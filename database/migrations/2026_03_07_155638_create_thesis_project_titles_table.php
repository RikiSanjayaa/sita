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
        Schema::create('thesis_project_titles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('thesis_projects')->cascadeOnDelete();
            $table->unsignedInteger('version_no');
            $table->string('title_id');
            $table->string('title_en')->nullable();
            $table->text('proposal_summary')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'version_no']);
            $table->index(['project_id', 'status']);
            $table->index('submitted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thesis_project_titles');
    }
};
