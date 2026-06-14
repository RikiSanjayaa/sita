<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mentorship_chat_threads', function (Blueprint $table): void {
            if (Schema::hasColumn('mentorship_chat_threads', 'student_user_id')) {
                $table->foreignId('student_user_id')->nullable()->change();
            }

            if (! Schema::hasColumn('mentorship_chat_threads', 'private_key')) {
                $table->string('private_key')->nullable()->after('label');
                $table->unique('private_key', 'mentorship_chat_threads_private_key_unique');
            }
        });
    }

    public function down(): void
    {
        DB::table('mentorship_chat_threads')
            ->where('type', 'private')
            ->delete();

        Schema::table('mentorship_chat_threads', function (Blueprint $table): void {
            if (Schema::hasColumn('mentorship_chat_threads', 'private_key')) {
                $table->dropUnique('mentorship_chat_threads_private_key_unique');
                $table->dropColumn('private_key');
            }

            if (Schema::hasColumn('mentorship_chat_threads', 'student_user_id')) {
                $table->foreignId('student_user_id')->nullable(false)->change();
            }
        });
    }
};
