<?php

namespace App\Services;

use App\Models\ThesisDefense;
use App\Models\User;
use App\Support\SidangSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SidangPaperService
{
    public const ROLES = ['examiner_1' => 'Penguji 1', 'examiner_2' => 'Penguji 2', 'supervisor' => 'Pembimbing penilai'];

    public function record(ThesisDefense $defense, User $actor, array $data): void
    {
        DB::transaction(function () use ($defense, $actor, $data): void {
            $defense = ThesisDefense::query()->lockForUpdate()->findOrFail($defense->id);
            $defense->load('examiners.lecturer.dosenProfile', 'project.programStudi');
            $this->ensure($defense->type === 'sidang' && in_array($defense->status, ['scheduled', 'awaiting_finalization'], true)
                && $defense->sidang_paper === null && $defense->project->state === 'active',
                'Sidang ini sudah diproses atau tidak lagi terjadwal.');

            $moderator = $data['moderator_user_id'] ?? null;
            if ($moderator !== null) {
                $this->ensure(User::query()->whereKey($moderator)->whereHas('dosenProfile', function ($query) use ($defense): void {
                    $query->where('is_active', true)->where(function ($query) use ($defense): void {
                        $query->where('program_studi_id', $defense->project->program_studi_id)
                            ->orWhereIn('user_id', $defense->examiners->pluck('lecturer_user_id'));
                    });
                })->exists(), 'Moderator harus dosen aktif pada prodi ini atau anggota panel sidang.');
            }
            $defense->forceFill([
                'moderator_user_id' => $moderator,
                'sidang_sk' => $data['sidang_sk'] ?? null,
            ]);
            if (! isset($data['scores'])) {
                $defense->save();

                return;
            }

            $settings = SidangSettings::forProgram($defense->project->programStudi);
            $scores = [];
            $weighted = 0.0;
            foreach (self::ROLES as $role => $label) {
                $entry = $data['scores'][$role] ?? [];
                $panel = $defense->examiners->find((int) ($entry['panel_id'] ?? 0));
                $this->ensure($panel && ($role === 'supervisor' ? $panel->isSupervisorRole() : $panel->role === 'examiner'),
                    'Pilih anggota panel yang sesuai untuk '.$label.'.');
                $value = (float) $entry['value'];
                $this->ensure($panel->score === null || (float) $panel->score === $value,
                    'Nilai '.$label.' sudah tercatat. Gunakan nilai yang tercatat atau selesaikan koreksinya terlebih dahulu.');
                $panel->forceFill(['score' => $value])->save();
                $weight = (int) $settings[$role];
                $weighted += $value * $weight;
                $scores[$role] = [
                    'panel_id' => $panel->id, 'name' => $panel->lecturer?->name,
                    'nidn' => $panel->lecturer?->dosenProfile?->sidang_nidn,
                    'value' => $value, 'weight' => $weight,
                ];
            }
            $this->ensure(count(array_unique(array_column($scores, 'panel_id'))) === 3, 'Ketiga penilai harus berbeda.');
            $score = round($weighted / 100, 4);
            $defense->forceFill([
                'sidang_paper' => [
                    'recorded_by' => $actor->id, 'recorded_at' => now()->toIso8601String(),
                    'scores' => $scores, 'value' => $score, 'grade' => SidangSettings::grade($score, $settings),
                    'settings' => $settings,
                ],
                'status' => 'awaiting_finalization',
            ])->save();
        });
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['paper' => $message]);
        }
    }
}
