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
        Schema::create('mentorship_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lecturer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('mentorship_assignment_id')->nullable()->constrained('mentorship_assignments')->nullOnDelete();
            $table->string('title');
            $table->string('file_name');
            $table->string('file_url')->nullable();
            $table->unsignedBigInteger('file_size_kb')->nullable();
            $table->string('status')->default('submitted');
            $table->text('revision_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('uploaded_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['lecturer_user_id', 'status']);
            $table->index(['student_user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mentorship_documents');
    }
};
