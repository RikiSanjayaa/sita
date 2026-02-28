<?php

use App\Enums\SemproExaminerDecision;
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
        Schema::create('sempro_examiners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sempro_id')->constrained('sempros')->cascadeOnDelete();
            $table->foreignId('examiner_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('examiner_order');
            $table->string('decision')->default(SemproExaminerDecision::Pending->value);
            $table->text('decision_notes')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['sempro_id', 'examiner_user_id']);
            $table->unique(['sempro_id', 'examiner_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sempro_examiners');
    }
};
