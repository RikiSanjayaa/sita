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
        Schema::table('thesis_projects', function (Blueprint $table) {
            $table->date('mentorship_valid_from')->nullable()->after('notes');
            $table->date('mentorship_valid_until')->nullable()->after('mentorship_valid_from');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('thesis_projects', function (Blueprint $table) {
            $table->dropColumn(['mentorship_valid_from', 'mentorship_valid_until']);
        });
    }
};
