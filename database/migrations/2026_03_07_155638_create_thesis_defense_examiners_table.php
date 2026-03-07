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
        Schema::create('thesis_defense_examiners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('defense_id')->constrained('thesis_defenses')->cascadeOnDelete();
            $table->foreignId('lecturer_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('legacy_sempro_examiner_id')->nullable()->unique();
            $table->string('role')->default('examiner');
            $table->unsignedTinyInteger('order_no');
            $table->string('decision')->default('pending');
            $table->decimal('score', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['defense_id', 'lecturer_user_id']);
            $table->unique(['defense_id', 'order_no']);
            $table->index(['lecturer_user_id', 'decision']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thesis_defense_examiners');
    }
};
