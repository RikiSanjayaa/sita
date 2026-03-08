<?php

namespace App\Services;

use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ThesisDefenseExaminerDecisionService
{
    /**
     * @param  array{decision:string,score:float|int,decision_notes:?string,revision_notes:?string}  $data
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
                'score' => $data['score'],
                'notes' => $data['decision_notes'],
                'decided_at' => now(),
            ])->save();

            if ($data['decision'] === 'pass_with_revision') {
                ThesisRevision::query()->updateOrCreate(
                    [
                        'project_id' => $defense->project_id,
                        'defense_id' => $defense->id,
                        'requested_by_user_id' => $lecturer->id,
                        'status' => 'open',
                    ],
                    [
                        'notes' => $data['revision_notes'] ?? $data['decision_notes'] ?? 'Perlu revisi setelah penilaian penguji.',
                        'due_at' => now()->addDays(14),
                        'submitted_at' => null,
                        'resolved_at' => null,
                        'resolved_by_user_id' => null,
                        'resolution_notes' => null,
                    ],
                );
            }

            $this->recalculateDefenseOutcome($defense->fresh(['examiners', 'revisions']));
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

        if ($decisions->contains('fail')) {
            $defense->forceFill([
                'status' => 'completed',
                'result' => 'fail',
            ])->save();

            return;
        }

        if ($decisions->contains('pass_with_revision')) {
            $defense->forceFill([
                'status' => 'completed',
                'result' => 'pass_with_revision',
            ])->save();

            return;
        }

        if ($decisions->isNotEmpty() && $decisions->every(fn(string $decision): bool => $decision === 'pass')) {
            $defense->forceFill([
                'status' => 'completed',
                'result' => 'pass',
            ])->save();
        }
    }
}
