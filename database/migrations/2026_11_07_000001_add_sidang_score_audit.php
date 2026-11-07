<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('thesis_defense_examiners', function (Blueprint $table): void {
            $table->foreignId('score_recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('score_recorded_at')->nullable();
            $table->string('score_source', 20)->nullable();
            $table->foreignId('paper_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paper_verified_at')->nullable();
        });
        Schema::create('thesis_defense_score_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('examiner_id')->index()->constrained('thesis_defense_examiners')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20);
            $table->decimal('old_score', 5, 2)->nullable();
            $table->decimal('new_score', 5, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thesis_defense_score_changes');
        Schema::table('thesis_defense_examiners', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('score_recorded_by');
            $table->dropConstrainedForeignId('paper_verified_by');
            $table->dropColumn(['score_recorded_at', 'score_source', 'paper_verified_at']);
        });
    }
};
