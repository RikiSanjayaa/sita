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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('browser_notifications_enabled')
                ->default(false)
                ->after('last_active_role');
            $table->json('notification_preferences')
                ->nullable()
                ->after('browser_notifications_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'browser_notifications_enabled',
                'notification_preferences',
            ]);
        });
    }
};
