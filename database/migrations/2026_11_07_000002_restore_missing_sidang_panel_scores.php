<?php

use App\Models\ThesisDefense;
use App\Services\SidangScoreService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        ThesisDefense::query()->where('type', 'sidang')->eachById(function (ThesisDefense $defense): void {
            $scores = app(SidangScoreService::class);
            $scores->importLegacy($defense);
            DB::transaction(function () use ($defense, $scores): void {
                $defense = ThesisDefense::query()->lockForUpdate()->findOrFail($defense->id);
                if ($defense->status === 'completed' && $defense->sidang_paper === null && $scores->summary($defense) !== null) {
                    $defense->forceFill(['sidang_paper' => ['settings_origin' => 'program_at_migration']]);
                    $scores->snapshot($defense);
                }
            });
        });
    }

    public function down(): void
    {
        // Restored scores and existing historical snapshots remain available on rollback.
    }
};
