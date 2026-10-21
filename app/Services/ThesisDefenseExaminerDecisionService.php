<?php

namespace App\Services;

use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\User;
use App\Support\AcademicTerminology;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ThesisDefenseExaminerDecisionService
{
    public function __construct(
        private readonly RealtimeNotificationService $realtimeNotificationService,
    ) {}

    /**
     * @param  array{decision:string,score?:float|int|null,decision_notes:?string,revision_notes:?string}  $data
     */
    public function submit(User $lecturer, ThesisDefense $defense, array $data): ThesisDefenseExaminer
    {
        $examiner = ThesisDefenseExaminer::query()
            ->where('defense_id', $defense->id)
            ->where('lecturer_user_id', $lecturer->id)
            ->first();

        if (! $examiner instanceof ThesisDefenseExaminer) {
            throw new RuntimeException('Anda bukan penguji untuk ujian ini.');
        }

        if ($defense->status !== 'scheduled') {
            throw new RuntimeException('Ujian tidak dalam status yang dapat dinilai.');
        }

        if ($examiner->decision !== 'pending') {
            throw new RuntimeException('Anda sudah mengirim keputusan untuk ujian ini.');
        }

        DB::transaction(function () use ($data, $defense, $examiner, $lecturer): void {
            $examiner->forceFill([
                'decision' => $data['decision'],
                'score' => $data['score'] ?? null,
                'notes' => $data['decision_notes'] ?? null,
                'revision_notes' => $data['decision'] === 'pass_with_revision'
                    ? $data['revision_notes']
                    : null,
                'decided_at' => now(),
            ])->save();

            $this->recalculateDefenseOutcome($defense->fresh('examiners'));

            $student = $defense->project?->student;
            $project = $defense->project;
            $terms = $project instanceof \App\Models\ThesisProject ? AcademicTerminology::forProject($project) : AcademicTerminology::neutral();

            if ($student instanceof User) {
                $this->realtimeNotificationService->notifyUser($student, 'statusTugasAkhir', [
                    'title' => sprintf(
                        'Nilai %s dari penguji tersedia',
                        AcademicTerminology::defenseLabel($terms, $defense->type, short: true),
                    ),
                    'description' => sprintf(
                        '%s sudah mengirim nilai dan keputusan untuk %s Anda.',
                        $lecturer->name,
                        AcademicTerminology::defenseLabel($terms, $defense->type, short: true),
                    ),
                    'url' => '/tugas-akhir',
                    'icon' => 'check-circle',
                    'createdAt' => now()->toIso8601String(),
                ]);
            }
        });

        return $examiner->fresh();
    }

    private function recalculateDefenseOutcome(ThesisDefense $defense): void
    {
        $decisions = $defense->examiners->pluck('decision');

        if ($decisions->isEmpty() || $decisions->contains('pending')) {
            $defense->forceFill([
                'status' => 'scheduled',
                'result' => 'pending',
            ])->save();

            return;
        }

        $defense->forceFill([
            'status' => 'awaiting_finalization',
            'result' => 'pending',
        ])->save();
    }
}
