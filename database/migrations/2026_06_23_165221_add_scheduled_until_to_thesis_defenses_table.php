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
        Schema::table('thesis_defenses', function (Blueprint $table) {
            $table->timestamp('scheduled_until')->nullable()->after('scheduled_for');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('thesis_defenses', function (Blueprint $table) {
            $table->dropColumn('scheduled_until');
        });
    }
};
