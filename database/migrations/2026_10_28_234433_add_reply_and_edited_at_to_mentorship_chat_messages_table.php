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
            // Keep the id after source deletion so replies retain an unavailable context.
            $table->unsignedBigInteger('reply_to_id')->nullable()->index();
            $table->timestamp('edited_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mentorship_chat_messages', function (Blueprint $table): void {
            $table->dropIndex(['reply_to_id']);
            $table->dropColumn(['reply_to_id', 'edited_at']);
        });
    }
};
