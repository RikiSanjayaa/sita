<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mentorship_documents', function (Blueprint $table): void {
            $table->json('revision_items')->nullable();
        });
        DB::table('mentorship_documents')->where('status', 'needs_revision')->whereNotNull('revision_notes')
            ->orderBy('id')->chunkById(100, function ($documents): void {
                foreach ($documents as $document) {
                    if (trim($document->revision_notes) === '') {
                        continue;
                    }
                    DB::table('mentorship_documents')->where('id', $document->id)->update([
                        'revision_items' => json_encode([[
                            'id' => (string) Str::uuid(),
                            'description' => $document->revision_notes,
                            'resolved_at' => null,
                        ]], JSON_THROW_ON_ERROR),
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mentorship_documents', function (Blueprint $table): void {
            $table->dropColumn('revision_items');
        });
    }
};
