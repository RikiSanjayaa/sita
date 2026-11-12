<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The default name (65 characters) exceeds the 64 character identifier limit of MySQL/MariaDB.
     */
    private const SECONDARY_LECTURER_FOREIGN_KEY = 'thesis_titles_proposed_secondary_lecturer_foreign';

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
                ->constrained('users', indexName: self::SECONDARY_LECTURER_FOREIGN_KEY)
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
            $table->dropForeign(['proposed_secondary_lecturer_user_id'])->index(self::SECONDARY_LECTURER_FOREIGN_KEY);
            $table->dropColumn('proposed_secondary_lecturer_user_id');
        });
    }
};
