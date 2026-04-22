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
        Schema::table('thesis_documents', function (Blueprint $table): void {
            $table->foreignId('source_workspace_document_id')
                ->nullable()
                ->after('revision_id')
                ->constrained('mentorship_documents')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('thesis_documents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_workspace_document_id');
        });
    }
};
