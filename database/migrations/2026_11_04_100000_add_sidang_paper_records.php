<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_studis', function (Blueprint $table): void {
            $table->json('sidang_settings')->nullable();
        });
        Schema::table('dosen_profiles', function (Blueprint $table): void {
            $table->string('sidang_nidn', 30)->nullable();
        });
        Schema::table('thesis_defenses', function (Blueprint $table): void {
            $table->foreignId('moderator_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sidang_sk', 150)->nullable();
            $table->json('sidang_paper')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('thesis_defenses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('moderator_user_id');
            $table->dropColumn(['sidang_sk', 'sidang_paper']);
        });
        Schema::table('dosen_profiles', function (Blueprint $table): void {
            $table->dropColumn('sidang_nidn');
        });
        Schema::table('program_studis', function (Blueprint $table): void {
            $table->dropColumn('sidang_settings');
        });
    }
};
