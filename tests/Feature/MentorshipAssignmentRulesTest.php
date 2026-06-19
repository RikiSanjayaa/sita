<?php

use App\Enums\AdvisorType;
use App\Enums\AppRole;
use App\Enums\AssignmentStatus;
use App\Models\DosenProfile;
use App\Models\DosenProgramStudiAssignment;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipAssignment;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\ThesisProject;
use App\Models\ThesisSubmission;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Services\MentorshipAssignmentService;
use Illuminate\Validation\ValidationException;

function createRoleUser(string $role): User
{
    $user = User::factory()->create(['last_active_role' => $role]);
    $roleModel = Role::query()->firstOrCreate(['name' => $role]);
    $user->roles()->sync([$roleModel->id]);
    $programStudi = ProgramStudi::query()->firstOrCreate(
        ['slug' => 'test-prodi'],
        [
            'name' => 'Test Prodi',
            'concentrations' => ['Umum', 'Jaringan', 'Computer Vision'],
        ],
    );

    if ($role === AppRole::Mahasiswa->value) {
        MahasiswaProfile::factory()->create([
            'user_id' => $user->id,
            'program_studi_id' => $programStudi->id,
            'concentration' => 'Umum',
            'is_active' => true,
        ]);
    }

    if ($role === AppRole::Dosen->value) {
        DosenProfile::factory()->create([
            'user_id' => $user->id,
            'program_studi_id' => $programStudi->id,
            'concentration' => 'Umum',
            'is_active' => true,
        ]);
    }

    return $user;
}

test('student can have maximum two active advisors', function () {
    $admin = createRoleUser(AppRole::Admin->value);
    $student = createRoleUser(AppRole::Mahasiswa->value);
    $lecturerOne = createRoleUser(AppRole::Dosen->value);
    $lecturerTwo = createRoleUser(AppRole::Dosen->value);
    $lecturerThree = createRoleUser(AppRole::Dosen->value);

    MentorshipAssignment::query()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $lecturerOne->id,
        'advisor_type' => AdvisorType::Primary->value,
        'status' => AssignmentStatus::Active->value,
        'assigned_by' => $admin->id,
    ]);

    MentorshipAssignment::query()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $lecturerTwo->id,
        'advisor_type' => AdvisorType::Secondary->value,
        'status' => AssignmentStatus::Active->value,
        'assigned_by' => $admin->id,
    ]);

    expect(function () use ($admin, $student, $lecturerThree): void {
        MentorshipAssignment::query()->create([
            'student_user_id' => $student->id,
            'lecturer_user_id' => $lecturerThree->id,
            'advisor_type' => 'co-advisor',
            'status' => AssignmentStatus::Active->value,
            'assigned_by' => $admin->id,
        ]);
    })->toThrow(ValidationException::class);
});

test('advisor type must be unique per active student assignment', function () {
    $admin = createRoleUser(AppRole::Admin->value);
    $student = createRoleUser(AppRole::Mahasiswa->value);
    $lecturerOne = createRoleUser(AppRole::Dosen->value);
    $lecturerTwo = createRoleUser(AppRole::Dosen->value);

    MentorshipAssignment::query()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $lecturerOne->id,
        'advisor_type' => AdvisorType::Primary->value,
        'status' => AssignmentStatus::Active->value,
        'assigned_by' => $admin->id,
    ]);

    expect(function () use ($admin, $student, $lecturerTwo): void {
        MentorshipAssignment::query()->create([
            'student_user_id' => $student->id,
            'lecturer_user_id' => $lecturerTwo->id,
            'advisor_type' => AdvisorType::Primary->value,
            'status' => AssignmentStatus::Active->value,
            'assigned_by' => $admin->id,
        ]);
    })->toThrow(ValidationException::class);
});

test('lecturer quota blocks assignment above configured active mahasiswa count', function () {
    $admin = createRoleUser(AppRole::Admin->value);
    $lecturer = createRoleUser(AppRole::Dosen->value);

    $lecturer->dosenProfile()->update([
        'supervision_quota' => 2,
    ]);

    for ($index = 0; $index < 2; $index++) {
        $student = createRoleUser(AppRole::Mahasiswa->value);

        MentorshipAssignment::query()->create([
            'student_user_id' => $student->id,
            'lecturer_user_id' => $lecturer->id,
            'advisor_type' => AdvisorType::Primary->value,
            'status' => AssignmentStatus::Active->value,
            'assigned_by' => $admin->id,
        ]);
    }

    $extraStudent = createRoleUser(AppRole::Mahasiswa->value);

    expect(function () use ($admin, $lecturer, $extraStudent): void {
        MentorshipAssignment::query()->create([
            'student_user_id' => $extraStudent->id,
            'lecturer_user_id' => $lecturer->id,
            'advisor_type' => AdvisorType::Primary->value,
            'status' => AssignmentStatus::Active->value,
            'assigned_by' => $admin->id,
        ]);
    })->toThrow(ValidationException::class);
});

