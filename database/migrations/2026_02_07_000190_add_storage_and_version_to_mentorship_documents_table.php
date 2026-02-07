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
        Schema::table('mentorship_documents', function (Blueprint $table): void {
            $table->string('category')->nullable()->after('title');
            $table->string('document_group')->nullable()->after('category');
            $table->unsignedInteger('version_number')->default(1)->after('document_group');
            $table->string('storage_disk')->nullable()->after('file_url');
            $table->string('storage_path')->nullable()->after('storage_disk');
            $table->string('mime_type')->nullable()->after('storage_path');
            $table->string('uploaded_by_role')->nullable()->after('uploaded_by_user_id');

            $table->index(['student_user_id', 'document_group', 'version_number'], 'mentorship_documents_version_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mentorship_documents', function (Blueprint $table): void {
            $table->dropIndex('mentorship_documents_version_idx');
            $table->dropColumn([
                'category',
                'document_group',
                'version_number',
                'storage_disk',
                'storage_path',
                'mime_type',
                'uploaded_by_role',
            ]);
        });
    }
};
