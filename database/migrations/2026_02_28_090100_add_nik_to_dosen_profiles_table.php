<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('dosen_profiles', 'nik')) {
            Schema::table('dosen_profiles', function (Blueprint $table): void {
                $table->string('nik')->nullable()->unique()->after('user_id');
            });
        }

        if (Schema::hasColumn('dosen_profiles', 'nidn')) {
            DB::table('dosen_profiles')
                ->whereNull('nik')
                ->update(['nik' => DB::raw('nidn')]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('dosen_profiles', 'nik')) {
            Schema::table('dosen_profiles', function (Blueprint $table): void {
                $table->dropUnique(['nik']);
                $table->dropColumn('nik');
            });
        }
    }
};
