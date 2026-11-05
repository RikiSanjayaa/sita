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
        Schema::create('sidang_report_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_studi_id')->constrained('program_studis')->cascadeOnDelete();
            $table->string('semester', 6);
            $table->unsignedSmallInteger('start_year');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();
            $table->unique(['program_studi_id', 'start_year', 'semester'], 'sidang_report_period_program_year_semester');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sidang_report_periods');
    }
};
