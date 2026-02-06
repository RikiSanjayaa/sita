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
        Schema::create('mentorship_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lecturer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('mentorship_assignment_id')->nullable()->constrained('mentorship_assignments')->nullOnDelete();
            $table->string('topic');
            $table->string('status')->default('pending');
            $table->timestamp('requested_for')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->string('location')->nullable();
            $table->text('student_note')->nullable();
            $table->text('lecturer_note')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
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
        Schema::dropIfExists('mentorship_schedules');
    }
};
