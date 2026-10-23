<?php

use App\Models\KaprodiAssignment;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\ThesisProject;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Services\KaprodiPortalService;
use App\Services\StudentProgressSnapshot;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function kaprodiSurfaceKaprodi(ProgramStudi $prodi): User
{
    $kaprodi = User::factory()->asKaprodi()->create();
    KaprodiAssignment::factory()->create([
        'user_id' => $kaprodi->id,
        'program_studi_id' => $prodi->id,
    ]);

    return $kaprodi;
}

function kaprodiSurfaceStudent(ProgramStudi $prodi, string $name, bool $withProject = true): User
{
    $student = User::factory()->asMahasiswa()->create(['name' => $name]);
    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'is_active' => true,
    ]);

    if (! $withProject) {
        return $student;
    }

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'research',
        'state' => 'active',
        'started_at' => now()->subDays(120),
        'created_by' => $student->id,
    ]);

    ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => 'Judul '.$name,
        'status' => 'approved',
        'submitted_by_user_id' => $student->id,
        'submitted_at' => now()->subDays(120),
    ]);

    ThesisSupervisorAssignment::query()->create([
        'project_id' => $project->id,
        'lecturer_user_id' => User::factory()->asDosen()->create()->id,
        'role' => 'primary',
        'status' => 'active',
        'assigned_by' => $student->id,
        'started_at' => now()->subDays(100),
    ]);

    return $student;
}

it('gives every kaprodi student row a progress payload with detail for the same-prodi kaprodi', function (): void {
    $prodi = ProgramStudi::factory()->create();
    $kaprodi = kaprodiSurfaceKaprodi($prodi);
    kaprodiSurfaceStudent($prodi, 'Alpha Mahasiswa');
    kaprodiSurfaceStudent($prodi, 'Beta Mahasiswa', withProject: false);

    $this->actingAs($kaprodi)
        ->get('/kaprodi/mahasiswa')
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('kaprodi/mahasiswa')
            ->where('students.0.name', 'Alpha Mahasiswa')
            ->where('students.0.progress.phase', 'research')
            ->has('students.0.progress.phaseLabel')
            ->has('students.0.progress.detail.supervisors', 1)
            ->has('students.0.progress.detail.isInactive')
            ->where('students.1.name', 'Beta Mahasiswa')
            ->where('students.1.progress', null));
});

it('hides supervision detail from a kaprodi of another prodi and lists only their own students', function (): void {
    $prodiA = ProgramStudi::factory()->create();
    $prodiB = ProgramStudi::factory()->create();
    $kaprodiB = kaprodiSurfaceKaprodi($prodiB);
    $studentA = kaprodiSurfaceStudent($prodiA, 'Mahasiswa Prodi A');
    kaprodiSurfaceStudent($prodiB, 'Mahasiswa Prodi B');

    $this->actingAs($kaprodiB)
        ->get('/kaprodi/mahasiswa')
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('students.0.name', 'Mahasiswa Prodi B')
            ->has('students.0.progress.detail')
            ->missing('students.1'));

    $projectA = ThesisProject::query()->where('student_user_id', $studentA->id)->get();
    $snapshot = app(StudentProgressSnapshot::class)->forProjects($projectA, $kaprodiB);

    expect($snapshot[$projectA->first()->id]['detail'])->toBeNull();
});

it('builds one snapshot per student index request', function (): void {
    $prodi = ProgramStudi::factory()->create();
    $kaprodi = kaprodiSurfaceKaprodi($prodi);
    foreach (range(1, 4) as $i) {
        kaprodiSurfaceStudent($prodi, "Mahasiswa {$i}");
    }

    $viewers = new ArrayObject;
    app()->extend(StudentProgressSnapshot::class, function (StudentProgressSnapshot $snapshot) use ($viewers): StudentProgressSnapshot {
        return new class($snapshot, $viewers) extends StudentProgressSnapshot
        {
            public function __construct(private StudentProgressSnapshot $inner, private ArrayObject $viewers) {}

            public function forProjects(Illuminate\Database\Eloquent\Collection $projects, ?User $viewer, bool $detailForAll = false): array
            {
                $this->viewers->append($viewer?->id);

                return $this->inner->forProjects($projects, $viewer, $detailForAll);
            }
        };
    });

    $this->actingAs($kaprodi)->get('/kaprodi/mahasiswa')->assertOk();

    expect($viewers->getArrayCopy())->toBe([$kaprodi->id]);
});

it('does not run extra queries as students are added', function (): void {
    $prodi = ProgramStudi::factory()->create();
    $kaprodi = kaprodiSurfaceKaprodi($prodi);
    $service = app(KaprodiPortalService::class);
    $counter = 0;
    $addStudent = function () use ($prodi, &$counter): void {
        kaprodiSurfaceStudent($prodi, 'Mahasiswa Antrian '.++$counter);
    };
    $queriesForIndex = function () use ($service, $prodi, $kaprodi): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $service->studentIndex($prodi, $kaprodi);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    foreach (range(1, 2) as $_) {
        $addStudent();
    }
    // Warm the viewer's lazy relations once so the first measurement does not include them.
    $queriesForIndex();
    $small = $queriesForIndex();

    foreach (range(1, 6) as $_) {
        $addStudent();
    }

    expect($queriesForIndex())->toBe($small);
});
