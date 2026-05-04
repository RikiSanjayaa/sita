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
        Schema::table('mentorship_schedules', function (Blueprint $table) {
            $table->boolean('is_recurring')->default(false)->after('status');
            $table->string('recurring_pattern')->nullable()->after('is_recurring');
            $table->unsignedInteger('recurring_count')->nullable()->after('recurring_pattern');
            $table->uuid('recurring_group_id')->nullable()->after('recurring_count');
            $table->unsignedInteger('recurring_index')->nullable()->after('recurring_group_id');

            $table->index('recurring_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('mentorship_schedules', function (Blueprint $table) {
            $table->dropIndex(['recurring_group_id']);
            $table->dropColumn([
                'is_recurring',
                'recurring_pattern',
                'recurring_count',
                'recurring_group_id',
                'recurring_index',
            ]);
        });
    }
};
