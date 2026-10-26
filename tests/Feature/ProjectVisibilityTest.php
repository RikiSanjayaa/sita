<?php

use App\Filament\Resources\ThesisProjects\ThesisProjectResource;
use App\Models\AdminProfile;
use App\Models\KaprodiAssignment;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisProject;
use App\Models\ThesisProjectNote;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Services\DosenBimbinganService;
use App\Services\DosenThesisWorkspaceService;
use App\Services\KaprodiPortalService;
use App\Services\MentorshipScheduleService;

function directoryVisibilityProject(User $student, ProgramStudi $program, string $state, string $title, string $startedAt): ThesisProject
{
    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $program->id,
        'state' => $state,
        'phase' => $state === 'completed' ? 'completed' : 'title_review',
        'started_at' => $startedAt,
    ]);
    ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => $title,
        'status' => $state === 'active' ? 'submitted' : 'approved',
    ]);

    return $project;
}

function directoryVisibilityStudent(ProgramStudi $program): User
{
    $student = User::factory()->asMahasiswa()->create();
    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $program->id,
        'degree_level' => 's1',
        'is_active' => true,
    ]);

    return $student;
}

test('historical theses never duplicate a current student or leak through non-admin lists', function (string $oldState): void {
    $program = ProgramStudi::factory()->create();
    $student = directoryVisibilityStudent($program);
    $old = directoryVisibilityProject($student, $program, $oldState, 'Historical private title', '2025-09-10');
    $current = directoryVisibilityProject($student, $program, 'active', 'Current pending title', '2026-03-01');
    $kaprodi = User::factory()->asKaprodi()->create();
    KaprodiAssignment::factory()->create(['user_id' => $kaprodi->id, 'program_studi_id' => $program->id, 'is_primary' => true]);
    ThesisProjectNote::factory()->create(['project_id' => $old->id, 'author_user_id' => $kaprodi->id, 'body' => 'Historical private note']);
    $oldDefense = ThesisDefense::query()->create([
        'project_id' => $old->id, 'type' => 'sempro', 'attempt_no' => 1,
        'status' => 'scheduled', 'scheduled_for' => now()->addWeek(), 'location' => 'Historical private room',
    ]);
    ThesisDefense::query()->create([
        'project_id' => $old->id, 'type' => 'sidang', 'attempt_no' => 1,
        'status' => 'completed', 'result' => 'pass_with_revision', 'scheduled_for' => now()->subDay(),
        'location' => 'Historical follow-up room',
    ]);

    $props = data_get($this->actingAs($kaprodi)->get('/kaprodi/mahasiswa')->assertOk()->viewData('page'), 'props');
    expect(collect($props['students'])->where('id', $student->id)->values()->all())
        ->toHaveCount(1)
        ->and($props['students'][0]['projectId'])->toBe($current->id)
        ->and($props['archives'])->toBe([])
        ->and(json_encode($props))->not->toContain('Historical private title', 'Historical private note', 'Historical private room')
        ->and(array_keys($props['projectNotes']))->not->toContain($old->id, (string) $old->id);

    $service = app(KaprodiPortalService::class);
    foreach ([$service->dashboard($program, $kaprodi->kaprodiAssignment), $service->exams($program), $service->documents($program, $kaprodi), $service->archives($program), $service->studentDetail($program, $student)] as $payload) {
        expect(json_encode($payload))->not->toContain('Historical private title', 'Historical private room', 'Historical follow-up room');
    }

    $this->app['auth']->forgetGuards();
    foreach (['/topik', '/mahasiswa-aktif', '/jadwal'] as $url) {
        $payload = data_get($this->get($url)->assertOk()->viewData('page'), 'props');
        expect(json_encode($payload))->not->toContain('Historical private title', 'Current pending title', 'Historical private room', 'Historical follow-up room');
        if ($url === '/mahasiswa-aktif') {
            expect(collect($payload['activeStudents'])->where('id', $student->id))->toHaveCount(1);
        }
        if ($url === '/jadwal') {
            expect(collect($payload['upcomingSchedules'])->pluck('id'))->not->toContain($oldDefense->id);
        }
    }

    $viewer = User::factory()->asMahasiswa()->create();
    $profile = data_get($this->actingAs($viewer)->get('/profil/'.$student->id)->assertOk()->viewData('page'), 'props.profile');
    expect(json_encode($profile))->not->toContain('Historical private title', 'Current pending title')
        ->and(data_get($profile, 'thesis.titleVisible'))->toBeFalse();

    $admin = User::factory()->asAdmin()->create();
    AdminProfile::query()->create(['user_id' => $admin->id, 'program_studi_id' => $program->id]);
    $this->actingAs($admin)->get(ThesisProjectResource::getUrl('view', ['record' => $old]))->assertOk()->assertSee('Historical private title');
    expect(ThesisProject::query()->where('student_user_id', $student->id)->count())->toBe(2);
})->with(['completed', 'cancelled', 'active']);

test('a current completed publication stays public until a later thesis supersedes it', function (): void {
    $program = ProgramStudi::factory()->create();
    $student = directoryVisibilityStudent($program);
    directoryVisibilityProject($student, $program, 'completed', 'Completed publication', '2025-09-10');

    $props = data_get($this->get('/topik')->assertOk()->viewData('page'), 'props');
    expect(collect($props['semproTitles'])->pluck('title')->all())->toBe(['Completed publication']);

    directoryVisibilityProject($student, $program, 'active', 'Pending replacement', '2026-03-01');
    $props = data_get($this->get('/topik')->assertOk()->viewData('page'), 'props');
    expect($props['semproTitles'])->toBe([])->and($props['topicTotal'])->toBe(0);
});

