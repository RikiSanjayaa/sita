<?php

namespace App\Services;

use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\User;
use App\Policies\SidangPaperPolicy;
use App\Support\SidangSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SidangPaperService
{
    public const ROLES = ['examiner_1' => 'Penguji 1', 'examiner_2' => 'Penguji 2', 'supervisor' => 'Pembimbing penilai'];

    public const SK_STATUSES = ['awaiting_finalization', 'completed'];

    /** @param  Collection<int, ThesisProject>  $selection */
    public function assignSk(User $actor, Collection $selection, string $number): int
    {
        abort_unless($actor->hasAnyRole(['super_admin', 'admin']), 403);
        $number = trim($number);
        $this->ensure($number !== '' && mb_strlen($number) <= 150, 'Isi No SK dari Adum, maksimal 150 karakter.');
        $ids = $selection->pluck('id')->unique()->values();
        $this->ensure($ids->isNotEmpty(), 'Pilih minimal satu mahasiswa.');

        return DB::transaction(function () use ($actor, $ids, $number): int {
            $projects = ThesisProject::query()->whereKey($ids->all())->orderBy('id')->lockForUpdate()->get();
            $policy = app(SidangPaperPolicy::class);
            abort_unless($projects->count() === $ids->count()
                && $projects->every(fn (ThesisProject $project): bool => $policy->manage($actor, (int) $project->program_studi_id)), 403);
            $this->ensure($projects->pluck('program_studi_id')->unique()->count() === 1, 'Pilih mahasiswa dari satu prodi.');
            $defenses = ThesisDefense::query()->whereIn('project_id', $ids)->where('type', 'sidang')
                ->orderBy('project_id')->orderByDesc('attempt_no')->lockForUpdate()->get()
                ->unique('project_id')->keyBy('project_id');

            foreach ($projects as $project) {
                $defense = $defenses->get($project->id);
                $this->ensure($project->state !== 'cancelled' && $defense?->scheduled_for !== null
                    && in_array($defense->status, self::SK_STATUSES, true),
                    'Pilih sidang yang menunggu finalisasi atau sudah selesai.');
                $this->ensure(blank($defense->sidang_sk) || $defense->sidang_sk === $number,
                    'Ada mahasiswa dengan No SK berbeda. Keluarkan baris tersebut sebelum menyimpan.');
                $defense->forceFill(['sidang_sk' => $number])->save();
            }

            return $projects->count();
        });
    }

    public function record(ThesisDefense $defense, User $actor, array $data): void
    {
        DB::transaction(function () use ($defense, $actor, $data): void {
            $defense = ThesisDefense::query()->lockForUpdate()->findOrFail($defense->id);
            $defense->load('examiners.lecturer.dosenProfile', 'project.programStudi');
            $this->ensure($defense->type === 'sidang' && in_array($defense->status, ['scheduled', 'awaiting_finalization'], true)
                && $defense->sidang_paper === null && $defense->project->state === 'active',
                'Sidang ini sudah diproses atau tidak lagi terjadwal.');

            $moderator = array_key_exists('moderator_user_id', $data)
                ? $data['moderator_user_id']
                : $defense->moderator_user_id;
            if (isset($data['moderator_user_id'])) {
                $this->ensure(User::query()->whereKey($moderator)->whereHas('dosenProfile', function ($query) use ($defense): void {
                    $query->where('is_active', true)->where(function ($query) use ($defense): void {
                        $query->where('program_studi_id', $defense->project->program_studi_id)
                            ->orWhereIn('user_id', $defense->examiners->pluck('lecturer_user_id'));
                    });
                })->exists(), 'Moderator harus dosen aktif pada prodi ini atau anggota panel sidang.');
            }
            $defense->forceFill([
                'moderator_user_id' => $moderator,
                'sidang_sk' => $data['sidang_sk'] ?? $defense->sidang_sk,
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
