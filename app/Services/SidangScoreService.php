<?php

namespace App\Services;

use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisDefenseScoreChange;
use App\Models\User;
use App\Support\SidangSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class SidangScoreService
{
    public function editable(ThesisDefense $defense): bool
    {
        return $defense->type === 'sidang'
            && in_array($defense->status, ['scheduled', 'awaiting_finalization'], true)
            && $defense->project->state === 'active';
    }

    /** @return array<string, int>|null */
    public function panelIds(ThesisDefense $defense): ?array
    {
        $defense->loadMissing('examiners');
        $paper = $defense->sidang_paper ?? [];
        $ids = $paper['panel_ids'] ?? null;
        if ($ids === null && isset($paper['scores'])) {
            if (! is_array($paper['scores']) || count($paper['scores']) !== 3
                || collect($paper['scores'])->contains(fn ($entry): bool => ! is_array($entry) || ! isset($entry['panel_id']))) {
                return null;
            }
            $ids = array_map(fn (array $entry): int => (int) $entry['panel_id'], $paper['scores']);
        }
        if ($ids === null) {
            $panel = $defense->examiners->sortBy(fn (ThesisDefenseExaminer $member): array => [$member->order_no, $member->id]);
            $examiners = $panel->where('role', 'examiner')->values();
            $supervisor = $panel->first(fn (ThesisDefenseExaminer $member): bool => $member->isSupervisorRole());
            $ids = ['examiner_1' => $examiners->get(0)?->id, 'examiner_2' => $examiners->get(1)?->id, 'supervisor' => $supervisor?->id];
        }
        if (! is_array($ids) || count($ids) !== 3 || count(array_unique(array_values($ids))) !== 3) {
            return null;
        }
        foreach (SidangPaperService::ROLES as $role => $label) {
            $member = $defense->examiners->find((int) ($ids[$role] ?? 0));
            if ($member === null || ($role === 'supervisor' ? ! $member->isSupervisorRole() : $member->role !== 'examiner')) {
                return null;
            }
        }

        return array_map('intval', $ids);
    }

    /** @return array{panel_ids: array<string, int>, scores: array, settings: array, value: float, grade: string}|null */
    public function summary(ThesisDefense $defense): ?array
    {
        if ($defense->type !== 'sidang' || ($ids = $this->panelIds($defense)) === null) {
            return null;
        }
        $settings = $defense->status === 'completed'
            ? ($defense->sidang_paper['settings'] ?? SidangSettings::forProgram($defense->project->programStudi))
            : SidangSettings::forProgram($defense->project->programStudi);
        $scores = [];
        $weighted = 0.0;
        foreach ($ids as $role => $id) {
            $member = $defense->examiners->find($id);
            if ($member->score === null) {
                return null;
            }
            $value = (float) $member->score;
            $weight = (int) $settings[$role];
            $weighted += $value * $weight;
            $scores[$role] = ['panel_id' => $id, 'value' => $value, 'weight' => $weight,
                'name' => $member->lecturer?->name, 'nidn' => $member->lecturer?->dosenProfile?->sidang_nidn];
        }
        $value = round($weighted / 100, 4);
        // Existing final results retain their historical rounding and grading rules.
        if ($defense->status === 'completed' && isset($defense->sidang_paper['value'], $defense->sidang_paper['grade'])) {
            $value = (float) $defense->sidang_paper['value'];
            $grade = $defense->sidang_paper['grade'];
        } else {
            $grade = SidangSettings::grade($value, $settings);
        }

        return ['panel_ids' => $ids, 'scores' => $scores, 'settings' => $settings, 'value' => $value, 'grade' => $grade];
    }

    /** Caller holds the defense lock and transaction. */
    public function write(ThesisDefenseExaminer $member, User $actor, mixed $value, string $source, bool $verify = false): void
    {
        Validator::make(['score' => $value], ['score' => 'required|numeric|between:0,100|decimal:0,2'])->validate();
        $old = $member->score === null ? null : (float) $member->score;
        $value = (float) $value;
        if ($old !== $value) {
            ThesisDefenseScoreChange::query()->create([
                'examiner_id' => $member->id, 'actor_user_id' => $actor->id,
                'source' => $source, 'old_score' => $old, 'new_score' => $value,
            ]);
            $member->forceFill(['score' => $value, 'score_recorded_by' => $actor->id,
                'score_recorded_at' => now(), 'score_source' => $source,
                'paper_verified_by' => null, 'paper_verified_at' => null]);
        }
        if ($verify) {
            $member->forceFill(['paper_verified_by' => $actor->id, 'paper_verified_at' => now()]);
        }
        $member->save();
    }

    public function assertCurrent(ThesisDefenseExaminer $member, mixed $expected, string $field): void
    {
        $current = $member->score === null ? null : (float) $member->score;
        $expected = $expected === null || $expected === '' ? null : (float) $expected;
        if ($current !== $expected) {
            throw ValidationException::withMessages([$field => 'Nilai sudah berubah. Muat ulang halaman dan cocokkan kembali sebelum menyimpan.']);
        }
    }

    public function correct(User $lecturer, ThesisDefense $defense, array $data): void
    {
        DB::transaction(function () use ($lecturer, $defense, $data): void {
            $defense = ThesisDefense::query()->lockForUpdate()->findOrFail($defense->id);
            $member = $defense->examiners()->where('lecturer_user_id', $lecturer->id)->lockForUpdate()->first();
            abort_unless($lecturer->hasRole('dosen') && $member !== null, 403);
            if (! $this->editable($defense)) {
                throw ValidationException::withMessages(['score' => 'Nilai hanya dapat diubah sebelum sidang difinalisasi.']);
            }
            $this->assertCurrent($member, $data['expected_score'], 'score');
            $this->write($member, $lecturer, $data['score'], 'lecturer');
        });
    }

    /** Called under the same defense lock as finalization. */
    public function snapshot(ThesisDefense $defense): void
    {
        $summary = $this->summary($defense);
        if ($summary === null) {
            if (isset($defense->sidang_paper['recorded_at'])) {
                throw ValidationException::withMessages(['paper' => 'Lengkapi tiga penilai dan nilainya sebelum finalisasi sidang.']);
            }

            return;
        }
        $paper = $defense->sidang_paper ?? [];
        unset($paper['scores']);
        $defense->forceFill(['sidang_paper' => [...$paper,
            'panel_ids' => $summary['panel_ids'], 'value' => $summary['value'],
            'grade' => $summary['grade'], 'settings' => $summary['settings'],
            'finalized_at' => $defense->status === 'completed' ? $defense->decision_at?->toIso8601String() : now()->toIso8601String(),
            'snapshot_at' => now()->toIso8601String(),
        ]])->save();
    }

    /** @return array<int, array{defense_id: int, panel_id: ?int, problem: string}> */
    public function legacyIssues(ThesisDefense $defense): array
    {
        $entries = $defense->sidang_paper['scores'] ?? null;
        if ($entries === null) {
            return $defense->examiners->whereNotNull('score')->isNotEmpty() && $this->panelIds($defense) === null
                ? [['defense_id' => $defense->id, 'panel_id' => null, 'problem' => 'unmapped_panel']] : [];
        }
        if ($this->panelIds($defense) === null) {
            return [['defense_id' => $defense->id, 'panel_id' => null, 'problem' => 'unmapped_panel']];
        }
        $issues = [];
        foreach ($entries as $entry) {
            $member = $defense->examiners->find((int) $entry['panel_id']);
            $value = $entry['value'] ?? null;
            $problem = match (true) {
                ! is_numeric($value) || $value < 0 || $value > 100 => 'invalid_legacy_score',
                $member->score === null => 'missing_panel_score',
                (float) $member->score !== (float) $value => 'different_panel_score',
                default => null,
            };
            if ($problem !== null) {
                $issues[] = ['defense_id' => $defense->id, 'panel_id' => $member->id, 'problem' => $problem];
            }
        }

        return $issues;
    }

    /** Additive backfill. Existing panel scores, decisions and final results are preserved. */
    public function importLegacy(ThesisDefense $defense): void
    {
        DB::transaction(function () use ($defense): void {
            $defense = ThesisDefense::query()->lockForUpdate()->findOrFail($defense->id);
            $defense->load(['examiners' => fn ($query) => $query->orderBy('id')->lockForUpdate()]);
            $paper = $defense->sidang_paper ?? [];
            if (! isset($paper['scores']) || $this->panelIds($defense) === null) {
                return;
            }
            $actor = isset($paper['recorded_by']) ? User::query()->find($paper['recorded_by']) : null;
            $recordedAt = isset($paper['recorded_at']) && strtotime($paper['recorded_at']) !== false
                ? \Carbon\CarbonImmutable::parse($paper['recorded_at']) : null;
            foreach ($paper['scores'] as $entry) {
                $member = $defense->examiners->find((int) $entry['panel_id']);
                if ($member->score_recorded_at !== null) {
                    continue;
                }
                $value = $entry['value'] ?? null;
                if (Validator::make(['score' => $value], ['score' => 'required|numeric|between:0,100|decimal:0,2'])->fails()) {
                    continue;
                }
                if ($member->score === null) {
                    $member->forceFill(['score' => $value, 'score_source' => 'paper',
                        'score_recorded_by' => $actor?->id, 'score_recorded_at' => $recordedAt]);
                }
                if ((float) $member->score === (float) $value && $member->paper_verified_at === null) {
                    $member->forceFill(['paper_verified_by' => $actor?->id, 'paper_verified_at' => $recordedAt]);
                    if ($member->score_source === null && $member->decision === 'pending') {
                        // The snapshot proves the paper match, but not the original score author.
                        $member->forceFill(['score_source' => 'paper']);
                    }
                }
                $member->save();
            }
        });
    }
}
