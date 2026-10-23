<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('thesis_project_titles', function (Blueprint $table) {
            $table->dropForeign(['proposed_primary_lecturer_user_id']);
            $table->dropForeign(['proposed_secondary_lecturer_user_id']);
            $table->dropColumn('proposed_secondary_lecturer_user_id');
            $table->renameColumn('proposed_primary_lecturer_user_id', 'discussion_lecturer_user_id');
        });

        Schema::table('thesis_project_titles', function (Blueprint $table) {
            $table->foreign('discussion_lecturer_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('thesis_project_titles', function (Blueprint $table) {
            $table->dropForeign(['discussion_lecturer_user_id']);
            $table->renameColumn('discussion_lecturer_user_id', 'proposed_primary_lecturer_user_id');
            $table->foreignId('proposed_secondary_lecturer_user_id')
                ->nullable()
                ->after('proposed_primary_lecturer_user_id')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::table('thesis_project_titles', function (Blueprint $table) {
            $table->foreign('proposed_primary_lecturer_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }
};
