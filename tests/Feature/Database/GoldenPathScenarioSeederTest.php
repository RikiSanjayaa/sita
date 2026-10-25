<?php

use App\Models\MahasiswaProfile;
use App\Models\ThesisDocument;
use App\Models\ThesisProject;
use App\Models\User;
use Database\Seeders\GoldenPathScenarioSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Storage;

it('seeds the golden path student without a thesis project and resets it on re-run', function () {
    $this->seed(UserSeeder::class);
    $this->seed(GoldenPathScenarioSeeder::class);

    $student = User::query()->where('email', GoldenPathScenarioSeeder::EMAIL)->firstOrFail();

    expect(ThesisProject::query()->where('student_user_id', $student->id)->exists())->toBeFalse();

    $otherStudent = User::factory()->asMahasiswa()->create();
    $otherProject = ThesisProject::factory()->create([
        'student_user_id' => $otherStudent->id,
        'program_studi_id' => $student->mahasiswaProfile->program_studi_id,
    ]);

    ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $student->mahasiswaProfile->program_studi_id,
        'phase' => 'research',
        'state' => 'active',
        'started_at' => now(),
        'created_by' => $student->id,
    ]);

    $this->seed(GoldenPathScenarioSeeder::class);

    expect(User::query()->where('email', GoldenPathScenarioSeeder::EMAIL)->count())->toBe(1)
        ->and(ThesisProject::query()->where('student_user_id', $student->id)->exists())->toBeFalse();
    expect($otherProject->fresh())->not->toBeNull();
});

it('clears owned academic data and files without touching another student on :dataset', function (bool $restart) {
    Storage::fake('public');
    $this->seed(UserSeeder::class);
    $this->seed(GoldenPathScenarioSeeder::class);

    $student = User::query()->where('email', GoldenPathScenarioSeeder::EMAIL)->firstOrFail();
    $prodiId = $student->mahasiswaProfile->program_studi_id;

    $other = User::factory()->asMahasiswa()->create();
    $otherProject = ThesisProject::factory()->create(['student_user_id' => $other->id, 'program_studi_id' => $prodiId]);
    Storage::disk('public')->put('thesis/other.pdf', 'other');

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodiId,
        'phase' => 'sempro',
        'state' => 'active',
        'started_at' => now(),
        'created_by' => $student->id,
    ]);
    Storage::disk('public')->put('thesis/golden.pdf', 'golden');
    ThesisDocument::query()->create([
        'project_id' => $project->id,
        'uploaded_by_user_id' => $student->id,
        'kind' => 'proposal',
        'version_no' => 1,
        'status' => 'active',
        'title' => 'Proposal',
        'storage_disk' => 'public',
        'storage_path' => 'thesis/golden.pdf',
        'stored_file_name' => 'golden.pdf',
        'file_name' => 'golden.pdf',
        'mime_type' => 'application/pdf',
        'file_size_kb' => 1,
        'uploaded_at' => now(),
    ]);

    if ($restart) {
        $this->seed(GoldenPathScenarioSeeder::class);
    } else {
        GoldenPathScenarioSeeder::cleanup();
        GoldenPathScenarioSeeder::cleanup();
        expect(MahasiswaProfile::query()->where('user_id', $student->id)->exists())->toBeFalse();
    }

    expect(User::query()->where('email', GoldenPathScenarioSeeder::EMAIL)->exists())->toBe($restart)
        ->and(ThesisProject::query()->whereKey($project->id)->exists())->toBeFalse()
        ->and($otherProject->fresh())->not->toBeNull();
    Storage::disk('public')->assertMissing('thesis/golden.pdf');
    Storage::disk('public')->assertExists('thesis/other.pdf');
})->with([
    'cleanup after a rehearsal' => [false],
    'restart after an interrupted rehearsal' => [true],
]);
