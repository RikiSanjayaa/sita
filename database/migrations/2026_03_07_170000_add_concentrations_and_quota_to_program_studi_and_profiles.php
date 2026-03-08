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
            $table->json('concentrations')->nullable()->after('slug');
        });

        Schema::table('mahasiswa_profiles', function (Blueprint $table): void {
            $table->string('concentration')->nullable()->after('program_studi_id');
        });

        Schema::table('dosen_profiles', function (Blueprint $table): void {
            $table->string('concentration')->nullable()->after('program_studi_id');
            $table->unsignedInteger('supervision_quota')->default(14)->after('concentration');
        });

        $defaultConcentrationsByProgramStudiId = [];

        DB::table('program_studis')
            ->select(['id', 'slug'])
            ->orderBy('id')
            ->get()
            ->each(function (object $programStudi) use (&$defaultConcentrationsByProgramStudiId): void {
                $concentrations = $programStudi->slug === 'ilkom'
                    ? ['Jaringan', 'Sistem Cerdas', 'Computer Vision']
                    : ['Umum'];

                DB::table('program_studis')
                    ->where('id', $programStudi->id)
                    ->update([
                        'concentrations' => json_encode($concentrations, JSON_THROW_ON_ERROR),
                    ]);

                $defaultConcentrationsByProgramStudiId[$programStudi->id] = $concentrations[0];
            });

        foreach ($defaultConcentrationsByProgramStudiId as $programStudiId => $defaultConcentration) {
            DB::table('mahasiswa_profiles')
                ->where('program_studi_id', $programStudiId)
                ->update(['concentration' => $defaultConcentration]);

            DB::table('dosen_profiles')
                ->where('program_studi_id', $programStudiId)
                ->update(['concentration' => $defaultConcentration]);
        }

        DB::table('mahasiswa_profiles')
            ->whereNull('concentration')
            ->update(['concentration' => 'Umum']);

        DB::table('dosen_profiles')
            ->whereNull('concentration')
            ->update(['concentration' => 'Umum']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dosen_profiles', function (Blueprint $table): void {
            $table->dropColumn(['concentration', 'supervision_quota']);
        });

        Schema::table('mahasiswa_profiles', function (Blueprint $table): void {
            $table->dropColumn('concentration');
        });

        Schema::table('program_studis', function (Blueprint $table): void {
            $table->dropColumn('concentrations');
        });
    }
};
