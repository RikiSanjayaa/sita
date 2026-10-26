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
        Schema::table('mentorship_schedules', function (Blueprint $table): void {
            $table->longText('student_session_note')->nullable();
            $table->timestamp('student_session_noted_at')->nullable();
            $table->longText('lecturer_session_note')->nullable();
            $table->timestamp('lecturer_session_noted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mentorship_schedules', function (Blueprint $table): void {
            $table->dropColumn(['student_session_note', 'student_session_noted_at', 'lecturer_session_note', 'lecturer_session_noted_at']);
        });
    }
};
