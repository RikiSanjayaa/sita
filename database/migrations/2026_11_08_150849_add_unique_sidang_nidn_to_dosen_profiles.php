<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $profiles = DB::table('dosen_profiles')->whereNotNull('sidang_nidn')->get(['id', 'sidang_nidn']);
        $duplicates = $profiles
            ->filter(fn (object $profile): bool => trim($profile->sidang_nidn) !== '')
            ->groupBy(fn (object $profile): string => trim($profile->sidang_nidn))
            ->filter(fn ($group): bool => $group->count() > 1);

        if ($duplicates->isNotEmpty()) {
            $ids = $duplicates->flatten(1)->pluck('id')->implode(', ');
            throw new RuntimeException('NIDN ganda pada ID profil dosen: '.$ids.'. Perbaiki data sebelum mengulang migrasi. Tidak ada NIDN yang diubah.');
        }

        foreach ($profiles as $profile) {
            $normalized = trim($profile->sidang_nidn);
            if ($normalized !== $profile->sidang_nidn || $normalized === '') {
                DB::table('dosen_profiles')->where('id', $profile->id)->update(['sidang_nidn' => $normalized === '' ? null : $normalized]);
            }
        }

        Schema::table('dosen_profiles', function (Blueprint $table) {
            $table->unique('sidang_nidn');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dosen_profiles', function (Blueprint $table) {
            $table->dropUnique(['sidang_nidn']);
        });
    }
};
