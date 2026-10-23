<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Every pre-existing ThesisRevision gets one round and one item built from its
 * `notes`, so nothing is lost and the new tables describe the old data.
 * Idempotent: revisions that already have a round are skipped.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('thesis_revisions')
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('thesis_revision_rounds')
                    ->whereColumn('thesis_revision_rounds.revision_id', 'thesis_revisions.id');
            })
            ->orderBy('id')
            ->chunkById(200, function ($revisions): void {
                foreach ($revisions as $revision) {
                    $resolved = $revision->status === 'resolved';
                    $submitted = $revision->status === 'submitted';

                    DB::table('thesis_revision_rounds')->insert([
                        'revision_id' => $revision->id,
                        'round_no' => 1,
                        'opened_at' => $revision->created_at,
                        'student_submitted_at' => $revision->submitted_at,
                        'student_document_id' => null,
                        'reviewed_at' => $resolved ? $revision->resolved_at : null,
                        'status' => match (true) {
                            $resolved => 'closed',
                            $submitted => 'awaiting_review',
                            default => 'awaiting_student',
                        },
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('thesis_revision_items')->insert([
                        'revision_id' => $revision->id,
                        'round_opened' => 1,
                        'location' => null,
                        'description' => $revision->notes,
                        'priority' => null,
                        'status' => match (true) {
                            $resolved => 'accepted',
                            $submitted => 'addressed',
                            default => 'open',
                        },
                        'verified_at' => $resolved ? $revision->resolved_at : null,
                        'verified_by_user_id' => $resolved ? $revision->resolved_by_user_id : null,
                        'sort_order' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // The backfill is not reversible on its own; dropping the tables in the
        // previous migration removes the rows.
    }
};
