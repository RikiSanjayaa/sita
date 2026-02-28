<?php

use App\Enums\ThesisSubmissionStatus;
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
        Schema::create('thesis_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('program_studi')->nullable();
            $table->string('title_id');
            $table->string('title_en')->nullable();
            $table->text('proposal_summary')->nullable();
            $table->string('status')->default(ThesisSubmissionStatus::IntakeCreated->value);
            $table->boolean('is_active')->default(true);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_user_id', 'status']);
            $table->index(['status', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thesis_submissions');
    }
};
