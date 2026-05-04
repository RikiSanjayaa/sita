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
        Schema::create('thesis_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('thesis_projects')->cascadeOnDelete();
            $table->foreignId('title_version_id')->nullable()->constrained('thesis_project_titles')->nullOnDelete();
            $table->foreignId('defense_id')->nullable()->constrained('thesis_defenses')->nullOnDelete();
            $table->foreignId('revision_id')->nullable()->constrained('thesis_revisions')->nullOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind');
            $table->string('status')->default('active');
            $table->unsignedInteger('version_no')->default(1);
            $table->string('title');
            $table->text('notes')->nullable();
            $table->string('storage_disk');
            $table->string('storage_path');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('file_size_kb')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'kind', 'status'], 'td_project_kind_status_idx');
            $table->index(['defense_id', 'kind'], 'td_defense_kind_idx');
            $table->index('revision_id', 'td_revision_idx');
            $table->unique(['project_id', 'kind', 'version_no', 'storage_path'], 'td_project_kind_version_path_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thesis_documents');
    }
};
