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
            $table->timestamp('archived_at')->nullable()->after('cancelled_at');
            $table->json('archive_summary')->nullable()->after('archived_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('thesis_projects', function (Blueprint $table) {
            $table->dropColumn(['archived_at', 'archive_summary']);
        });
    }
};
