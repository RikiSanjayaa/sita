<?php

use App\Models\KaprodiAssignment;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisProject;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;

dataset('public title cases', [
    'sempro passed' => ['active', ['status' => 'completed', 'result' => 'pass'], true],
    'sempro passed with revision' => ['active', ['status' => 'completed', 'result' => 'pass_with_revision'], true],
    'project completed without sempro record' => ['completed', null, true],
    'sempro failed' => ['active', ['status' => 'completed', 'result' => 'fail'], false],
    'sempro scheduled' => ['active', ['status' => 'scheduled', 'result' => 'pending'], false],
    'no sempro' => ['active', null, false],
    'cancelled after sempro passed' => ['cancelled', ['status' => 'completed', 'result' => 'pass'], false],
]);

/**
 * @param  array<string, string>|null  $semproAttributes
 * @return array{student: User, programStudi: ProgramStudi, project: ThesisProject, sempro: ThesisDefense|null}
 */
function publicTitleVisibilityFixture(string $state, ?array $semproAttributes, string $titleId = 'Judul Uji Visibilitas'): array
{
    $programStudi = ProgramStudi::factory()->create();
    $student = User::factory()->asMahasiswa()->create();

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $programStudi->id,
        'is_active' => true,
    ]);

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $programStudi->id,
        'phase' => 'sempro',
        'state' => $state,
        'started_at' => now()->subMonths(2),
    ]);

    ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => $titleId,
        'status' => 'approved',
    ]);

    $sempro = null;

    if ($semproAttributes !== null) {
        $sempro = ThesisDefense::query()->create([
            'project_id' => $project->id,
            'type' => 'sempro',
            'attempt_no' => 1,
            'scheduled_for' => now()->subMonth(),
            ...$semproAttributes,
        ]);
    }

    return [
        'student' => $student,
        'programStudi' => $programStudi,
        'project' => $project,
        'sempro' => $sempro,
    ];
}

test('unrelated viewers see the thesis title only when the public rule holds', function (string $state, ?array $semproAttributes, bool $public): void {
    $fixture = publicTitleVisibilityFixture($state, $semproAttributes);

    foreach ([User::factory()->asMahasiswa()->create(), User::factory()->asDosen()->create()] as $viewer) {
        $page = $this->actingAs($viewer)
            ->get(route('users.profile.show', ['user' => $fixture['student']->id]))
            ->assertOk()
            ->viewData('page');

        expect(data_get($page, 'props.profile.thesis.title'))->toBe($public ? 'Judul Uji Visibilitas' : null)
            ->and(data_get($page, 'props.profile.thesis.titleVisible'))->toBe($public);
    }
})->with('public title cases');

test('people tied to the project always see the full thesis title', function (string $relation): void {
    $fixture = publicTitleVisibilityFixture('active', ['status' => 'completed', 'result' => 'fail']);
    $project = $fixture['project'];
    $sempro = $fixture['sempro'];

    $viewer = match ($relation) {
        'student' => $fixture['student'],
        'supervisor' => User::factory()->asDosen()->create(),
        'examiner' => User::factory()->asDosen()->create(),
        'kaprodi' => User::factory()->asKaprodi()->create(),
        'admin' => User::factory()->asAdmin()->create(),
    };

    match ($relation) {
        'supervisor' => ThesisSupervisorAssignment::query()->create([
            'project_id' => $project->id,
            'lecturer_user_id' => $viewer->id,
            'role' => 'primary',
        ]),
        'examiner' => ThesisDefenseExaminer::query()->create([
            'defense_id' => $sempro->id,
            'lecturer_user_id' => $viewer->id,
            'role' => 'examiner',
            'order_no' => 1,
        ]),
        'kaprodi' => KaprodiAssignment::factory()->create([
            'program_studi_id' => $fixture['programStudi']->id,
            'user_id' => $viewer->id,
            'is_primary' => true,
        ]),
        default => null,
    };

    $page = $this->actingAs($viewer)
        ->get(route('users.profile.show', ['user' => $fixture['student']->id]))
        ->assertOk()
        ->viewData('page');

    expect(data_get($page, 'props.profile.thesis.title'))->toBe('Judul Uji Visibilitas')
        ->and(data_get($page, 'props.profile.thesis.titleVisible'))->toBeTrue();
})->with(['student', 'supervisor', 'examiner', 'kaprodi', 'admin']);

test('topik lists only projects that pass the public title rule', function (): void {
    publicTitleVisibilityFixture('active', ['status' => 'completed', 'result' => 'pass'], 'Judul Lulus Sempro');
    publicTitleVisibilityFixture('active', ['status' => 'completed', 'result' => 'fail'], 'Judul Gagal Sempro');
    publicTitleVisibilityFixture('completed', null, 'Judul Selesai');

    $page = $this->get('/topik')
        ->assertOk()
        ->viewData('page');

    $items = collect(data_get($page, 'props.semproTitles'));

    expect($items->pluck('title')->sort()->values()->all())
        ->toBe(['Judul Lulus Sempro', 'Judul Selesai'])
        ->and($items->firstWhere('title', 'Judul Lulus Sempro'))
        ->toMatchArray(['state' => 'active', 'stateLabel' => 'Sedang berjalan'])
        ->and($items->firstWhere('title', 'Judul Selesai'))
        ->toMatchArray(['state' => 'completed', 'stateLabel' => 'Selesai']);
});
