<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('csat_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_studi_id')->constrained('program_studis')->restrictOnDelete();
            $table->string('respondent_role', 20);
            $table->unsignedTinyInteger('score');
            $table->text('kritik')->nullable();
            $table->text('saran')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['program_studi_id', 'created_at']);
            $table->index(['respondent_role', 'created_at']);
            $table->index(['score', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('csat_responses');
    }
};