test('latest cancellation never revives older work and leaves one neutral student row', function (): void {
    $program = ProgramStudi::factory()->create();
    $student = directoryVisibilityStudent($program);
    directoryVisibilityProject($student, $program, 'completed', 'Older completed title', '2025-09-10');
    directoryVisibilityProject($student, $program, 'cancelled', 'Latest cancelled title', '2026-03-01');

    $rows = app(KaprodiPortalService::class)->studentIndex($program, null);
    expect($rows['students'])->toHaveCount(1)
        ->and($rows['students'][0]['projectId'])->toBeNull()
        ->and($rows['archives'])->toBe([]);
    $props = data_get($this->get('/topik')->assertOk()->viewData('page'), 'props');
    expect($props['semproTitles'])->toBe([]);

    foreach (['/mahasiswa/dashboard', '/mahasiswa/tugas-akhir'] as $url) {
        $props = data_get($this->actingAs($student)->get($url)->assertOk()->viewData('page'), 'props');
        expect(json_encode($props))->not->toContain('Older completed title', 'Latest cancelled title');
        if ($url === '/mahasiswa/tugas-akhir') {
            expect($props['submission'])->toBeNull();
        }
    }
});

test('current project ordering uses lifecycle start then id and preserves same-project title history', function (): void {
    $program = ProgramStudi::factory()->create();
    $student = directoryVisibilityStudent($program);
    $first = directoryVisibilityProject($student, $program, 'active', 'First title', '2026-03-01');
    $current = directoryVisibilityProject($student, $program, 'active', 'Current original title', '2026-03-01');
    directoryVisibilityProject($student, $program, 'completed', 'Imported old title', '2025-09-10');
    directoryVisibilityProject($student, $program, 'completed', 'Undated imported title', '2025-01-01')->update(['started_at' => null]);
    ThesisProjectTitle::query()->create([
        'project_id' => $current->id, 'version_no' => 2, 'title_id' => 'Current revised title', 'status' => 'submitted',
    ]);

    $props = data_get($this->actingAs($student)->get('/mahasiswa/tugas-akhir')->assertOk()->viewData('page'), 'props');
    expect($props['submission']['id'])->toBe($current->id)
        ->and($props['submission']['title_id'])->toBe('Current revised title')
        ->and($current->titles()->orderBy('version_no')->pluck('title_id')->all())->toBe(['Current original title', 'Current revised title'])
        ->and(ThesisProject::query()->find($first->id))->not->toBeNull();
});

test('an undated imported thesis does not displace dated current work', function (): void {
    $program = ProgramStudi::factory()->create();
    $student = directoryVisibilityStudent($program);
    $current = directoryVisibilityProject($student, $program, 'active', 'Dated current title', '2026-03-01');
    $undated = directoryVisibilityProject($student, $program, 'completed', 'Undated historical publication', '2025-01-01');
    $undated->update(['started_at' => null]);

    $props = data_get($this->get('/topik')->assertOk()->viewData('page'), 'props');
    expect($props['semproTitles'])->toBe([]);

    $props = data_get($this->actingAs($student)->get('/mahasiswa/tugas-akhir')->assertOk()->viewData('page'), 'props');
    expect($props['submission']['id'])->toBe($current->id)
        ->and($props['submission']['title_id'])->toBe('Dated current title');

    $current->update(['started_at' => null]);
    expect(ThesisProject::query()->current()->where('student_user_id', $student->id)->value('id'))->toBe($undated->id);
});

test('lecturer assignments and exam workspaces exclude superseded theses', function (): void {
    $program = ProgramStudi::factory()->create();
    $student = directoryVisibilityStudent($program);
    $lecturer = User::factory()->asDosen()->create();
    $old = directoryVisibilityProject($student, $program, 'active', 'Superseded lecturer title', '2025-09-10');
    foreach (['active', 'ended'] as $status) {
        ThesisSupervisorAssignment::query()->create([
            'project_id' => $old->id, 'lecturer_user_id' => $lecturer->id,
            'role' => $status === 'active' ? 'primary' : 'secondary', 'status' => $status,
        ]);
    }
    $defense = ThesisDefense::query()->create([
        'project_id' => $old->id, 'type' => 'sempro', 'attempt_no' => 1, 'status' => 'scheduled',
    ]);
    ThesisDefenseExaminer::query()->create([
        'defense_id' => $defense->id, 'lecturer_user_id' => $lecturer->id, 'role' => 'examiner', 'order_no' => 1,
    ]);
    directoryVisibilityProject($student, $program, 'active', 'Replacement lecturer title', '2026-03-01');

    expect(app(DosenBimbinganService::class)->activeAssignmentsWithStudent($lecturer))->toBeEmpty()
        ->and(app(DosenBimbinganService::class)->archivedAssignmentsWithStudent($lecturer))->toBeEmpty()
        ->and(app(DosenThesisWorkspaceService::class)->activeSupervisionAssignments($lecturer))->toBeEmpty()
        ->and(app(DosenThesisWorkspaceService::class)->defenseExaminerAssignments($lecturer))->toBeEmpty()
        ->and(app(MentorshipScheduleService::class)->supervisedStudents($lecturer))->toBeEmpty();
});
