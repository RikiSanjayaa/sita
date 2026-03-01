<?php

namespace Database\Seeders;

use App\Enums\AppRole;
use App\Enums\SemproStatus;
use App\Enums\ThesisSubmissionStatus;
use App\Models\DosenProfile;
use App\Models\Role;
use App\Models\Sempro;
use App\Models\SemproExaminer;
use App\Models\ThesisSubmission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ThesisWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@sita.test')->first();
        $student = User::query()->where('email', 'mahasiswa@sita.test')->first();

        if ($admin === null || $student === null) {
            return;
        }

        $dosenRole = Role::query()->firstOrCreate(['name' => AppRole::Dosen->value]);

        $primaryExaminer = User::query()->firstOrCreate(
            ['email' => 'dosen@sita.test'],
            [
                'name' => 'Dr. Budi Santoso, M.Kom.',
                'password' => Hash::make('password'),
                'last_active_role' => AppRole::Dosen->value,
            ],
        );
        $primaryExaminer->roles()->syncWithoutDetaching([$dosenRole->id]);
        DosenProfile::query()->updateOrCreate(['user_id' => $primaryExaminer->id], [
            'nik' => '7301010101010001',
            'homebase' => 'Informatika',
            'is_active' => true,
        ]);

        $secondaryExaminer = User::query()->firstOrCreate(
            ['email' => 'dosen2@sita.test'],
            [
                'name' => 'Dr. Ratna Kusuma, M.Kom.',
                'password' => Hash::make('password'),
                'last_active_role' => AppRole::Dosen->value,
            ],
        );
        $secondaryExaminer->roles()->syncWithoutDetaching([$dosenRole->id]);
        DosenProfile::query()->updateOrCreate(['user_id' => $secondaryExaminer->id], [
            'nik' => '7301010101010002',
            'homebase' => 'Informatika',
            'is_active' => true,
        ]);

        $submission = ThesisSubmission::query()->updateOrCreate(
            ['student_user_id' => $student->id, 'title_id' => 'Sistem Rekomendasi Topik Bimbingan Berbasis Riwayat Interaksi'],
            [
                'program_studi' => 'Informatika',
                'title_en' => 'Mentoring Topic Recommendation System Based on Interaction History',
                'proposal_summary' => 'Sistem rekomendasi topik bimbingan untuk meningkatkan efektivitas konsultasi mahasiswa.',
                'status' => ThesisSubmissionStatus::SemproDijadwalkan->value,
                'is_active' => true,
                'submitted_at' => now()->subDays(12),
            ],
        );

        $sempro = Sempro::query()->updateOrCreate(
            ['thesis_submission_id' => $submission->id],
            [
                'status' => SemproStatus::Scheduled->value,
                'scheduled_for' => now()->addDays(7)->setTime(9, 0),
                'location' => 'Ruang Seminar 2',
                'mode' => 'offline',
                'created_by' => $admin->id,
            ],
        );

        SemproExaminer::query()->updateOrCreate(
            ['sempro_id' => $sempro->id, 'examiner_order' => 1],
            ['examiner_user_id' => $primaryExaminer->id, 'assigned_by' => $admin->id],
        );

        SemproExaminer::query()->updateOrCreate(
            ['sempro_id' => $sempro->id, 'examiner_order' => 2],
            ['examiner_user_id' => $secondaryExaminer->id, 'assigned_by' => $admin->id],
        );
    }
}
