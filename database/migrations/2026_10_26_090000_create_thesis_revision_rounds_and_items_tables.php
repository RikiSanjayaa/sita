<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thesis_revision_rounds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('revision_id')->constrained('thesis_revisions')->cascadeOnDelete();
            $table->unsignedInteger('round_no');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('student_submitted_at')->nullable();
            $table->foreignId('student_document_id')->nullable()->constrained('thesis_documents')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('status')->default('awaiting_student');
            $table->timestamps();

            $table->unique(['revision_id', 'round_no']);
            $table->index(['revision_id', 'status']);
        });

        Schema::create('thesis_revision_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('revision_id')->constrained('thesis_revisions')->cascadeOnDelete();
            $table->unsignedInteger('round_opened')->default(1);
            $table->string('location')->nullable();
            $table->text('description');
            $table->string('priority')->nullable();
            $table->string('status')->default('open');
            $table->text('student_response')->nullable();
            $table->string('student_response_location')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['revision_id', 'sort_order']);
        });

        // Examiners submit their revision items with the decision; the items
        // become ThesisRevisionItem rows when the admin finalises the exam.
        Schema::table('thesis_defense_examiners', function (Blueprint $table): void {
            $table->json('revision_items')->nullable()->after('revision_notes');
        });
    }

    public function down(): void
    {
        Schema::table('thesis_defense_examiners', function (Blueprint $table): void {
            $table->dropColumn('revision_items');
        });

        Schema::dropIfExists('thesis_revision_items');
        Schema::dropIfExists('thesis_revision_rounds');
    }
};
