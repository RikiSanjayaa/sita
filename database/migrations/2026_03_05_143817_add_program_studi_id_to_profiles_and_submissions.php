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
        Schema::table('mahasiswa_profiles', function (Blueprint $table) {
            $table->foreignId('program_studi_id')->nullable()->constrained('program_studis')->nullOnDelete();
            $table->dropColumn('program_studi');
        });

        Schema::table('dosen_profiles', function (Blueprint $table) {
            $table->foreignId('program_studi_id')->nullable()->constrained('program_studis')->nullOnDelete();
            $table->dropColumn('homebase');
        });

        Schema::table('admin_profiles', function (Blueprint $table) {
            $table->foreignId('program_studi_id')->nullable()->constrained('program_studis')->nullOnDelete();
            $table->dropColumn('program_studi');
        });

        Schema::table('thesis_submissions', function (Blueprint $table) {
            $table->foreignId('program_studi_id')->nullable()->constrained('program_studis')->nullOnDelete();
            $table->dropColumn('program_studi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('thesis_submissions', function (Blueprint $table) {
            $table->string('program_studi')->nullable();
            $table->dropConstrainedForeignId('program_studi_id');
        });

        Schema::table('admin_profiles', function (Blueprint $table) {
            $table->string('program_studi')->nullable();
            $table->dropConstrainedForeignId('program_studi_id');
        });

        Schema::table('dosen_profiles', function (Blueprint $table) {
            $table->string('homebase')->nullable();
            $table->dropConstrainedForeignId('program_studi_id');
        });

        Schema::table('mahasiswa_profiles', function (Blueprint $table) {
            $table->string('program_studi')->nullable();
            $table->dropConstrainedForeignId('program_studi_id');
        });
    }
};
