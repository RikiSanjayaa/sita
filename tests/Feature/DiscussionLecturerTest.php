<?php

use App\Enums\AppRole;
use App\Models\DosenProfile;
use App\Models\DosenProgramStudiAssignment;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\ThesisProjectTitle;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function discussionLecturerUser(string $role): User
{
    $user = User::factory()->create(['last_active_role' => $role]);
    $roleModel = Role::query()->firstOrCreate(['name' => $role]);
    $user->roles()->sync([$roleModel->id]);

    return $user;
}

function discussionLecturerLecturer(ProgramStudi $programStudi, string $concentration): User
{
    $lecturer = discussionLecturerUser(AppRole::Dosen->value);

    DosenProfile::factory()->create([
        'user_id' => $lecturer->id,
        'program_studi_id' => $programStudi->id,
        'concentration' => $concentration,
        'is_active' => true,
    ]);

    DosenProgramStudiAssignment::query()->updateOrCreate(
        [
            'user_id' => $lecturer->id,
            'program_studi_id' => $programStudi->id,
            'concentration' => $concentration,
        ],
        [
            'is_primary' => true,
            'is_active' => true,
        ],
    );

    return $lecturer;
}

function discussionLecturerStudent(ProgramStudi $programStudi): User
{
    $student = discussionLecturerUser(AppRole::Mahasiswa->value);

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $programStudi->id,
        'is_active' => true,
    ]);

    return $student;
}

function discussionLecturerPayload(array $overrides = []): array
{
    return array_merge([
        'title_id' => 'Judul Dosen Diskusi',
        'title_en' => 'Discussion Lecturer Title',
        'proposal_summary' => 'Ringkasan proposal dengan dosen diskusi.',
        'proposal_file' => UploadedFile::fake()->create('proposal-diskusi.pdf', 700, 'application/pdf'),
    ], $overrides);
}

test('mahasiswa can submit a title with a discussion lecturer from own program studi', function () {
    Storage::fake('public');

    $prodi = ProgramStudi::factory()->create(['name' => 'Informatika']);
    $student = discussionLecturerStudent($prodi);
    $lecturer = discussionLecturerLecturer($prodi, $student->mahasiswaProfile->concentration);

    $this->actingAs($student)
        ->post('/mahasiswa/tugas-akhir', discussionLecturerPayload([
            'discussion_lecturer_user_id' => $lecturer->id,
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $title = ThesisProjectTitle::query()->firstOrFail();

    expect($title->discussion_lecturer_user_id)->toBe($lecturer->id);
});

test('submitting a title without a discussion lecturer is rejected', function () {
    Storage::fake('public');

    $prodi = ProgramStudi::factory()->create();
    $student = discussionLecturerStudent($prodi);

    $this->actingAs($student)
        ->post('/mahasiswa/tugas-akhir', discussionLecturerPayload())
        ->assertSessionHasErrors('discussion_lecturer_user_id');

    expect(ThesisProjectTitle::query()->count())->toBe(0);
});

test('discussion lecturer from another program studi is rejected', function () {
    Storage::fake('public');

    $prodi = ProgramStudi::factory()->create();
    $otherProdi = ProgramStudi::factory()->create();
    $student = discussionLecturerStudent($prodi);
    $outsider = discussionLecturerLecturer($otherProdi, 'Lintas Prodi');

    $this->actingAs($student)
        ->post('/mahasiswa/tugas-akhir', discussionLecturerPayload([
            'discussion_lecturer_user_id' => $outsider->id,
        ]))
        ->assertSessionHasErrors('discussion_lecturer_user_id');

    expect(ThesisProjectTitle::query()->count())->toBe(0);
});

test('saved discussion lecturer is prefilled on the student submission', function () {
    Storage::fake('public');

    $prodi = ProgramStudi::factory()->create();
    $student = discussionLecturerStudent($prodi);
    $lecturer = discussionLecturerLecturer($prodi, $student->mahasiswaProfile->concentration);

    $this->actingAs($student)
        ->post('/mahasiswa/tugas-akhir', discussionLecturerPayload([
            'discussion_lecturer_user_id' => $lecturer->id,
        ]))
        ->assertRedirect();

    $this->actingAs($student)
        ->get('/mahasiswa/tugas-akhir')
        ->assertInertia(fn (Assert $page) => $page
            ->where('submission.discussion_lecturer_user_id', $lecturer->id));
});
