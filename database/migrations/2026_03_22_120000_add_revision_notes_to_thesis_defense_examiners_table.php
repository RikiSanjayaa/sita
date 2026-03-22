<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('thesis_defense_examiners', function (Blueprint $table) {
            $table->text('revision_notes')->nullable()->after('notes');
        });

        $activeRevisions = DB::table('thesis_revisions')
            ->whereNotNull('defense_id')
            ->whereIn('status', ['open', 'submitted'])
            ->orderBy('id')
            ->get();

        foreach ($activeRevisions as $revision) {
            $requestingLecturerIds = DB::table('thesis_defense_examiners')
                ->where('defense_id', $revision->defense_id)
                ->where('decision', 'pass_with_revision')
                ->orderBy('order_no')
                ->pluck('lecturer_user_id')
                ->map(static fn($id): int => (int) $id)
                ->values();

            if ($requestingLecturerIds->isEmpty()) {
                continue;
            }

            DB::table('thesis_revisions')
                ->where('id', $revision->id)
                ->update([
                    'requested_by_user_id' => $requestingLecturerIds->first(),
                    'updated_at' => now(),
                ]);

            $existingRequesterIds = DB::table('thesis_revisions')
                ->where('project_id', $revision->project_id)
                ->where('defense_id', $revision->defense_id)
                ->whereIn('status', ['open', 'submitted'])
                ->whereNotNull('requested_by_user_id')
                ->pluck('requested_by_user_id')
                ->map(static fn($id): int => (int) $id)
                ->all();

            foreach ($requestingLecturerIds->slice(1) as $lecturerUserId) {
                if (in_array($lecturerUserId, $existingRequesterIds, true)) {
                    continue;
                }

                DB::table('thesis_revisions')->insert([
                    'project_id' => $revision->project_id,
                    'defense_id' => $revision->defense_id,
                    'legacy_sempro_revision_id' => null,
                    'requested_by_user_id' => $lecturerUserId,
                    'status' => $revision->status,
                    'notes' => $revision->notes,
                    'due_at' => $revision->due_at,
                    'submitted_at' => $revision->submitted_at,
                    'resolved_at' => $revision->resolved_at,
                    'resolved_by_user_id' => $revision->resolved_by_user_id,
                    'resolution_notes' => $revision->resolution_notes,
                    'created_at' => $revision->created_at,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('thesis_defense_examiners', function (Blueprint $table) {
            $table->dropColumn('revision_notes');
        });
    }
};
