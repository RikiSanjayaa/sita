<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addThreadColumns();

        if ($this->isMysql()) {
            $this->dropForeignIfExists('mentorship_chat_threads', 'mentorship_chat_threads_student_user_id_foreign');
            $this->dropIndexIfExists('mentorship_chat_threads', 'mentorship_chat_threads_student_user_id_unique');
            $this->addThreadCompositeIndexIfMissing();
            $this->addForeignIfMissing('mentorship_chat_threads', 'mentorship_chat_threads_student_user_id_foreign');
        } else {
            Schema::table('mentorship_chat_threads', function (Blueprint $table) {
                // Drop the old unique index on student_user_id (allow multiple threads per student)
                $table->dropUnique('mentorship_chat_threads_student_user_id_unique');

                // Add composite index
                $table->index(['student_user_id', 'type', 'context_id'], 'threads_student_type_context_idx');
            });
        }

        if (! Schema::hasTable('mentorship_chat_thread_participants')) {
            Schema::create('mentorship_chat_thread_participants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('thread_id')->constrained('mentorship_chat_threads')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('role', 30)->default('member'); // student, examiner, advisor
                $table->timestamps();

                $table->unique(['thread_id', 'user_id'], 'mctp_thread_user_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mentorship_chat_thread_participants');

        Schema::table('mentorship_chat_threads', function (Blueprint $table) {
            $table->dropIndex('threads_student_type_context_idx');
            $table->dropColumn(['type', 'context_id', 'label']);
            $table->unique('student_user_id');
        });
    }

    private function addThreadColumns(): void
    {
        Schema::table('mentorship_chat_threads', function (Blueprint $table) {
            if (! Schema::hasColumn('mentorship_chat_threads', 'type')) {
                $table->string('type', 30)->default('pembimbing')->after('student_user_id');
            }

            if (! Schema::hasColumn('mentorship_chat_threads', 'context_id')) {
                $table->unsignedBigInteger('context_id')->nullable()->after('type');
            }

            if (! Schema::hasColumn('mentorship_chat_threads', 'label')) {
                $table->string('label')->nullable()->after('context_id');
            }
        });
    }

    private function isMysql(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }

    private function dropForeignIfExists(string $table, string $constraint): void
    {
        if (! $this->foreignExists($table, $constraint)) {
            return;
        }

        DB::statement("alter table `{$table}` drop foreign key `{$constraint}`");
    }

    private function addForeignIfMissing(string $table, string $constraint): void
    {
        if ($this->foreignExists($table, $constraint)) {
            return;
        }

        DB::statement("alter table `{$table}` add constraint `{$constraint}` foreign key (`student_user_id`) references `users` (`id`) on delete cascade");
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (! $this->indexExists($table, $index)) {
            return;
        }

        DB::statement("alter table `{$table}` drop index `{$index}`");
    }

    private function addThreadCompositeIndexIfMissing(): void
    {
        if ($this->indexExists('mentorship_chat_threads', 'threads_student_type_context_idx')) {
            return;
        }

        Schema::table('mentorship_chat_threads', function (Blueprint $table) {
            $table->index(['student_user_id', 'type', 'context_id'], 'threads_student_type_context_idx');
        });
    }

    private function foreignExists(string $table, string $constraint): bool
    {
        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('CONSTRAINT_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->exists();
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->exists();
    }
};
