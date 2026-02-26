<?php

use App\Enums\AdvisorType;
use App\Enums\AppRole;
use App\Enums\AssignmentStatus;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipAssignment;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function createRoleBasedUser(string $role): User
{
    $user = User::factory()->create(['last_active_role' => $role]);
    $roleModel = Role::query()->firstOrCreate(['name' => $role]);
    $user->roles()->sync([$roleModel->id]);

    return $user;
}

test('admin can view assignment queue from database', function () {
    $admin = createRoleBasedUser(AppRole::Admin->value);
    $student = createRoleBasedUser(AppRole::Mahasiswa->value);
    $lecturer = createRoleBasedUser(AppRole::Dosen->value);

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'nim' => '2210518888',
        'program_studi' => 'Informatika',
        'status_akademik' => 'aktif',
    ]);

    MentorshipAssignment::query()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $lecturer->id,
        'advisor_type' => AdvisorType::Primary->value,
        'status' => AssignmentStatus::Active->value,
        'assigned_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.penugasan'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/penugasan')
            ->has('queue', 1)
            ->where('queue.0.studentUserId', $student->id)
            ->where('queue.0.primaryAdvisor.id', $lecturer->id)
            ->has('lecturers')
        );
});

test('admin can assign primary and secondary advisor', function () {
    $admin = createRoleBasedUser(AppRole::Admin->value);
    $student = createRoleBasedUser(AppRole::Mahasiswa->value);
    $primaryLecturer = createRoleBasedUser(AppRole::Dosen->value);
    $secondaryLecturer = createRoleBasedUser(AppRole::Dosen->value);

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'status_akademik' => 'aktif',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.penugasan.store'), [
            'student_user_id' => $student->id,
            'primary_lecturer_user_id' => $primaryLecturer->id,
            'secondary_lecturer_user_id' => $secondaryLecturer->id,
            'notes' => 'Assignment awal',
        ])
        ->assertRedirect(route('admin.penugasan'));

    $this->assertDatabaseHas('mentorship_assignments', [
        'student_user_id' => $student->id,
        'lecturer_user_id' => $primaryLecturer->id,
        'advisor_type' => AdvisorType::Primary->value,
        'status' => AssignmentStatus::Active->value,
    ]);

    $this->assertDatabaseHas('mentorship_assignments', [
        'student_user_id' => $student->id,
        'lecturer_user_id' => $secondaryLecturer->id,
        'advisor_type' => AdvisorType::Secondary->value,
        'status' => AssignmentStatus::Active->value,
    ]);
});

test('admin can reassign advisor and old assignment is ended', function () {
    $admin = createRoleBasedUser(AppRole::Admin->value);
    $student = createRoleBasedUser(AppRole::Mahasiswa->value);
    $oldLecturer = createRoleBasedUser(AppRole::Dosen->value);
    $newLecturer = createRoleBasedUser(AppRole::Dosen->value);

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'status_akademik' => 'aktif',
    ]);

    $oldAssignment = MentorshipAssignment::query()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $oldLecturer->id,
        'advisor_type' => AdvisorType::Primary->value,
        'status' => AssignmentStatus::Active->value,
        'assigned_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.penugasan.store'), [
            'student_user_id' => $student->id,
            'primary_lecturer_user_id' => $newLecturer->id,
            'secondary_lecturer_user_id' => null,
            'notes' => null,
        ])
        ->assertRedirect(route('admin.penugasan'));

    $oldAssignment->refresh();

    expect($oldAssignment->status)->toBe(AssignmentStatus::Ended->value);
    expect($oldAssignment->ended_at)->not->toBeNull();

    $this->assertDatabaseHas('mentorship_assignments', [
        'student_user_id' => $student->id,
        'lecturer_user_id' => $newLecturer->id,
        'advisor_type' => AdvisorType::Primary->value,
        'status' => AssignmentStatus::Active->value,
    ]);
});

test('admin assignment is blocked when lecturer quota is full', function () {
    $admin = createRoleBasedUser(AppRole::Admin->value);
    $fullLecturer = createRoleBasedUser(AppRole::Dosen->value);

    for ($index = 0; $index < 14; $index++) {
        $student = createRoleBasedUser(AppRole::Mahasiswa->value);

        MahasiswaProfile::factory()->create([
            'user_id' => $student->id,
            'status_akademik' => 'aktif',
        ]);

        MentorshipAssignment::query()->create([
            'student_user_id' => $student->id,
            'lecturer_user_id' => $fullLecturer->id,
            'advisor_type' => AdvisorType::Primary->value,
            'status' => AssignmentStatus::Active->value,
            'assigned_by' => $admin->id,
        ]);
    }

    $newStudent = createRoleBasedUser(AppRole::Mahasiswa->value);
    MahasiswaProfile::factory()->create([
        'user_id' => $newStudent->id,
        'status_akademik' => 'aktif',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.penugasan.store'), [
            'student_user_id' => $newStudent->id,
            'primary_lecturer_user_id' => $fullLecturer->id,
            'secondary_lecturer_user_id' => null,
            'notes' => null,
        ])
        ->assertSessionHasErrors('primary_lecturer_user_id');
});

test('admin cannot assign mahasiswa with final academic status', function () {
    $admin = createRoleBasedUser(AppRole::Admin->value);
    $lecturer = createRoleBasedUser(AppRole::Dosen->value);
    $student = createRoleBasedUser(AppRole::Mahasiswa->value);

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'status_akademik' => 'lulus',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.penugasan.store'), [
            'student_user_id' => $student->id,
            'primary_lecturer_user_id' => $lecturer->id,
            'secondary_lecturer_user_id' => null,
        ])
        ->assertSessionHasErrors('student_user_id');
});
