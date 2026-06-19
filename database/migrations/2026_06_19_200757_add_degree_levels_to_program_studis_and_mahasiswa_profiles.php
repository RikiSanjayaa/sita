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
        Schema::table('program_studis', function (Blueprint $table): void {
            $table->json('degree_levels')->nullable()->after('concentrations');
        });

        DB::table('program_studis')->whereNull('degree_levels')->update([
            'degree_levels' => json_encode(['s1'], JSON_THROW_ON_ERROR),
        ]);

        Schema::table('mahasiswa_profiles', function (Blueprint $table): void {
            $table->string('degree_level', 2)->default('s1')->after('program_studi_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mahasiswa_profiles', function (Blueprint $table): void {
            $table->dropColumn('degree_level');
        });

        Schema::table('program_studis', function (Blueprint $table): void {
            $table->dropColumn('degree_levels');
        });
    }
};
