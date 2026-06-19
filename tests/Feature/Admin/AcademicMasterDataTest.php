<?php

use App\Filament\Resources\ExpertiseFields\ExpertiseFieldResource;
use App\Filament\Resources\ExpertiseFields\Pages\CreateExpertiseField;
use App\Filament\Resources\Faculties\FacultyResource;
use App\Filament\Resources\Faculties\Pages\CreateFaculty;
use App\Models\ExpertiseField;
use App\Models\Faculty;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\User;
use Livewire\Livewire;

test('only super admin can access academic master data resources', function (): void {
    $admin = User::factory()->asAdmin()->create();

    $this->actingAs($admin)
        ->get(FacultyResource::getUrl('index'))
        ->assertForbidden();

    $this->get(ExpertiseFieldResource::getUrl('index'))
        ->assertForbidden();

    $superAdmin = User::factory()->asSuperAdmin()->create();

    $this->actingAs($superAdmin)
        ->get(FacultyResource::getUrl('index'))
        ->assertOk();

    $this->get(ExpertiseFieldResource::getUrl('index'))
        ->assertOk();
});

test('super admin can create faculties and expertise fields', function (): void {
    $superAdmin = User::factory()->asSuperAdmin()->create();

    $this->actingAs($superAdmin);

    Livewire::test(CreateFaculty::class)
        ->fillForm([
            'name' => 'Fakultas Teknologi Informasi',
            'code' => 'fti',
            'slug' => 'fakultas-teknologi-informasi',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    Livewire::test(CreateExpertiseField::class)
        ->fillForm([
            'name' => 'Kecerdasan Buatan',
            'slug' => 'kecerdasan-buatan',
            'description' => 'Sistem cerdas dan pembelajaran mesin.',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('faculties', [
        'name' => 'Fakultas Teknologi Informasi',
        'code' => 'FTI',
    ]);

    $this->assertDatabaseHas('expertise_fields', [
        'name' => 'Kecerdasan Buatan',
        'slug' => 'kecerdasan-buatan',
    ]);
});

test('program studi belongs to a faculty and stores its available degree levels', function (): void {
    $faculty = Faculty::factory()->create();
    $programStudi = ProgramStudi::factory()->create([
        'faculty_id' => $faculty->id,
        'degree_levels' => ['d3', 's1', 's2', 'invalid', 's1'],
    ]);

    expect($programStudi->faculty->is($faculty))->toBeTrue()
        ->and($programStudi->degreeLevelList())->toBe(['d3', 's1', 's2'])
        ->and($programStudi->degreeLevelOptions())->toBe([
            'd3' => 'D3',
            's1' => 'S1',
            's2' => 'S2',
        ]);
});

test('legacy academic data receives safe defaults', function (): void {
    $programStudi = ProgramStudi::query()->create([
        'name' => 'Program Studi Lama',
        'slug' => 'program-studi-lama',
        'concentrations' => ['Umum'],
    ]);

    $profile = MahasiswaProfile::factory()->create([
        'program_studi_id' => $programStudi->id,
    ]);

    expect($programStudi->faculty->is_placeholder)->toBeTrue()
        ->and($programStudi->degreeLevelList())->toBe(['s1'])
        ->and($profile->degree_level)->toBe('s1');
});

test('expertise fields can be assigned to more than one lecturer with assignment metadata', function (): void {
    $assignedBy = User::factory()->asSuperAdmin()->create();
    $lecturerA = User::factory()->asDosen()->create();
    $lecturerB = User::factory()->asDosen()->create();
    $field = ExpertiseField::factory()->create();

    $field->lecturers()->attach([
        $lecturerA->id => ['assigned_by_user_id' => $assignedBy->id],
        $lecturerB->id => ['assigned_by_user_id' => $assignedBy->id],
    ]);

    expect($field->lecturers()->count())->toBe(2)
        ->and($lecturerA->expertiseFields()->firstOrFail()->pivot->assigned_by_user_id)
        ->toBe($assignedBy->id);
});

test('academic master data in use cannot be deleted', function (): void {
    $faculty = Faculty::factory()->create();
    ProgramStudi::factory()->create(['faculty_id' => $faculty->id]);

    expect(fn() => $faculty->delete())
        ->toThrow(LogicException::class, 'masih memiliki program studi');

    $lecturer = User::factory()->asDosen()->create();
    $field = ExpertiseField::factory()->create();
    $field->lecturers()->attach($lecturer);

    expect(fn() => $field->delete())
        ->toThrow(LogicException::class, 'masih digunakan dosen');
});
