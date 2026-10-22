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

function proposedSupervisorsUser(string $role): User
{
    $user = User::factory()->create(['last_active_role' => $role]);
    $roleModel = Role::query()->firstOrCreate(['name' => $role]);
    $user->roles()->sync([$roleModel->id]);

    return $user;
}

function proposedSupervisorsLecturer(ProgramStudi $programStudi, string $concentration): User
{
    $lecturer = proposedSupervisorsUser(AppRole::Dosen->value);

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

function proposedSupervisorsStudent(ProgramStudi $programStudi): User
{
    $student = proposedSupervisorsUser(AppRole::Mahasiswa->value);

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $programStudi->id,
        'is_active' => true,
    ]);

    return $student;
}

function proposedSupervisorsPayload(array $overrides = []): array
{
    return array_merge([
        'title_id' => 'Judul Usulan Dosen',
        'title_en' => 'Proposed Supervisor Title',
        'proposal_summary' => 'Ringkasan proposal dengan usulan dosen.',
        'proposal_file' => UploadedFile::fake()->create('proposal-usulan.pdf', 700, 'application/pdf'),
    ], $overrides);
}

test('mahasiswa can submit a title with proposed supervisors from own program studi', function () {
    Storage::fake('public');

    $prodi = ProgramStudi::factory()->create(['name' => 'Informatika']);
    $student = proposedSupervisorsStudent($prodi);
    $concentration = $student->mahasiswaProfile->concentration;
    $primary = proposedSupervisorsLecturer($prodi, $concentration);
    $secondary = proposedSupervisorsLecturer($prodi, $concentration);

    $this->actingAs($student)
        ->post('/mahasiswa/tugas-akhir', proposedSupervisorsPayload([
            'proposed_primary_lecturer_user_id' => $primary->id,
            'proposed_secondary_lecturer_user_id' => $secondary->id,
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $title = ThesisProjectTitle::query()->firstOrFail();

    expect($title->proposed_primary_lecturer_user_id)->toBe($primary->id)
        ->and($title->proposed_secondary_lecturer_user_id)->toBe($secondary->id);
});

test('mahasiswa can submit a title without proposed supervisors', function () {
    Storage::fake('public');

    $prodi = ProgramStudi::factory()->create();
    $student = proposedSupervisorsStudent($prodi);

    $this->actingAs($student)
        ->post('/mahasiswa/tugas-akhir', proposedSupervisorsPayload())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $title = ThesisProjectTitle::query()->firstOrFail();

    expect($title->proposed_primary_lecturer_user_id)->toBeNull()
        ->and($title->proposed_secondary_lecturer_user_id)->toBeNull();
});

test('proposed supervisor from another program studi is rejected', function () {
    Storage::fake('public');

    $prodi = ProgramStudi::factory()->create();
    $otherProdi = ProgramStudi::factory()->create();
    $student = proposedSupervisorsStudent($prodi);
    $outsider = proposedSupervisorsLecturer($otherProdi, 'Lintas Prodi');

    $this->actingAs($student)
        ->post('/mahasiswa/tugas-akhir', proposedSupervisorsPayload([
            'proposed_primary_lecturer_user_id' => $outsider->id,
        ]))
        ->assertSessionHasErrors('proposed_primary_lecturer_user_id');

    expect(ThesisProjectTitle::query()->count())->toBe(0);
});

test('the same lecturer cannot be proposed as both supervisors', function () {
    Storage::fake('public');

    $prodi = ProgramStudi::factory()->create();
    $student = proposedSupervisorsStudent($prodi);
    $lecturer = proposedSupervisorsLecturer($prodi, $student->mahasiswaProfile->concentration);

    $this->actingAs($student)
        ->post('/mahasiswa/tugas-akhir', proposedSupervisorsPayload([
            'proposed_primary_lecturer_user_id' => $lecturer->id,
            'proposed_secondary_lecturer_user_id' => $lecturer->id,
        ]))
        ->assertSessionHasErrors('proposed_secondary_lecturer_user_id');

    expect(ThesisProjectTitle::query()->count())->toBe(0);
});

test('saved proposed supervisors are prefilled on the student submission', function () {
    Storage::fake('public');

    $prodi = ProgramStudi::factory()->create();
    $student = proposedSupervisorsStudent($prodi);
    $primary = proposedSupervisorsLecturer($prodi, $student->mahasiswaProfile->concentration);

    $this->actingAs($student)
        ->post('/mahasiswa/tugas-akhir', proposedSupervisorsPayload([
            'proposed_primary_lecturer_user_id' => $primary->id,
        ]))
        ->assertRedirect();

    $this->actingAs($student)
        ->get('/mahasiswa/tugas-akhir')
        ->assertInertia(fn (Assert $page) => $page
            ->where('submission.proposed_primary_lecturer_user_id', $primary->id)
            ->where('submission.proposed_secondary_lecturer_user_id', null));
});
