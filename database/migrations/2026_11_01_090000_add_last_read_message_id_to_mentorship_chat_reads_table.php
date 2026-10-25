<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The id watermark is the exact "read up to here" position. Receipts saved before this column
     * existed keep a null watermark and fall back to their last_read_at timestamp.
     */
    public function up(): void
    {
        Schema::table('mentorship_chat_reads', function (Blueprint $table): void {
            $table->unsignedBigInteger('last_read_message_id')->nullable()->after('last_read_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mentorship_chat_reads', function (Blueprint $table): void {
            $table->dropColumn('last_read_message_id');
        });
    }
};