test('lecturer concentration does not need to match mahasiswa concentration', function () {
    $admin = createRoleUser(AppRole::Admin->value);
    $student = createRoleUser(AppRole::Mahasiswa->value);
    $lecturer = createRoleUser(AppRole::Dosen->value);

    $student->mahasiswaProfile()->update([
        'concentration' => 'Jaringan',
    ]);

    $lecturer->dosenProfile()->update([
        'concentration' => 'Computer Vision',
    ]);

    MentorshipAssignment::query()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $lecturer->id,
        'advisor_type' => AdvisorType::Primary->value,
        'status' => AssignmentStatus::Active->value,
        'assigned_by' => $admin->id,
    ]);

    expect(MentorshipAssignment::query()->where('student_user_id', $student->id)->count())->toBe(1);
});

test('lecturer must have an active assignment in the mahasiswa program studi', function () {
    $admin = createRoleUser(AppRole::Admin->value);
    $student = createRoleUser(AppRole::Mahasiswa->value);
    $lecturer = createRoleUser(AppRole::Dosen->value);
    $otherProgram = ProgramStudi::factory()->create();

    $lecturer->activeDosenProgramStudiAssignments()->update([
        'program_studi_id' => $otherProgram->id,
    ]);

    expect(function () use ($admin, $student, $lecturer): void {
        MentorshipAssignment::query()->create([
            'student_user_id' => $student->id,
            'lecturer_user_id' => $lecturer->id,
            'advisor_type' => AdvisorType::Primary->value,
            'status' => AssignmentStatus::Active->value,
            'assigned_by' => $admin->id,
        ]);
    })->toThrow(ValidationException::class);
});

test('inactive lecturer cannot be assigned as mahasiswa advisor', function () {
    $admin = createRoleUser(AppRole::Admin->value);
    $student = createRoleUser(AppRole::Mahasiswa->value);
    $lecturer = createRoleUser(AppRole::Dosen->value);

    $lecturer->dosenProfile()->update(['is_active' => false]);

    expect(function () use ($admin, $student, $lecturer): void {
        MentorshipAssignment::query()->create([
            'student_user_id' => $student->id,
            'lecturer_user_id' => $lecturer->id,
            'advisor_type' => AdvisorType::Primary->value,
            'status' => AssignmentStatus::Active->value,
            'assigned_by' => $admin->id,
        ]);
    })->toThrow(ValidationException::class);
});

test('lecturer can advise student through additional academic assignment', function () {
    $admin = createRoleUser(AppRole::Admin->value);
    $student = createRoleUser(AppRole::Mahasiswa->value);
    $lecturer = createRoleUser(AppRole::Dosen->value);
    $programStudiId = $student->mahasiswaProfile?->program_studi_id;

    $student->mahasiswaProfile()->update([
        'concentration' => 'Jaringan',
    ]);

    DosenProgramStudiAssignment::query()->create([
        'user_id' => $lecturer->id,
        'program_studi_id' => $programStudiId,
        'concentration' => 'Jaringan',
        'is_primary' => false,
        'is_active' => true,
    ]);

    MentorshipAssignment::query()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $lecturer->id,
        'advisor_type' => AdvisorType::Primary->value,
        'status' => AssignmentStatus::Active->value,
        'assigned_by' => $admin->id,
    ]);

    expect(MentorshipAssignment::query()->where('student_user_id', $student->id)->count())->toBe(1);
});

test('sync student advisors no longer backfills thesis project supervisor snapshot', function () {
    $admin = createRoleUser(AppRole::Admin->value);
    $student = createRoleUser(AppRole::Mahasiswa->value);
    $lecturerOne = createRoleUser(AppRole::Dosen->value);
    $lecturerTwo = createRoleUser(AppRole::Dosen->value);
    $prodi = ProgramStudi::factory()->create();

    $student->mahasiswaProfile()->update([
        'program_studi_id' => $prodi->id,
    ]);

    foreach ([$lecturerOne, $lecturerTwo] as $lecturer) {
        DosenProgramStudiAssignment::query()->create([
            'user_id' => $lecturer->id,
            'program_studi_id' => $prodi->id,
            'concentration' => $student->mahasiswaProfile?->concentration ?? 'Umum',
            'is_primary' => false,
            'is_active' => true,
        ]);
    }

    ThesisSubmission::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'title_id' => 'Sinkronisasi Pembimbing',
        'status' => 'pembimbing_ditetapkan',
        'is_active' => true,
        'submitted_at' => now()->subDays(8),
    ]);

    app(MentorshipAssignmentService::class)->syncStudentAdvisors(
        studentUserId: $student->id,
        assignedBy: $admin->id,
        primaryLecturerUserId: $lecturerOne->id,
        secondaryLecturerUserId: $lecturerTwo->id,
        notes: 'Snapshot pembimbing',
    );

    expect(ThesisProject::query()->count())->toBe(0)
        ->and(ThesisSupervisorAssignment::query()->count())->toBe(0)
        ->and(MentorshipAssignment::query()->where('status', AssignmentStatus::Active->value)->count())->toBe(2);
});
