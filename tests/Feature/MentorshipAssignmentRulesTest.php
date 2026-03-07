<?php

use App\Enums\AdvisorType;
use App\Enums\AppRole;
use App\Enums\AssignmentStatus;
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

    if ($role === AppRole::Mahasiswa->value) {
        \App\Models\MahasiswaProfile::factory()->create(['user_id' => $user->id, 'is_active' => true]);
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

test('lecturer quota blocks assignment above fourteen active mahasiswa', function () {
    $admin = createRoleUser(AppRole::Admin->value);
    $lecturer = createRoleUser(AppRole::Dosen->value);

    for ($index = 0; $index < 14; $index++) {
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

test('sync student advisors updates thesis project supervisor snapshot', function () {
    $admin = createRoleUser(AppRole::Admin->value);
    $student = createRoleUser(AppRole::Mahasiswa->value);
    $lecturerOne = createRoleUser(AppRole::Dosen->value);
    $lecturerTwo = createRoleUser(AppRole::Dosen->value);
    $prodi = ProgramStudi::factory()->create();

    $student->mahasiswaProfile()->update([
        'program_studi_id' => $prodi->id,
    ]);

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

    expect(ThesisProject::query()->count())->toBe(1)
        ->and(ThesisSupervisorAssignment::query()->count())->toBe(2)
        ->and(ThesisSupervisorAssignment::query()->where('status', AssignmentStatus::Active->value)->count())->toBe(2);
});
