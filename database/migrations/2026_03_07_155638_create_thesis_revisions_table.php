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
        Schema::create('thesis_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('thesis_projects')->cascadeOnDelete();
            $table->foreignId('defense_id')->nullable()->constrained('thesis_defenses')->nullOnDelete();
            $table->unsignedBigInteger('legacy_sempro_revision_id')->nullable()->unique();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('open');
            $table->text('notes');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['defense_id', 'status']);
            $table->index(['due_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thesis_revisions');
    }
};
