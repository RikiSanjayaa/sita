<?php

use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisProject;
use App\Services\SidangScoreService;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

uses(TestCase::class);

function scoreSummaryDefense(array $values): ThesisDefense
{
    $program = new ProgramStudi(['sidang_settings' => []]);
    $project = (new ThesisProject(['state' => 'active']))->setRelation('programStudi', $program);
    $members = [];
    foreach (['examiner', 'examiner', 'primary_supervisor'] as $index => $role) {
        $members[] = (new ThesisDefenseExaminer(['id' => $index + 1, 'role' => $role, 'order_no' => $index, 'score' => $values[$index]]))
            ->forceFill(['id' => $index + 1])->setRelation('lecturer', null);
    }

    return (new ThesisDefense(['type' => 'sidang', 'status' => 'scheduled']))
        ->setRelation('project', $project)->setRelation('examiners', new Collection($members));
}

it('weights panel scores including zeros and decimals', function (array $values, float $expected, string $grade): void {
    $summary = (new SidangScoreService)->summary(scoreSummaryDefense($values));
    expect($summary['value'])->toBe($expected)->and($summary['grade'])->toBe($grade);
})->with([
    'zero' => [[0, 0, 0], 0.0, 'E'],
    'decimal' => [[88.5, 70, 80], 79.55, 'B+'],
    'hundred' => [[100, 100, 100], 100.0, 'A'],
    'boundary' => [[81, 81, 81], 81.0, 'A'],
]);

it('requires all three selected scores and distinct valid roles', function (): void {
    $defense = scoreSummaryDefense([80, null, 70]);
    $scores = new SidangScoreService;
    expect($scores->summary($defense))->toBeNull();
    $defense->examiners[1]->score = 75;
    $defense->forceFill(['sidang_paper' => ['panel_ids' => ['examiner_1' => 1, 'examiner_2' => 1, 'supervisor' => 3]]]);
    expect($scores->summary($defense))->toBeNull();
    $defense->forceFill(['sidang_paper' => ['panel_ids' => ['examiner_1' => 1, 'examiner_2' => 2, 'supervisor' => 99]]]);
    expect($scores->summary($defense))->toBeNull();
});

it('does not reinterpret a supervisor as the second examiner for a legacy panel', function (): void {
    $defense = scoreSummaryDefense([80, 90, 70]);
    $defense->examiners[1]->role = 'secondary_supervisor';
    expect((new SidangScoreService)->summary($defense))->toBeNull();
});

it('reads current panel scores even when a prefinalization legacy JSON contains different scores', function (): void {
    $defense = scoreSummaryDefense([88.5, 70, 80]);
    $defense->forceFill(['sidang_paper' => ['scores' => [
        'examiner_1' => ['panel_id' => 1, 'value' => 1],
        'examiner_2' => ['panel_id' => 2, 'value' => 1],
        'supervisor' => ['panel_id' => 3, 'value' => 1],
    ], 'value' => 1, 'grade' => 'E']]);
    expect((new SidangScoreService)->summary($defense)['value'])->toBe(79.55);
});
