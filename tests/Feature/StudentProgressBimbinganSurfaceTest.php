<?php

use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisProject;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Services\MentorshipProgressService;
use App\Services\StudentProgressSnapshot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function bimbinganSurfaceProject(User $lecturer, string $assignmentStatus = 'active', string $role = 'primary'): ThesisProject
{
    $prodi = ProgramStudi::factory()->create();
    $student = User::factory()->asMahasiswa()->create();
    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'is_active' => true,
    ]);

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'research',
        'state' => 'active',
        'started_at' => now()->subDays(60),
        'created_by' => $student->id,
    ]);

    ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => 'Judul Uji Bimbingan',
        'status' => 'approved',
        'submitted_by_user_id' => $student->id,
        'submitted_at' => now()->subDays(60),
    ]);

    ThesisSupervisorAssignment::query()->create([
        'project_id' => $project->id,
        'lecturer_user_id' => $lecturer->id,
        'role' => $role,
        'status' => $assignmentStatus,
        'assigned_by' => $student->id,
        'started_at' => now()->subDays(50),
        'ended_at' => $assignmentStatus === 'ended' ? now()->subDays(5) : null,
    ]);

    return $project;
}

function bimbinganSurfaceExaminedProject(User $lecturer): ThesisProject
{
    $supervisor = User::factory()->asDosen()->create();
    $project = bimbinganSurfaceProject($supervisor);
    $title = ThesisProjectTitle::query()->where('project_id', $project->id)->firstOrFail();

    $defense = ThesisDefense::query()->create([
        'project_id' => $project->id,
        'title_version_id' => $title->id,
        'type' => 'sempro',
        'attempt_no' => 1,
        'status' => 'scheduled',
        'result' => 'pending',
        'scheduled_for' => now()->addDays(3)->setTime(13, 0),
        'location' => 'Ruang Seminar A',
        'mode' => 'offline',
    ]);

    ThesisDefenseExaminer::query()->create([
        'defense_id' => $defense->id,
        'lecturer_user_id' => $lecturer->id,
        'role' => 'examiner',
        'order_no' => 1,
    ]);

    return $project;
}

function bimbinganSurfaceQueryCount(User $lecturer): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->actingAs($lecturer)->get(route('dosen.mahasiswa-bimbingan'))->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('carries a progress payload on every active row', function (): void {
    $lecturer = User::factory()->asDosen()->create();
    $supervised = bimbinganSurfaceProject($lecturer);
    $examined = bimbinganSurfaceExaminedProject($lecturer);

    $this->actingAs($lecturer)
        ->get(route('dosen.mahasiswa-bimbingan'))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($supervised, $examined): void {
            $page->component('dosen/mahasiswa-bimbingan')->has('mahasiswaRows', 2);

            $rows = collect($page->toArray()['props']['mahasiswaRows'])->keyBy('projectId');

            foreach ([$supervised, $examined] as $project) {
                expect($rows[$project->id]['progress'])
                    ->toBeArray()
                    ->toHaveKeys(['phase', 'phaseLabel', 'detail'])
                    ->and($rows[$project->id]['progress']['phase'])->toBe('research');
            }
        });
});

it('matches the progress payload with the snapshot service', function (): void {
    $lecturer = User::factory()->asDosen()->create();
    $project = bimbinganSurfaceProject($lecturer);

    $expected = app(StudentProgressSnapshot::class)
        ->forProjects(ThesisProject::query()->whereKey($project->id)->get(), $lecturer)[$project->id];

    $this->actingAs($lecturer)
        ->get(route('dosen.mahasiswa-bimbingan'))
        ->assertInertia(fn (Assert $page) => $page->where('mahasiswaRows.0.progress', $expected));
});

it('shows supervision detail to active supervisors and examiners', function (): void {
    $lecturer = User::factory()->asDosen()->create();
    bimbinganSurfaceProject($lecturer);
    bimbinganSurfaceExaminedProject($lecturer);

    $this->actingAs($lecturer)
        ->get(route('dosen.mahasiswa-bimbingan'))
        ->assertInertia(function (Assert $page): void {
            $rows = collect($page->toArray()['props']['mahasiswaRows']);

            expect($rows)->toHaveCount(2);
            $rows->each(fn (array $row) => expect($row['progress']['detail'])->toBeArray()->toHaveKeys(['statusLabel', 'supervisors', 'lastActivityAt', 'isInactive']));
        });
});

it('hides supervision detail on history rows where the dosen is no longer a supervisor', function (): void {
    $lecturer = User::factory()->asDosen()->create();
    $project = bimbinganSurfaceProject($lecturer, 'ended');

    $this->actingAs($lecturer)
        ->get(route('dosen.mahasiswa-bimbingan'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('mahasiswaRows', 0)
            ->has('historyRows', 1)
            ->where('historyRows.0.projectId', $project->id)
            ->where('historyRows.0.progress.phase', 'research')
            ->where('historyRows.0.progress.detail', null));
});

it('keeps the query count flat as students are added', function (): void {
    $lecturer = User::factory()->asDosen()->create();
    bimbinganSurfaceProject($lecturer);
    bimbinganSurfaceExaminedProject($lecturer);
    bimbinganSurfaceProject($lecturer, 'ended');

    // Warm the viewer's lazy relations once so the baseline does not include them.
    bimbinganSurfaceQueryCount($lecturer);
    $baseline = bimbinganSurfaceQueryCount($lecturer);

    foreach (range(1, 3) as $_) {
        bimbinganSurfaceProject($lecturer, 'active', 'secondary');
        bimbinganSurfaceExaminedProject($lecturer);
        bimbinganSurfaceProject($lecturer, 'ended');
    }

    expect(bimbinganSurfaceQueryCount($lecturer))->toBe($baseline);
});

it('builds one snapshot for active and history rows together', function (): void {
    $lecturer = User::factory()->asDosen()->create();
    bimbinganSurfaceProject($lecturer);
    bimbinganSurfaceProject($lecturer, 'ended');

    $spy = new class(app(MentorshipProgressService::class)) extends StudentProgressSnapshot
    {
        public int $calls = 0;

        public function forProjects(Collection $projects, ?User $viewer, bool $detailForAll = false): array
        {
            $this->calls++;

            return parent::forProjects($projects, $viewer, $detailForAll);
        }
    };
    $this->app->instance(StudentProgressSnapshot::class, $spy);

    $this->actingAs($lecturer)
        ->get(route('dosen.mahasiswa-bimbingan'))
        ->assertOk();

    expect($spy->calls)->toBe(1);
});
