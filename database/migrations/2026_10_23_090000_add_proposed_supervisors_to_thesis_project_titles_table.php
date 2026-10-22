<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('thesis_project_titles', function (Blueprint $table) {
            $table->foreignId('proposed_primary_lecturer_user_id')
                ->nullable()
                ->after('decision_notes')
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('proposed_secondary_lecturer_user_id')
                ->nullable()
                ->after('proposed_primary_lecturer_user_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('thesis_project_titles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proposed_primary_lecturer_user_id');
            $table->dropConstrainedForeignId('proposed_secondary_lecturer_user_id');
        });
    }
};
