<?php

use App\Enums\AppRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\AdminProfile;
use App\Models\DosenProfile;
use App\Models\ExpertiseField;
use App\Models\ProgramStudi;
use App\Models\User;
use App\Services\KaprodiPortalService;
use App\Services\UserProfilePresenter;
use App\Services\UserProvisioningService;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('super admin can create a student with a degree available in the selected program', function (): void {
    $superAdmin = User::factory()->asSuperAdmin()->create();
    $programStudi = ProgramStudi::factory()->create([
        'degree_levels' => ['d3', 's1'],
    ]);

    $this->actingAs($superAdmin);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'role' => AppRole::Mahasiswa->value,
            'name' => 'Mahasiswa D3',
            'email' => 'mahasiswa-d3@sita.test',
            'password' => 'password123',
            'prodi' => $programStudi->id,
            'degree_level' => 'd3',
            'concentration' => 'Umum',
            'nim' => '2300000001',
            'angkatan' => 2023,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $student = User::query()->where('email', 'mahasiswa-d3@sita.test')->firstOrFail();

    expect($student->mahasiswaProfile?->degree_level)->toBe('d3');
});

test('student degree is rejected when it is unavailable in the selected program', function (): void {
    $superAdmin = User::factory()->asSuperAdmin()->create();
    $programStudi = ProgramStudi::factory()->create([
        'degree_levels' => ['d3'],
    ]);
    $student = User::factory()->asMahasiswa()->create();

    $this->actingAs($superAdmin);

    expect(fn() => app(UserProvisioningService::class)->syncRoleAndProfiles($student, [
        'role' => AppRole::Mahasiswa->value,
        'prodi' => $programStudi->id,
        'degree_level' => 's1',
        'concentration' => 'Umum',
        'nim' => '2300000002',
        'angkatan' => 2023,
    ]))->toThrow(ValidationException::class, 'Jenjang wajib dipilih');
});

test('admin can assign multiple expertise fields to a lecturer they manage', function (): void {
    $programStudi = ProgramStudi::factory()->create();
    $admin = User::factory()->asAdmin()->create();
    AdminProfile::query()->create([
        'user_id' => $admin->id,
        'program_studi_id' => $programStudi->id,
    ]);
    $lecturer = User::factory()->asDosen()->create();
    DosenProfile::factory()->create([
        'user_id' => $lecturer->id,
        'program_studi_id' => $programStudi->id,
        'concentration' => 'Umum',
    ]);
    $fields = ExpertiseField::factory()->count(2)->create();

    $this->actingAs($admin);

    Livewire::test(EditUser::class, ['record' => $lecturer->getKey()])
        ->fillForm([
            'expertise_field_ids' => $fields->modelKeys(),
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($lecturer->expertiseFields()->pluck('expertise_fields.id')->all())
        ->toEqualCanonicalizing($fields->modelKeys());

    foreach ($fields as $field) {
        $this->assertDatabaseHas('expertise_field_user', [
            'expertise_field_id' => $field->id,
            'user_id' => $lecturer->id,
            'assigned_by_user_id' => $admin->id,
        ]);
    }
});

test('profile presenter exposes degree and expertise badges', function (): void {
    $programStudi = ProgramStudi::factory()->create(['degree_levels' => ['s1']]);
    $student = User::factory()->asMahasiswa()->create();
    $student->mahasiswaProfile()->create([
        'nim' => '2300000003',
        'program_studi_id' => $programStudi->id,
        'degree_level' => 's1',
        'concentration' => 'Umum',
        'angkatan' => 2023,
        'is_active' => true,
    ]);

    $lecturer = User::factory()->asDosen()->create();
    DosenProfile::factory()->create([
        'user_id' => $lecturer->id,
        'program_studi_id' => $programStudi->id,
    ]);
    $field = ExpertiseField::factory()->create(['name' => 'Sains Data']);
    $lecturer->expertiseFields()->attach($field);

    $presenter = app(UserProfilePresenter::class);
    $portal = app(KaprodiPortalService::class);
    $studentIndex = $portal->studentIndex($programStudi);
    $lecturerIndex = $portal->lecturers($programStudi);

    expect($presenter->summary($student)['degreeLevel'])->toBe('S1')
        ->and($presenter->summary($lecturer)['expertiseFields'])->toBe(['Sains Data'])
        ->and(collect($presenter->detail($student)['meta'])->firstWhere('label', 'Jenjang')['value'])->toBe('S1')
        ->and(collect($presenter->detail($lecturer)['meta'])->firstWhere('label', 'Bidang Keilmuan')['value'])->toBe('Sains Data')
        ->and($studentIndex['students'][0]['degreeLevel'])->toBe('S1')
        ->and($lecturerIndex['lecturers'][0]['expertiseFields'])->toBe(['Sains Data']);
});
