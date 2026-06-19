<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('faculties', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_placeholder')->default(false);
            $table->timestamps();
        });

        $facultyId = DB::table('faculties')->insertGetId([
            'name' => 'Belum Ditentukan',
            'code' => 'UNASSIGNED',
            'slug' => 'belum-ditentukan',
            'is_active' => true,
            'is_placeholder' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('program_studis', function (Blueprint $table): void {
            $table->foreignId('faculty_id')
                ->nullable()
                ->after('id')
                ->constrained('faculties')
                ->restrictOnDelete();
        });

        DB::table('program_studis')->whereNull('faculty_id')->update([
            'faculty_id' => $facultyId,
        ]);

        Schema::table('program_studis', function (Blueprint $table): void {
            $table->unsignedBigInteger('faculty_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('program_studis', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('faculty_id');
        });

        Schema::dropIfExists('faculties');
    }
};
