<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $program = DB::table('program_studis')->where('slug', 'ilkom')->first(['id', 'sidang_settings']);
        if ($program === null) {
            return;
        }

        $settings = json_decode($program->sidang_settings ?? '{}', true, flags: JSON_THROW_ON_ERROR) ?? [];
        if (! array_key_exists('minimum_paper_examiners', $settings)) {
            $settings['minimum_paper_examiners'] = 2;
            DB::table('program_studis')->where('id', $program->id)
                ->update(['sidang_settings' => json_encode($settings, JSON_THROW_ON_ERROR)]);
        }
    }

    public function down(): void
    {
        // Preserve settings that may have been changed by an administrator.
    }
};
