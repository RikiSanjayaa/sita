<?php

use App\Enums\AdvisorType;
use App\Enums\AppRole;
use App\Enums\AssignmentStatus;
use App\Models\DosenProfile;
use App\Models\DosenProgramStudiAssignment;
use App\Models\MentorshipAssignment;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\ThesisProject;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function createUserWithRole(string $role): User
{
    $user = User::factory()->create(['last_active_role' => $role]);
    $roleModel = Role::query()->firstOrCreate(['name' => $role]);
    $user->roles()->sync([$roleModel->id]);

    if ($role === AppRole::Dosen->value) {
        DosenProfile::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);
    }

    return $user;
}

function ensureActiveThesisProject(User $student): ThesisProject
{
    $profile = $student->mahasiswaProfile;
    $programStudiId = $profile?->program_studi_id ?? ProgramStudi::factory()->create()->id;

    return ThesisProject::query()->firstOrCreate(
        [
            'student_user_id' => $student->id,
            'state' => 'active',
        ],
        [
            'program_studi_id' => $programStudiId,
            'phase' => 'research',
            'started_at' => now()->subWeek(),
            'created_by' => $student->id,
        ],
    );
}

function ensureLecturerTeachesStudent(User $lecturer, User $student): void
{
    $profile = $student->mahasiswaProfile;

    if ($profile === null || $profile->program_studi_id === null || $profile->concentration === null) {
        return;
    }

    $lecturer->dosenProfile()->updateOrCreate(
        ['user_id' => $lecturer->id],
        [
            'program_studi_id' => $profile->program_studi_id,
            'concentration' => $profile->concentration,
            'is_active' => true,
        ],
    );

    DosenProgramStudiAssignment::query()->updateOrCreate(
        [
            'user_id' => $lecturer->id,
            'program_studi_id' => $profile->program_studi_id,
            'concentration' => $profile->concentration,
        ],
        [
            'is_primary' => true,
            'is_active' => true,
        ],
    );
}

function createActiveMentorshipAssignment(User $student, User $lecturer, User $admin, AdvisorType $advisorType): void
{
    ensureLecturerTeachesStudent($lecturer, $student);

    MentorshipAssignment::query()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $lecturer->id,
        'advisor_type' => $advisorType->value,
        'status' => AssignmentStatus::Active->value,
        'assigned_by' => $admin->id,
    ]);
}

function syncThesisSupervisor(User $student, User $lecturer, User $admin, string $role): void
{
    ensureLecturerTeachesStudent($lecturer, $student);

    $project = ensureActiveThesisProject($student);

    ThesisSupervisorAssignment::query()->updateOrCreate(
        [
            'project_id' => $project->id,
            'role' => $role,
            'status' => 'active',
        ],
        [
            'lecturer_user_id' => $lecturer->id,
            'assigned_by' => $admin->id,
            'started_at' => now(),
        ],
    );
}
