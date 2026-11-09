<?php

namespace App\Services;

use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\ThesisProjectEvent;
use App\Models\User;
use App\Policies\SidangPaperPolicy;
use App\Support\WitaDateTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SidangPaperService
{
    public const ROLES = ['examiner_1' => 'Penguji 1', 'examiner_2' => 'Penguji 2', 'supervisor' => 'Pembimbing penilai'];

    public const SK_STATUSES = ['awaiting_finalization', 'completed'];

    public const SK_CORRECTED_EVENT = 'sidang_sk_corrected';

    public function canCorrectSk(ThesisDefense $defense): bool
    {
        return $defense->type === 'sidang'
            && $defense->status === 'completed'
            && $defense->scheduled_for !== null
            && $defense->project->state !== 'cancelled'
            && ! $defense->project->sidangDefenses()
                ->where('attempt_no', '>', $defense->attempt_no)
                ->where('status', '!=', 'cancelled')->exists();
    }

    public function skVersion(ThesisDefense $defense): string
    {
        return hash('sha256', (string) $defense->sidang_sk);
    }

    public function correctSk(ThesisDefense $defense, User $actor, string $number, string $expectedVersion): bool
    {
        $number = trim($number);
        if ($number === '' || mb_strlen($number) > 150) {
            throw ValidationException::withMessages(['sidang_sk' => 'Isi No SK baru dari Adum, maksimal 150 karakter.']);
        }

        return DB::transaction(function () use ($defense, $actor, $number, $expectedVersion): bool {
            $project = ThesisProject::query()->lockForUpdate()->findOrFail($defense->project_id);
            abort_unless(app(SidangPaperPolicy::class)->manage($actor, (int) $project->program_studi_id), 403);
            $current = $project->sidangDefenses()->lockForUpdate()->findOrFail($defense->id);
            $current->setRelation('project', $project);

            if (! $this->canCorrectSk($current)) {
                throw ValidationException::withMessages(['sidang_sk' => 'Koreksi hanya tersedia untuk percobaan sidang terakhir yang sudah final.']);
            }
            if (! hash_equals($this->skVersion($current), $expectedVersion)) {
                throw ValidationException::withMessages(['sidang_sk' => 'No SK sudah berubah. Salin draf jika perlu, lalu muat ulang halaman untuk memeriksa nomor terbaru.']);
            }
            if ($number === $current->sidang_sk) {
                return false;
            }

            $previous = $current->sidang_sk;
            $current->forceFill(['sidang_sk' => $number])->save();
            $project->events()->create([
                'actor_user_id' => $actor->id,
                'event_type' => self::SK_CORRECTED_EVENT,
                'label' => 'No SK dikoreksi',
                'payload' => ['defense_id' => $current->id, 'old_number' => $previous, 'new_number' => $number],
                'occurred_at' => now(),
            ]);

            return true;
        });
    }

    /** @return list<array{id: int, oldNumber: ?string, newNumber: string, actor: string, occurredAt: ?string}> */
    public function skHistory(ThesisDefense $defense): array
    {
        return $defense->project->events()
            ->where('event_type', self::SK_CORRECTED_EVENT)
            ->with('actor')
            ->latest('occurred_at')->latest('id')
            ->get()
            ->filter(fn (ThesisProjectEvent $event): bool => (int) $event->payload['defense_id'] === $defense->id)
            ->map(fn (ThesisProjectEvent $event): array => [
                'id' => $event->id,
                'oldNumber' => $event->payload['old_number'],
                'newNumber' => $event->payload['new_number'],
                'actor' => $event->actor?->name ?? 'Akun tidak tersedia',
                'occurredAt' => $event->occurred_at === null ? null : WitaDateTime::format($event->occurred_at),
            ])->values()->all();
    }

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
            $defense->load(['examiners' => fn ($query) => $query->orderBy('id')->lockForUpdate(), 'project.programStudi']);
            abort_unless(app(SidangPaperPolicy::class)->manage($actor, (int) $defense->project->program_studi_id), 403);
            $scoreService = app(SidangScoreService::class);
            $this->ensure($scoreService->editable($defense), 'Sidang ini sudah difinalisasi atau tidak lagi terjadwal.');

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

            $ids = [];
            foreach (self::ROLES as $role => $label) {
                $entry = $data['scores'][$role] ?? [];
                $panel = $defense->examiners->find((int) ($entry['panel_id'] ?? 0));
                $this->ensure($panel && ($role === 'supervisor' ? $panel->isSupervisorRole() : $panel->role === 'examiner'),
                    'Pilih anggota panel yang sesuai untuk '.$label.'.');
                if (array_key_exists('expected_value', $entry)) {
                    $scoreService->assertCurrent($panel, $entry['expected_value'], 'scores.'.$role.'.value');
                }
                $scoreService->write($panel, $actor, $entry['value'] ?? null, 'paper', verify: true);
                $ids[$role] = $panel->id;
            }
            $this->ensure(count(array_unique($ids)) === 3, 'Ketiga penilai harus berbeda.');
            $defense->forceFill([
                'sidang_paper' => [
                    'recorded_by' => $actor->id, 'recorded_at' => now()->toIso8601String(),
                    'panel_ids' => $ids,
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
