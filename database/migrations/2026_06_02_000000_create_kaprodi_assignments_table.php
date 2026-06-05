<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kaprodi_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('program_studi_id')->constrained('program_studis')->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->unsignedTinyInteger('primary_guard')->nullable();
            $table->timestamps();

            $table->unique(['program_studi_id', 'user_id'], 'kaprodi_assignment_prodi_user_unique');
            $table->unique(['program_studi_id', 'primary_guard'], 'kaprodi_assignment_one_primary_unique');
            $table->index(['program_studi_id', 'is_primary'], 'kaprodi_assignment_prodi_primary_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kaprodi_assignments');
    }
};
