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
        Schema::create('dosen_program_studi_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_studi_id')->constrained('program_studis')->cascadeOnDelete();
            $table->string('concentration');
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'program_studi_id', 'concentration'], 'dosen_prodi_concentration_unique');
            $table->index(['program_studi_id', 'concentration', 'is_active'], 'dosen_prodi_concentration_active_index');
        });

        DB::table('dosen_profiles')
            ->whereNotNull('program_studi_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $profile): void {
                DB::table('dosen_program_studi_assignments')->insert([
                    'user_id' => $profile->user_id,
                    'program_studi_id' => $profile->program_studi_id,
                    'concentration' => $profile->concentration ?: 'Umum',
                    'is_primary' => true,
                    'is_active' => (bool) $profile->is_active,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dosen_program_studi_assignments');
    }
};
