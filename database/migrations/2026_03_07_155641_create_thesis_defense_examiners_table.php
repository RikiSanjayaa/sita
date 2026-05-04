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
            $table->unsignedBigInteger('legacy_sempro_examiner_id')->nullable();
            $table->string('role')->default('examiner');
            $table->unsignedTinyInteger('order_no');
            $table->string('decision')->default('pending');
            $table->decimal('score', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('legacy_sempro_examiner_id', 'tde_legacy_examiner_unique');
            $table->unique(['defense_id', 'lecturer_user_id'], 'tde_defense_lecturer_unique');
            $table->unique(['defense_id', 'order_no'], 'tde_defense_order_unique');
            $table->index(['lecturer_user_id', 'decision'], 'tde_lecturer_decision_idx');
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
