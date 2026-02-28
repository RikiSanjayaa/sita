<?php

use App\Enums\SemproStatus;
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
        Schema::create('sempros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thesis_submission_id')->constrained('thesis_submissions')->cascadeOnDelete();
            $table->string('status')->default(SemproStatus::Draft->value);
            $table->timestamp('scheduled_for')->nullable();
            $table->string('location')->nullable();
            $table->string('mode')->default('offline');
            $table->timestamp('revision_due_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['thesis_submission_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sempros');
    }
};
