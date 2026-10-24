<?php

use App\Models\ThesisProject;
use App\Models\User;
use Database\Seeders\PlaywrightScenarioSeeder;
use Database\Seeders\ThesisWorkflowSeeder;
use Database\Seeders\UserSeeder;

function playwrightProject(string $email): ThesisProject
{
    return ThesisProject::query()
        ->whereHas('student', fn ($query) => $query->where('email', $email))
        ->with(['activeSupervisorAssignments', 'documents', 'semproDefenses.examiners', 'sidangDefenses.examiners'])
        ->firstOrFail();
}

it('seeds one student per Playwright scenario and can be re-run to reset them', function () {
    $this->seed([UserSeeder::class, ThesisWorkflowSeeder::class]);

    $this->seed(PlaywrightScenarioSeeder::class);
    $this->seed(PlaywrightScenarioSeeder::class);

    $twoSupervisors = playwrightProject('e2e-pembimbing@sita.test');
    expect($twoSupervisors->phase)->toBe('research')
        ->and($twoSupervisors->activeSupervisorAssignments)->toHaveCount(2);

    $sempro = playwrightProject('e2e-sempro@sita.test')->semproDefenses->first();
    expect($sempro->status)->toBe('scheduled')
        ->and($sempro->examiners)->toHaveCount(2);

    $sinopsis = playwrightProject('e2e-sinopsis@sita.test');
    expect($sinopsis->student->mahasiswaProfile->is_obe_curriculum)->toBeTrue()
        ->and($sinopsis->semproDefenses->first()->examiners)->toHaveCount(2)
        ->and($sempro->project->student->mahasiswaProfile->is_obe_curriculum)->toBeFalse();

    $unscheduled = playwrightProject('e2e-jadwalsempro@sita.test');
    expect($unscheduled->semproDefenses)->toBeEmpty();

    $withoutSupervisors = playwrightProject('e2e-pembimbingbaru@sita.test');
    expect($withoutSupervisors->activeSupervisorAssignments)->toBeEmpty()
        ->and($withoutSupervisors->semproDefenses->first()->result)->toBe('pass');

    $sidang = playwrightProject('e2e-sidang@sita.test')->sidangDefenses->first();
    expect($sidang->status)->toBe('scheduled')
        ->and($sidang->examiners)->toHaveCount(3);

    expect(playwrightProject('e2e-masa-aktif-habis@sita.test')->isMentorshipSuspended())->toBeTrue();

    $inGrace = playwrightProject('e2e-lulus-tenggang@sita.test');
    $pastGrace = playwrightProject('e2e-lulus-lama@sita.test');
    expect($inGrace->state)->toBe('completed')
        ->and(ThesisProject::query()->dueForArchive()->pluck('id')->all())->toContain($pastGrace->id)->not->toContain($inGrace->id)
        ->and($pastGrace->documents->where('kind', 'final_manuscript'))->toHaveCount(1);

    expect(ThesisProject::query()->whereIn('student_user_id', User::query()->where('email', 'like', 'e2e-%@sita.test')->pluck('id'))->count())->toBe(9);
});
