<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('thesis_revision_items', function (Blueprint $table): void {
            $table->text('verification_comment')->nullable()->after('student_response_location');
        });
    }

    public function down(): void
    {
        Schema::table('thesis_revision_items', function (Blueprint $table): void {
            $table->dropColumn('verification_comment');
        });
    }
};
