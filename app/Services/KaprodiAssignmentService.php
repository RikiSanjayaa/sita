<?php

namespace App\Services;

use App\Enums\AppRole;
use App\Models\KaprodiAssignment;
use App\Models\ProgramStudi;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KaprodiAssignmentService
{
    /**
     * @param  array<int, array{user_id?: mixed, is_primary?: mixed}>  $assignments
     */
    public function syncForProgramStudi(ProgramStudi $programStudi, array $assignments): void
    {
        $normalizedAssignments = $this->normalizeAssignments($assignments);

        DB::transaction(function () use ($programStudi, $normalizedAssignments): void {
            $programStudi->kaprodiAssignments()->delete();

            $role = Role::query()->firstOrCreate(['name' => AppRole::Kaprodi->value]);

            foreach ($normalizedAssignments as $assignment) {
                /** @var KaprodiAssignment $kaprodiAssignment */
                $kaprodiAssignment = $programStudi->kaprodiAssignments()->create($assignment);
                $kaprodiAssignment->user?->roles()->syncWithoutDetaching([$role->id]);
            }
        });
    }

    /**
     * @param  array<int, array{user_id?: mixed, is_primary?: mixed}>  $assignments
     * @return array<int, array{user_id: int, is_primary: bool}>
     */
    private function normalizeAssignments(array $assignments): array
    {
        $normalized = collect($assignments)
            ->map(static fn(array $assignment): array => [
                'user_id' => (int) ($assignment['user_id'] ?? 0),
                'is_primary' => (bool) ($assignment['is_primary'] ?? false),
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
}
