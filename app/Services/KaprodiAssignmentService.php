<?php

namespace App\Services;

use App\Enums\AppRole;
use App\Models\DosenProgramStudiAssignment;
use App\Models\KaprodiAssignment;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KaprodiAssignmentService
{
    /**
     * @param  array<int, array{user_id?: mixed, is_primary?: mixed, capabilities?: mixed}>  $assignments
     */
    public function syncForProgramStudi(ProgramStudi $programStudi, array $assignments): void
    {
        $normalizedAssignments = $this->normalizeAssignments($assignments);

        DB::transaction(function () use ($programStudi, $normalizedAssignments): void {
            $programStudi->kaprodiAssignments()->delete();

            foreach ($normalizedAssignments as $assignment) {
                /** @var KaprodiAssignment $kaprodiAssignment */
                $kaprodiAssignment = $programStudi->kaprodiAssignments()->create($assignment);
                $this->ensureKaprodiIsActiveLecturer($kaprodiAssignment->user, $programStudi);
            }
        });
    }

    /**
     * @param  array<int, array{user_id?: mixed, is_primary?: mixed, capabilities?: mixed}>  $assignments
     * @return array<int, array{user_id: int, is_primary: bool, capabilities: array<string, bool>}>
     */
    private function normalizeAssignments(array $assignments): array
    {
        $normalized = collect($assignments)
            ->map(static fn(array $assignment): array => [
                'user_id' => (int) ($assignment['user_id'] ?? 0),
                'is_primary' => (bool) ($assignment['is_primary'] ?? false),
                'capabilities' => KaprodiAssignment::normalizeCapabilities(
                    is_array($assignment['capabilities'] ?? null) ? $assignment['capabilities'] : null,
                ),
            ])
            ->filter(static fn(array $assignment): bool => $assignment['user_id'] > 0)
            ->values();

        if ($normalized->count() > 3) {
            throw ValidationException::withMessages([
                'assignments' => ['Satu program studi hanya boleh memiliki maksimal tiga akun kaprodi.'],
            ]);
        }

        if ($normalized->pluck('user_id')->unique()->count() !== $normalized->count()) {
            throw ValidationException::withMessages([
                'assignments' => ['Akun kaprodi tidak boleh dipilih lebih dari satu kali.'],
            ]);
        }

        if ($normalized->isNotEmpty() && $normalized->where('is_primary', true)->count() !== 1) {
            throw ValidationException::withMessages([
                'assignments' => ['Pilih tepat satu akun sebagai kaprodi utama.'],
            ]);
        }

        return $normalized->all();
    }

    private function ensureKaprodiIsActiveLecturer(?User $user, ProgramStudi $programStudi): void
    {
        if (! $user instanceof User) {
            return;
        }

        $roleIds = collect([AppRole::Kaprodi->value, AppRole::Dosen->value])
            ->map(fn(string $role): int => Role::query()->firstOrCreate(['name' => $role])->id)
            ->all();

        $user->roles()->syncWithoutDetaching($roleIds);

        $concentration = $programStudi->concentrationList()[0] ?? ProgramStudi::DEFAULT_GENERAL_CONCENTRATION;

        $user->dosenProfile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'program_studi_id' => $programStudi->id,
                'concentration' => $concentration,
                'supervision_quota' => $user->dosenProfile?->supervision_quota ?? 14,
                'is_active' => true,
            ],
        );

        DosenProgramStudiAssignment::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'program_studi_id' => $programStudi->id,
                'concentration' => $concentration,
            ],
            [
                'is_primary' => true,
                'is_active' => true,
            ],
        );
    }
}
