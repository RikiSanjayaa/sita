<?php

use App\Models\DosenProfile;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisProject;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Services\UserProfilePresenter;
use Illuminate\Support\Facades\DB;

/**
 * @return array{project: ThesisProject, student: User}
 */
function profileSurfaceStudent(ProgramStudi $prodi, User $supervisor, string $phase = 'research'): array
{
    $student = User::factory()->asMahasiswa()->create();
    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'is_active' => true,
    ]);

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'phase' => $phase,
        'state' => 'active',
        'started_at' => now()->subDays(90),
        'created_by' => $student->id,
    ]);

    ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => 'Judul Uji Profil',
        'status' => 'approved',
        'submitted_by_user_id' => $student->id,
        'submitted_at' => now()->subDays(90),
    ]);

    ThesisSupervisorAssignment::query()->create([
        'project_id' => $project->id,
        'lecturer_user_id' => $supervisor->id,
        'role' => 'primary',
        'status' => 'active',
        'assigned_by' => $student->id,
        'started_at' => now()->subDays(80),
    ]);

    return compact('project', 'student');
}

function profileSurfaceDosen(ProgramStudi $prodi): User
{
    $dosen = User::factory()->asDosen()->create();
    DosenProfile::factory()->create([
        'user_id' => $dosen->id,
        'program_studi_id' => $prodi->id,
        'is_active' => true,
    ]);

    return $dosen;
}

function profileSurfaceQueryCount(User $profileOwner, User $viewer): int
{
    $presenter = app(UserProfilePresenter::class);
    $owner = User::query()->findOrFail($profileOwner->id);
    $viewer = User::query()->findOrFail($viewer->id);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $presenter->detail($owner, $viewer);
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('adds a progress entry to each student card on a dosen profile', function (): void {
    $prodi = ProgramStudi::factory()->create();
    $dosen = profileSurfaceDosen($prodi);
    $supervised = profileSurfaceStudent($prodi, $dosen, 'research');

    $examinedStudent = User::factory()->asMahasiswa()->create();
    MahasiswaProfile::factory()->create([
        'user_id' => $examinedStudent->id,
        'program_studi_id' => $prodi->id,
        'is_active' => true,
    ]);
    $examinedProject = ThesisProject::query()->create([
        'student_user_id' => $examinedStudent->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'sempro',
        'state' => 'active',
        'started_at' => now()->subDays(30),
        'created_by' => $examinedStudent->id,
    ]);
    $defense = ThesisDefense::query()->create([
        'project_id' => $examinedProject->id,
        'type' => 'sempro',
        'attempt_no' => 1,
        'status' => 'scheduled',
        'scheduled_for' => now()->addWeek(),
    ]);
    ThesisDefenseExaminer::query()->create([
        'defense_id' => $defense->id,
        'lecturer_user_id' => $dosen->id,
        'role' => 'examiner',
        'order_no' => 1,
    ]);

    $viewer = User::factory()->asAdmin()->create();

    $profile = app(UserProfilePresenter::class)->detail($dosen, $viewer);

    $supervisedRow = $profile['relatedUsers'][0]['users'][0];
    $examinedRow = $profile['relatedUsers'][1]['users'][0];

    expect($supervisedRow)->toHaveKey('progress')
        ->and($supervisedRow['progress']['phase'])->toBe('research')
        ->and($supervisedRow['progress']['detail'])->not->toBeNull()
        ->and($examinedRow)->toHaveKey('progress')
        ->and($examinedRow['progress']['phase'])->toBe('sempro')
        ->and($supervised['project']->phase)->toBe('research');
});

it('shows supervision detail to a permitted viewer and hides it from an unrelated dosen', function (): void {
    $prodi = ProgramStudi::factory()->create();
    $dosen = profileSurfaceDosen($prodi);
    profileSurfaceStudent($prodi, $dosen);
    $unrelated = profileSurfaceDosen(ProgramStudi::factory()->create());

    $presenter = app(UserProfilePresenter::class);

    $asSupervisor = $presenter->detail($dosen, $dosen)['relatedUsers'][0]['users'][0]['progress'];
    $asUnrelated = $presenter->detail(
        User::query()->findOrFail($dosen->id),
        $unrelated,
    )['relatedUsers'][0]['users'][0]['progress'];

    expect($asSupervisor['detail'])->not->toBeNull()
        ->and($asSupervisor['detail']['supervisors'])->toHaveCount(1)
        ->and($asUnrelated['phase'])->toBe('research')
        ->and($asUnrelated['detail'])->toBeNull();
});

it('exposes progress with detail on the mahasiswa self profile', function (): void {
    $prodi = ProgramStudi::factory()->create();
    $dosen = profileSurfaceDosen($prodi);
    ['student' => $student, 'project' => $project] = profileSurfaceStudent($prodi, $dosen, 'sempro');

    $response = $this->actingAs($student)
        ->get(route('users.profile.show', ['user' => $student->id]))
        ->assertOk();

    $page = $response->viewData('page');

    expect(data_get($page, 'props.profile.progress.phase'))->toBe($project->phase)
        ->and(data_get($page, 'props.profile.progress.detail'))->not->toBeNull()
        ->and(data_get($page, 'props.profile.progress.detail.supervisors.0.name'))->toBe($dosen->name);
});

it('gives a student without a project a null progress', function (): void {
    $prodi = ProgramStudi::factory()->create();
    $student = User::factory()->asMahasiswa()->create();
    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'is_active' => true,
    ]);

    $profile = app(UserProfilePresenter::class)->detail($student, $student);

    expect($profile)->toHaveKey('progress')
        ->and($profile['progress'])->toBeNull();
});

it('keeps the dosen profile query count flat as students are added', function (): void {
    $prodi = ProgramStudi::factory()->create();
    $dosen = profileSurfaceDosen($prodi);
    $viewer = User::factory()->asAdmin()->create();

    profileSurfaceStudent($prodi, $dosen);
    $withOne = profileSurfaceQueryCount($dosen, $viewer);

    foreach (range(1, 3) as $ignored) {
        profileSurfaceStudent($prodi, $dosen);
    }
    $withFour = profileSurfaceQueryCount($dosen, $viewer);

    expect($withFour)->toBe($withOne);
});
