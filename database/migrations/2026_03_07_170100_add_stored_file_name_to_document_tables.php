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
        Schema::table('thesis_documents', function (Blueprint $table): void {
            $table->string('stored_file_name')->nullable()->after('storage_path');
        });

        Schema::table('mentorship_documents', function (Blueprint $table): void {
            $table->string('stored_file_name')->nullable()->after('storage_path');
        });

        DB::table('thesis_documents')
            ->select(['id', 'storage_path'])
            ->orderBy('id')
            ->get()
            ->each(function (object $document): void {
                DB::table('thesis_documents')
                    ->where('id', $document->id)
                    ->update([
                        'stored_file_name' => $document->storage_path === null
                            ? null
                            : basename((string) $document->storage_path),
                    ]);
            });

        DB::table('mentorship_documents')
            ->select(['id', 'storage_path'])
            ->orderBy('id')
            ->get()
            ->each(function (object $document): void {
                DB::table('mentorship_documents')
                    ->where('id', $document->id)
                    ->update([
                        'stored_file_name' => $document->storage_path === null
                            ? null
                            : basename((string) $document->storage_path),
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mentorship_documents', function (Blueprint $table): void {
            $table->dropColumn('stored_file_name');
        });

        Schema::table('thesis_documents', function (Blueprint $table): void {
            $table->dropColumn('stored_file_name');
        });
    }
};
