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
        Schema::create('thesis_defenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('thesis_projects')->cascadeOnDelete();
            $table->foreignId('title_version_id')->nullable()->constrained('thesis_project_titles')->nullOnDelete();
            $table->unsignedBigInteger('legacy_sempro_id')->nullable()->unique();
            $table->string('type');
            $table->unsignedInteger('attempt_no');
            $table->string('status')->default('draft');
            $table->string('result')->default('pending');
            $table->timestamp('scheduled_for')->nullable();
            $table->string('location')->nullable();
            $table->string('mode')->default('offline');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decision_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'type', 'attempt_no']);
            $table->index(['type', 'status', 'scheduled_for']);
            $table->index(['project_id', 'type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thesis_defenses');
    }
};
