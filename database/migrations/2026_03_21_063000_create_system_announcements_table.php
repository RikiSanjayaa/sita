<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_announcements', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->json('target_roles');
            $table->foreignId('program_studi_id')->nullable()->constrained('program_studis')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('action_url')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_announcements');
    }
};
