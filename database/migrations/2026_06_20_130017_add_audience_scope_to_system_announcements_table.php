<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_announcements', function (Blueprint $table): void {
            $table->string('target_scope')->default('all')->after('target_roles');
            $table->json('target_faculty_ids')->nullable()->after('program_studi_id');
            $table->json('target_program_studi_ids')->nullable()->after('target_faculty_ids');
        });

        DB::table('system_announcements')
            ->whereNotNull('program_studi_id')
            ->orderBy('id')
            ->each(function (object $announcement): void {
                DB::table('system_announcements')
                    ->where('id', $announcement->id)
                    ->update([
                        'target_scope' => 'programs',
                        'target_program_studi_ids' => json_encode([(int) $announcement->program_studi_id]),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('system_announcements', function (Blueprint $table): void {
            $table->dropColumn([
                'target_scope',
                'target_faculty_ids',
                'target_program_studi_ids',
            ]);
        });
    }
};
