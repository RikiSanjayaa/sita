<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Additional sidang examiners stay secret from the mahasiswa until the sidang is
     * completed, so they must not sit in the Sidang chat thread before that. Threads
     * created before the rule joined every panel member; take the additional examiners
     * (not the supervisors) out of every Sidang thread whose defense is not completed.
     * Existing messages stay. completeSidang() adds the examiners back at completion.
     */
    public function up(): void
    {
        DB::table('mentorship_chat_thread_participants')
            ->where('role', 'examiner')
            ->whereExists(function (Builder $thread): void {
                $thread->select(DB::raw(1))
                    ->from('mentorship_chat_threads')
                    ->whereColumn('mentorship_chat_threads.id', 'mentorship_chat_thread_participants.thread_id')
                    ->where('mentorship_chat_threads.type', 'sidang')
                    ->whereExists(function (Builder $examiner): void {
                        $examiner->select(DB::raw(1))
                            ->from('thesis_defense_examiners')
                            ->join('thesis_defenses', 'thesis_defenses.id', '=', 'thesis_defense_examiners.defense_id')
                            ->whereColumn('thesis_defenses.id', 'mentorship_chat_threads.context_id')
                            ->whereColumn('thesis_defense_examiners.lecturer_user_id', 'mentorship_chat_thread_participants.user_id')
                            ->where('thesis_defenses.type', 'sidang')
                            ->where('thesis_defenses.status', '!=', 'completed')
                            ->whereNotIn('thesis_defense_examiners.role', ['primary_supervisor', 'secondary_supervisor']);
                    });
            })
            ->delete();
    }

    /**
     * The removed participants cannot be told apart from the ones that stay, and
     * the rule they enforce is the intended behaviour, so there is nothing to restore.
     */
    public function down(): void {}
};
