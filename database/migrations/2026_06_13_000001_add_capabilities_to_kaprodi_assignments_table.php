<?php

use App\Models\KaprodiAssignment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kaprodi_assignments', function (Blueprint $table): void {
            $table->json('capabilities')->nullable()->after('primary_guard');
        });

        KaprodiAssignment::query()->update([
            'capabilities' => json_encode(KaprodiAssignment::defaultCapabilities()),
        ]);
    }

    public function down(): void
    {
        Schema::table('kaprodi_assignments', function (Blueprint $table): void {
            $table->dropColumn('capabilities');
        });
    }
};
