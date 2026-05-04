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
        Schema::create('mentorship_chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mentorship_chat_thread_id')
                ->constrained('mentorship_chat_threads')
                ->cascadeOnDelete();
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('related_document_id')->nullable()->constrained('mentorship_documents')->nullOnDelete();
            $table->string('message_type')->default('text');
            $table->text('message');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['mentorship_chat_thread_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mentorship_chat_messages');
    }
};
