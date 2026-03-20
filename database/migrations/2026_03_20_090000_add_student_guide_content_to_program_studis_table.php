<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_studis', function (Blueprint $table): void {
            $table->json('student_guide_content')->nullable()->after('concentrations');
            $table->foreignId('student_guide_updated_by')->nullable()->after('student_guide_content')->constrained('users')->nullOnDelete();
            $table->timestamp('student_guide_updated_at')->nullable()->after('student_guide_updated_by');
        });
    }

    public function down(): void
    {
        Schema::table('program_studis', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('student_guide_updated_by');
            $table->dropColumn(['student_guide_content', 'student_guide_updated_at']);
        });
    }
};
