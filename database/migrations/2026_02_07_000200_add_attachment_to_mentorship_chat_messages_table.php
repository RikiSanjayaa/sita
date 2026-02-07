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
        Schema::table('mentorship_chat_messages', function (Blueprint $table): void {
            $table->string('attachment_disk')->nullable()->after('related_document_id');
            $table->string('attachment_path')->nullable()->after('attachment_disk');
            $table->string('attachment_name')->nullable()->after('attachment_path');
            $table->string('attachment_mime')->nullable()->after('attachment_name');
            $table->unsignedBigInteger('attachment_size_kb')->nullable()->after('attachment_mime');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mentorship_chat_messages', function (Blueprint $table): void {
            $table->dropColumn([
                'attachment_disk',
                'attachment_path',
                'attachment_name',
                'attachment_mime',
                'attachment_size_kb',
            ]);
        });
    }
};
