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
        Schema::create('mentorship_chat_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mentorship_chat_thread_id')
                ->constrained('mentorship_chat_threads')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_read_at');
            $table->timestamps();

            $table->unique(['mentorship_chat_thread_id', 'user_id'], 'chat_reads_thread_user_unique');
            $table->index(['user_id', 'last_read_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mentorship_chat_reads');
    }
};
