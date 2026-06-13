<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $kaprodiRoleId = DB::table('roles')->where('name', 'kaprodi')->value('id');

        if ($kaprodiRoleId === null) {
            $kaprodiRoleId = DB::table('roles')->insertGetId([
                'name' => 'kaprodi',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $dosenRoleId = DB::table('roles')->where('name', 'dosen')->value('id');

        if ($dosenRoleId === null) {
            $dosenRoleId = DB::table('roles')->insertGetId([
                'name' => 'dosen',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('kaprodi_assignments')
            ->join('program_studis', 'kaprodi_assignments.program_studi_id', '=', 'program_studis.id')
            ->select([
                'kaprodi_assignments.user_id',
                'kaprodi_assignments.program_studi_id',
                'program_studis.concentrations',
                'program_studis.slug',
            ])
            ->orderBy('kaprodi_assignments.id')
            ->get()
            ->each(function (object $assignment) use ($dosenRoleId, $kaprodiRoleId, $now): void {
                foreach ([$kaprodiRoleId, $dosenRoleId] as $roleId) {
                    DB::table('role_user')->updateOrInsert(
                        [
                            'role_id' => $roleId,
                            'user_id' => $assignment->user_id,
                        ],
                        [
                            'created_at' => $now,
                            'updated_at' => $now,
                        ],
                    );
                }

                $concentration = $this->defaultConcentration(
                    concentrations: $assignment->concentrations,
                    slug: $assignment->slug,
                );

                $profileKey = ['user_id' => $assignment->user_id];
                $profileValues = [
                    'program_studi_id' => $assignment->program_studi_id,
                    'concentration' => $concentration,
                    'supervision_quota' => 14,
                    'is_active' => true,
                    'updated_at' => $now,
                ];

                if (DB::table('dosen_profiles')->where($profileKey)->exists()) {
                    DB::table('dosen_profiles')->where($profileKey)->update($profileValues);
                } else {
                    DB::table('dosen_profiles')->insert([
                        ...$profileKey,
                        ...$profileValues,
                        'created_at' => $now,
                    ]);
                }

                $assignmentKey = [
                    'user_id' => $assignment->user_id,
                    'program_studi_id' => $assignment->program_studi_id,
                    'concentration' => $concentration,
                ];
                $assignmentValues = [
                    'is_primary' => true,
                    'is_active' => true,
                    'updated_at' => $now,
                ];

                if (DB::table('dosen_program_studi_assignments')->where($assignmentKey)->exists()) {
                    DB::table('dosen_program_studi_assignments')->where($assignmentKey)->update($assignmentValues);
                } else {
                    DB::table('dosen_program_studi_assignments')->insert([
                        ...$assignmentKey,
                        ...$assignmentValues,
                        'created_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Intentionally left blank; these lecturer identities may be used by workflow records.
    }

    private function defaultConcentration(?string $concentrations, ?string $slug): string
    {
        if ($concentrations !== null && trim($concentrations) !== '') {
            $decoded = json_decode($concentrations, true);

            if (is_array($decoded)) {
                $first = collect($decoded)
                    ->map(static fn(mixed $value): string => trim((string) $value))
                    ->first(static fn(string $value): bool => $value !== '');

                if (is_string($first) && $first !== '') {
                    return $first;
                }
            }
        }

        return $slug === 'ilkom' ? 'Jaringan' : 'Umum';
    }
};
