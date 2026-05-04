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
        Schema::create('thesis_supervisor_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('thesis_projects')->cascadeOnDelete();
            $table->foreignId('lecturer_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('legacy_mentorship_assignment_id')->nullable();
            $table->string('role');
            $table->string('status')->default('active');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['lecturer_user_id', 'status']);
            $table->index(['project_id', 'role', 'status']);
            $table->unique('legacy_mentorship_assignment_id', 'tsa_legacy_assignment_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thesis_supervisor_assignments');
    }
};
