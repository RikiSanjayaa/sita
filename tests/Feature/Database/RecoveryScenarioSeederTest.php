<?php

use App\Models\ThesisDocument;
use App\Models\ThesisProject;
use App\Models\User;
use Database\Seeders\GoldenPathScenarioSeeder;
use Database\Seeders\RecoveryScenarioSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Storage;

it('resets a research recovery without deleting another student or their files', function (): void {
    Storage::fake('public');
    $this->seed(UserSeeder::class);
    app(RecoveryScenarioSeeder::class)->run('research');

    $student = User::query()->where('email', GoldenPathScenarioSeeder::EMAIL)->firstOrFail();
    $project = ThesisProject::query()->where('student_user_id', $student->id)->firstOrFail();
    $proposal = ThesisDocument::query()->where('project_id', $project->id)->where('kind', 'proposal')->firstOrFail();
    $other = User::factory()->asMahasiswa()->create(['name' => 'Mahasiswa lain']);
    $otherProject = ThesisProject::factory()->create(['student_user_id' => $other->id]);
    Storage::disk('public')->put('recovery-other.pdf', 'another student file');

    app(RecoveryScenarioSeeder::class)->run('fresh');

    expect(ThesisProject::query()->whereKey($project->id)->exists())->toBeFalse()
        ->and(User::query()->where('email', GoldenPathScenarioSeeder::EMAIL)->count())->toBe(1)
        ->and(ThesisProject::query()->whereHas('student', fn ($query) => $query->where('email', GoldenPathScenarioSeeder::EMAIL))->exists())->toBeFalse()
        ->and($otherProject->fresh()->student->name)->toBe('Mahasiswa lain');
    Storage::disk('public')->assertMissing($proposal->storage_path);
    Storage::disk('public')->assertExists('recovery-other.pdf');
});
