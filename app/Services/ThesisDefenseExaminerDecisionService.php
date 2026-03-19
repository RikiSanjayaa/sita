<?php

namespace App\Services;

use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
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

        DB::transaction(function () use ($data, $defense, $examiner): void {
            $examiner->forceFill([
                'decision' => $data['decision'],
                'score' => $data['score'],
                'notes' => $data['decision_notes'],
                'decided_at' => now(),
            ])->save();

            $this->recalculateDefenseOutcome($defense->fresh('examiners'));
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
