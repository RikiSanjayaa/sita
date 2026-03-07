<?php

namespace Database\Seeders;

use App\Enums\AdvisorType;
use App\Enums\AssignmentStatus;
use App\Enums\SemproExaminerDecision;
use App\Enums\SemproStatus;
use App\Enums\ThesisSubmissionStatus;
use App\Models\MentorshipAssignment;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipChatThreadParticipant;
use App\Models\Sempro;
use App\Models\SemproExaminer;
use App\Models\SemproRevision;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisProject;
use App\Models\ThesisProjectEvent;
use App\Models\ThesisRevision;
use App\Models\ThesisSubmission;
use App\Models\User;
use App\Services\LegacyThesisProjectBackfillService;
use Illuminate\Database\Seeder;

/**
 * Seeds thesis workflow data at various stages:
 *
 * - mahasiswa@sita.test → Sempro approved + pembimbing ditetapkan (fully progressed)
 * - akbar@sita.test     → Sempro scheduled (belum dilaksanakan)
 * - nadia@sita.test     → Sempro revision (sedang revisi)
 * - rizky@sita.test     → Menunggu persetujuan (baru submit judul)
 * - siti@sita.test      → Sempro approved (belum diberi pembimbing)
 * - farhan@sita.test    → No submission yet
 * - bagas@sita.test     → Multiple sempro attempts
 * - laila@sita.test     → Historical inactive project + new active project
 * - putra@sita.test     → Supervisor rotation + future sidang scenario
 */
class ThesisWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@sita.test')->first();

        if ($admin === null) {
            return;
        }

        $ilkom = \App\Models\ProgramStudi::where('slug', 'ilkom')->first();

        if ($ilkom === null) {
            return;
        }

        $dosen1 = User::query()->where('email', 'dosen@sita.test')->first();
        $dosen2 = User::query()->where('email', 'dosen2@sita.test')->first();
        $dosen3 = User::query()->where('email', 'dosen3@sita.test')->first();
        $dosen4 = User::query()->where('email', 'dosen4@sita.test')->first();

        if ($dosen1 === null || $dosen2 === null) {
            return;
        }

        // === 1. mahasiswa@sita.test: Full flow — Sempro approved, pembimbing ditetapkan ===
        $this->seedFullyProgressed($admin, $dosen1, $dosen2, $dosen3, $dosen4, $ilkom);

        // === 2. akbar@sita.test: Sempro dijadwalkan ===
        $this->seedSemproScheduled($admin, $dosen1, $dosen2, $ilkom);

        // === 3. nadia@sita.test: Sempro revisi ===
        $this->seedSemproRevision($admin, $dosen1, $dosen2, $ilkom);

        // === 4. rizky@sita.test: Menunggu persetujuan ===
        $this->seedMenungguPersetujuan($ilkom);

        // === 5. siti@sita.test: Sempro approved, belum ada pembimbing ===
        $this->seedSemproApproved($admin, $dosen1, $dosen2, $ilkom);

        // === 6. bagas@sita.test: Multiple sempro attempts ===
        $this->seedMultiSemproAttempts($admin, $dosen1, $dosen2, $ilkom);

        // === 7. laila@sita.test: Historical inactive project + new active title review ===
        $this->seedHistoricalRestart($admin, $dosen1, $dosen2, $ilkom);

        // === 8. putra@sita.test: Supervisor rotation after sempro approval ===
        $this->seedSupervisorRotation($admin, $dosen1, $dosen2, $dosen3, $dosen4, $ilkom);

        app(LegacyThesisProjectBackfillService::class)->backfill();

        $this->seedFutureSidangScenarios($admin, $dosen1, $dosen2, $dosen3, $dosen4);
        $this->rebuildProjectTimelines();
    }

    private function seedFullyProgressed(User $admin, User $dosen1, User $dosen2, ?User $dosen3, ?User $dosen4, \App\Models\ProgramStudi $prodi): void
    {
        $student = User::query()->where('email', 'mahasiswa@sita.test')->first();

        if ($student === null) {
            return;
        }

        $submission = ThesisSubmission::query()->updateOrCreate(
            ['student_user_id' => $student->id, 'title_id' => 'Sistem Rekomendasi Topik Bimbingan Berbasis Riwayat Interaksi'],
            [
                'program_studi_id' => $prodi->id,
                'title_en' => 'Mentoring Topic Recommendation System Based on Interaction History',
                'proposal_summary' => 'Sistem rekomendasi topik bimbingan untuk meningkatkan efektivitas konsultasi mahasiswa.',
                'status' => ThesisSubmissionStatus::PembimbingDitetapkan->value,
                'is_active' => true,
                'submitted_at' => now()->subDays(45),
                'approved_at' => now()->subDays(20),
                'approved_by' => $admin->id,
            ],
        );

        $sempro = Sempro::query()->updateOrCreate(
            ['thesis_submission_id' => $submission->id],
            [
                'status' => SemproStatus::Approved->value,
                'scheduled_for' => now()->subDays(25)->setTime(9, 0),
                'location' => 'Ruang Seminar 2',
                'mode' => 'offline',
                'approved_at' => now()->subDays(20),
                'approved_by' => $admin->id,
                'created_by' => $admin->id,
            ],
        );

        SemproExaminer::query()->updateOrCreate(
            ['sempro_id' => $sempro->id, 'examiner_order' => 1],
            [
                'examiner_user_id' => $dosen1->id,
                'decision' => SemproExaminerDecision::Approved->value,
                'decision_notes' => 'Proposal sudah baik, lanjutkan ke pembimbingan.',
                'score' => 82.50,
                'decided_at' => now()->subDays(22),
                'assigned_by' => $admin->id,
            ],
        );

        SemproExaminer::query()->updateOrCreate(
            ['sempro_id' => $sempro->id, 'examiner_order' => 2],
            [
                'examiner_user_id' => $dosen2->id,
                'decision' => SemproExaminerDecision::Approved->value,
                'decision_notes' => 'Metodologi cukup jelas. Setuju untuk lanjut.',
                'score' => 78.00,
                'decided_at' => now()->subDays(21),
                'assigned_by' => $admin->id,
            ],
        );

        // Assign pembimbing (bypass model validation for seeding)
        $pembimbing1 = $dosen3 ?? $dosen1;
        $pembimbing2 = $dosen4 ?? $dosen2;

        MentorshipAssignment::withoutEvents(function () use ($student, $pembimbing1, $pembimbing2, $admin): void {
            MentorshipAssignment::query()->firstOrCreate([
                'student_user_id' => $student->id,
                'advisor_type' => AdvisorType::Primary->value,
                'status' => AssignmentStatus::Active->value,
            ], [
                'lecturer_user_id' => $pembimbing1->id,
                'assigned_by' => $admin->id,
                'started_at' => now()->subDays(18),
                'notes' => 'Pembimbing utama untuk skripsi sistem rekomendasi.',
            ]);

            MentorshipAssignment::query()->firstOrCreate([
                'student_user_id' => $student->id,
                'advisor_type' => AdvisorType::Secondary->value,
                'status' => AssignmentStatus::Active->value,
            ], [
                'lecturer_user_id' => $pembimbing2->id,
                'assigned_by' => $admin->id,
                'started_at' => now()->subDays(18),
                'notes' => 'Pembimbing kedua.',
            ]);
        });

        // Create penguji thread for sempro with examiners (Budi & Ratna)
        $this->createPengujiThread($sempro, $student, [$dosen1, $dosen2]);
    }

    private function seedSemproScheduled(User $admin, User $dosen1, User $dosen2, \App\Models\ProgramStudi $prodi): void
    {
        $student = User::query()->where('email', 'akbar@sita.test')->first();

        if ($student === null) {
            return;
        }

        $submission = ThesisSubmission::query()->updateOrCreate(
            ['student_user_id' => $student->id, 'title_id' => 'Analisis Sentimen Media Sosial Menggunakan Deep Learning'],
            [
                'program_studi_id' => $prodi->id,
                'title_en' => 'Social Media Sentiment Analysis Using Deep Learning',
                'proposal_summary' => 'Menganalisis sentimen publik di media sosial menggunakan teknik deep learning LSTM dan BERT.',
                'status' => ThesisSubmissionStatus::SemproDijadwalkan->value,
                'is_active' => true,
                'submitted_at' => now()->subDays(10),
            ],
        );

        $sempro = Sempro::query()->updateOrCreate(
            ['thesis_submission_id' => $submission->id],
            [
                'status' => SemproStatus::Scheduled->value,
                'scheduled_for' => now()->addDays(7)->setTime(9, 0),
                'location' => 'Ruang Seminar 1',
                'mode' => 'offline',
                'created_by' => $admin->id,
            ],
        );

        SemproExaminer::query()->updateOrCreate(
            ['sempro_id' => $sempro->id, 'examiner_order' => 1],
            [
                'examiner_user_id' => $dosen1->id,
                'decision' => SemproExaminerDecision::Pending->value,
                'assigned_by' => $admin->id,
            ],
        );

        SemproExaminer::query()->updateOrCreate(
            ['sempro_id' => $sempro->id, 'examiner_order' => 2],
            [
                'examiner_user_id' => $dosen2->id,
                'decision' => SemproExaminerDecision::Pending->value,
                'assigned_by' => $admin->id,
            ],
        );

        // Create penguji thread for scheduled sempro
        $this->createPengujiThread($sempro, $student, [$dosen1, $dosen2]);
    }

    private function seedSemproRevision(User $admin, User $dosen1, User $dosen2, \App\Models\ProgramStudi $prodi): void
    {
        $student = User::query()->where('email', 'nadia@sita.test')->first();

        if ($student === null) {
            return;
        }

        $submission = ThesisSubmission::query()->updateOrCreate(
            ['student_user_id' => $student->id, 'title_id' => 'Pengembangan Aplikasi E-Learning Berbasis Gamifikasi'],
            [
                'program_studi_id' => $prodi->id,
                'title_en' => 'Development of Gamification-Based E-Learning Application',
                'proposal_summary' => 'Membangun platform e-learning dengan elemen gamifikasi untuk meningkatkan motivasi belajar.',
                'status' => ThesisSubmissionStatus::RevisiSempro->value,
                'is_active' => true,
                'submitted_at' => now()->subDays(30),
            ],
        );

        $sempro = Sempro::query()->updateOrCreate(
            ['thesis_submission_id' => $submission->id],
            [
                'status' => SemproStatus::RevisionOpen->value,
                'scheduled_for' => now()->subDays(5)->setTime(13, 0),
                'location' => 'Ruang Seminar 3',
                'mode' => 'online',
                'revision_due_at' => now()->addDays(14),
                'created_by' => $admin->id,
            ],
        );

        SemproExaminer::query()->updateOrCreate(
            ['sempro_id' => $sempro->id, 'examiner_order' => 1],
            [
                'examiner_user_id' => $dosen1->id,
                'decision' => SemproExaminerDecision::NeedsRevision->value,
                'decision_notes' => 'Metodologi kurang jelas, perlu diperbaiki bagian analisis data.',
                'score' => 65.00,
                'decided_at' => now()->subDays(4),
                'assigned_by' => $admin->id,
            ],
        );

        SemproExaminer::query()->updateOrCreate(
            ['sempro_id' => $sempro->id, 'examiner_order' => 2],
            [
                'examiner_user_id' => $dosen2->id,
                'decision' => SemproExaminerDecision::Approved->value,
                'decision_notes' => 'Secara umum baik, tapi ikuti catatan penguji 1.',
                'score' => 72.50,
                'decided_at' => now()->subDays(4),
                'assigned_by' => $admin->id,
            ],
        );

        SemproRevision::query()->updateOrCreate(
            ['sempro_id' => $sempro->id, 'notes' => 'Perbaiki metodologi: tambahkan penjelasan teknik pengumpulan data dan analisis.'],
            [
                'status' => 'open',
                'due_at' => now()->addDays(14),
                'requested_by_user_id' => $dosen1->id,
            ],
        );

        // Create penguji thread for revision sempro
        $thread = $this->createPengujiThread($sempro, $student, [$dosen1, $dosen2]);

        if ($thread !== null) {
            // Add sample conversation
            $thread->messages()->create([
                'sender_user_id' => $dosen1->id,
                'message_type' => 'text',
                'message' => 'Mohon perbaiki bagian metodologi, terutama teknik pengumpulan data.',
                'sent_at' => now()->subDays(3),
            ]);

            $thread->messages()->create([
                'sender_user_id' => $student->id,
                'message_type' => 'text',
                'message' => 'Baik pak, akan saya perbaiki segera. Terima kasih atas masukannya.',
                'sent_at' => now()->subDays(2),
            ]);
        }
    }

    private function seedMenungguPersetujuan(\App\Models\ProgramStudi $prodi): void
    {
        $student = User::query()->where('email', 'rizky@sita.test')->first();

        if ($student === null) {
            return;
        }

        ThesisSubmission::query()->updateOrCreate(
            ['student_user_id' => $student->id, 'title_id' => 'Implementasi Blockchain untuk Sistem Voting Digital'],
            [
                'program_studi_id' => $prodi->id,
                'title_en' => 'Blockchain Implementation for Digital Voting System',
                'proposal_summary' => 'Membangun sistem voting digital berbasis blockchain untuk meningkatkan transparansi dan keamanan.',
                'status' => ThesisSubmissionStatus::MenungguPersetujuan->value,
                'is_active' => true,
                'submitted_at' => now()->subDays(3),
            ],
        );
    }

    private function seedSemproApproved(User $admin, User $dosen1, User $dosen2, \App\Models\ProgramStudi $prodi): void
    {
        $student = User::query()->where('email', 'siti@sita.test')->first();

        if ($student === null) {
            return;
        }

        $submission = ThesisSubmission::query()->updateOrCreate(
            ['student_user_id' => $student->id, 'title_id' => 'Sistem Deteksi Intrusi Jaringan Menggunakan Machine Learning'],
            [
                'program_studi_id' => $prodi->id,
                'title_en' => 'Network Intrusion Detection System Using Machine Learning',
                'proposal_summary' => 'Merancang sistem deteksi intrusi jaringan yang memanfaatkan algoritma machine learning.',
                'status' => ThesisSubmissionStatus::SemproSelesai->value,
                'is_active' => true,
                'submitted_at' => now()->subDays(35),
                'approved_at' => now()->subDays(10),
                'approved_by' => $admin->id,
            ],
        );

        $sempro = Sempro::query()->updateOrCreate(
            ['thesis_submission_id' => $submission->id],
            [
                'status' => SemproStatus::Approved->value,
                'scheduled_for' => now()->subDays(15)->setTime(10, 0),
                'location' => 'Ruang Seminar 1',
                'mode' => 'offline',
                'approved_at' => now()->subDays(10),
                'approved_by' => $admin->id,
                'created_by' => $admin->id,
            ],
        );

        SemproExaminer::query()->updateOrCreate(
            ['sempro_id' => $sempro->id, 'examiner_order' => 1],
            [
                'examiner_user_id' => $dosen1->id,
                'decision' => SemproExaminerDecision::Approved->value,
                'decision_notes' => 'Topik sangat relevan. Lanjutkan.',
                'score' => 85.00,
                'decided_at' => now()->subDays(12),
                'assigned_by' => $admin->id,
            ],
        );

        SemproExaminer::query()->updateOrCreate(
            ['sempro_id' => $sempro->id, 'examiner_order' => 2],
            [
                'examiner_user_id' => $dosen2->id,
                'decision' => SemproExaminerDecision::Approved->value,
                'decision_notes' => 'Setuju. Dataset sudah jelas.',
                'score' => 80.00,
                'decided_at' => now()->subDays(11),
                'assigned_by' => $admin->id,
            ],
        );
    }

    private function seedMultiSemproAttempts(User $admin, User $dosen1, User $dosen2, \App\Models\ProgramStudi $prodi): void
    {
        $student = User::query()->where('email', 'bagas@sita.test')->first();

        if ($student === null) {
            return;
        }

        $submission = ThesisSubmission::query()->updateOrCreate(
            ['student_user_id' => $student->id, 'title_id' => 'Platform Asesmen Otomatis Kualitas Proposal Skripsi'],
            [
                'program_studi_id' => $prodi->id,
                'title_en' => 'Automated Thesis Proposal Quality Assessment Platform',
                'proposal_summary' => 'Membangun platform penilaian otomatis kualitas proposal skripsi dengan analisis rubrik.',
                'status' => ThesisSubmissionStatus::SemproDijadwalkan->value,
                'is_active' => true,
                'submitted_at' => now()->subDays(28),
                'approved_at' => now()->subDays(25),
                'approved_by' => $admin->id,
            ],
        );

        $semproAttemptOne = Sempro::query()->updateOrCreate(
            [
                'thesis_submission_id' => $submission->id,
                'location' => 'Ruang Seminar 4A',
            ],
            [
                'status' => SemproStatus::Approved->value,
                'scheduled_for' => now()->subDays(18)->setTime(9, 30),
                'mode' => 'offline',
                'approved_at' => now()->subDays(17),
                'approved_by' => $admin->id,
                'created_by' => $admin->id,
            ],
        );

        SemproExaminer::query()->updateOrCreate(
            ['sempro_id' => $semproAttemptOne->id, 'examiner_order' => 1],
            [
                'examiner_user_id' => $dosen1->id,
                'decision' => SemproExaminerDecision::Approved->value,
                'decision_notes' => 'Attempt pertama dinyatakan layak, tetapi perlu penjadwalan ulang presentasi akhir.',
                'score' => 79.50,
                'decided_at' => now()->subDays(17),
                'assigned_by' => $admin->id,
            ],
        );

        SemproExaminer::query()->updateOrCreate(
            ['sempro_id' => $semproAttemptOne->id, 'examiner_order' => 2],
            [
                'examiner_user_id' => $dosen2->id,
                'decision' => SemproExaminerDecision::Approved->value,
                'decision_notes' => 'Attempt pertama selesai, lanjut ke penjadwalan ulang presentasi lanjutan.',
                'score' => 81.00,
                'decided_at' => now()->subDays(17),
                'assigned_by' => $admin->id,
            ],
        );

        $semproAttemptTwo = Sempro::query()->updateOrCreate(
            [
                'thesis_submission_id' => $submission->id,
                'location' => 'Ruang Seminar 4B',
            ],
            [
                'status' => SemproStatus::Scheduled->value,
                'scheduled_for' => now()->addDays(8)->setTime(13, 30),
                'mode' => 'online',
                'approved_at' => null,
                'approved_by' => null,
                'created_by' => $admin->id,
            ],
        );

        SemproExaminer::query()->updateOrCreate(
            ['sempro_id' => $semproAttemptTwo->id, 'examiner_order' => 1],
            [
                'examiner_user_id' => $dosen1->id,
                'decision' => SemproExaminerDecision::Pending->value,
                'assigned_by' => $admin->id,
            ],
        );

        SemproExaminer::query()->updateOrCreate(
            ['sempro_id' => $semproAttemptTwo->id, 'examiner_order' => 2],
            [
                'examiner_user_id' => $dosen2->id,
                'decision' => SemproExaminerDecision::Pending->value,
                'assigned_by' => $admin->id,
            ],
        );

        $this->createPengujiThread($semproAttemptTwo, $student, [$dosen1, $dosen2]);
    }

    private function seedHistoricalRestart(User $admin, User $dosen1, User $dosen2, \App\Models\ProgramStudi $prodi): void
    {
        $student = User::query()->where('email', 'laila@sita.test')->first();

        if ($student === null) {
            return;
        }

        $archivedSubmission = ThesisSubmission::query()->updateOrCreate(
            ['student_user_id' => $student->id, 'title_id' => 'Sistem Rekomendasi Ruang Belajar Kolaboratif Berbasis IoT'],
            [
                'program_studi_id' => $prodi->id,
                'title_en' => 'IoT-Based Collaborative Study Space Recommendation System',
                'proposal_summary' => 'Proyek lama yang telah melewati sempro dan diarsipkan karena mahasiswa mengganti arah penelitian.',
                'status' => ThesisSubmissionStatus::SemproSelesai->value,
                'is_active' => false,
                'submitted_at' => now()->subDays(160),
                'approved_at' => now()->subDays(130),
                'approved_by' => $admin->id,
            ],
        );

        $archivedSempro = Sempro::query()->updateOrCreate(
            ['thesis_submission_id' => $archivedSubmission->id],
            [
                'status' => SemproStatus::Approved->value,
                'scheduled_for' => now()->subDays(138)->setTime(10, 0),
                'location' => 'Ruang Seminar 2',
                'mode' => 'offline',
                'approved_at' => now()->subDays(135),
                'approved_by' => $admin->id,
                'created_by' => $admin->id,
            ],
        );

        SemproExaminer::query()->updateOrCreate(
            ['sempro_id' => $archivedSempro->id, 'examiner_order' => 1],
            [
                'examiner_user_id' => $dosen1->id,
                'decision' => SemproExaminerDecision::Approved->value,
                'decision_notes' => 'Sempro proyek lama disetujui.',
                'score' => 78.00,
                'decided_at' => now()->subDays(135),
                'assigned_by' => $admin->id,
            ],
        );

        SemproExaminer::query()->updateOrCreate(
            ['sempro_id' => $archivedSempro->id, 'examiner_order' => 2],
            [
                'examiner_user_id' => $dosen2->id,
                'decision' => SemproExaminerDecision::Approved->value,
                'decision_notes' => 'Dokumen proyek lama cukup baik.',
                'score' => 80.50,
                'decided_at' => now()->subDays(135),
                'assigned_by' => $admin->id,
            ],
        );

        ThesisSubmission::query()->updateOrCreate(
            ['student_user_id' => $student->id, 'title_id' => 'Asisten Akademik Berbasis Retrieval-Augmented Generation'],
            [
                'program_studi_id' => $prodi->id,
                'title_en' => 'Retrieval-Augmented Generation Academic Assistant',
                'proposal_summary' => 'Attempt baru setelah mahasiswa merombak total topik tugas akhir.',
                'status' => ThesisSubmissionStatus::MenungguPersetujuan->value,
                'is_active' => true,
                'submitted_at' => now()->subDays(6),
                'approved_at' => null,
                'approved_by' => null,
            ],
        );
    }

    private function seedSupervisorRotation(
        User $admin,
        User $dosen1,
        User $dosen2,
        ?User $dosen3,
        ?User $dosen4,
        \App\Models\ProgramStudi $prodi,
    ): void {
        $student = User::query()->where('email', 'putra@sita.test')->first();

        if ($student === null) {
            return;
        }

        $submission = ThesisSubmission::query()->updateOrCreate(
            ['student_user_id' => $student->id, 'title_id' => 'Deteksi Dini Risiko Dropout Mahasiswa Menggunakan Pembelajaran Mesin'],
            [
                'program_studi_id' => $prodi->id,
                'title_en' => 'Early Detection of Student Dropout Risk Using Machine Learning',
                'proposal_summary' => 'Model prediksi risiko dropout untuk membantu intervensi akademik lebih dini.',
                'status' => ThesisSubmissionStatus::PembimbingDitetapkan->value,
                'is_active' => true,
                'submitted_at' => now()->subDays(55),
                'approved_at' => now()->subDays(36),
                'approved_by' => $admin->id,
            ],
        );

        $sempro = Sempro::query()->updateOrCreate(
            ['thesis_submission_id' => $submission->id],
            [
                'status' => SemproStatus::Approved->value,
                'scheduled_for' => now()->subDays(40)->setTime(14, 0),
                'location' => 'Ruang Seminar 5',
                'mode' => 'offline',
                'approved_at' => now()->subDays(36),
                'approved_by' => $admin->id,
                'created_by' => $admin->id,
            ],
        );

        SemproExaminer::query()->updateOrCreate(
            ['sempro_id' => $sempro->id, 'examiner_order' => 1],
            [
                'examiner_user_id' => $dosen1->id,
                'decision' => SemproExaminerDecision::Approved->value,
                'decision_notes' => 'Analisis sudah matang.',
                'score' => 84.00,
                'decided_at' => now()->subDays(36),
                'assigned_by' => $admin->id,
            ],
        );

        SemproExaminer::query()->updateOrCreate(
            ['sempro_id' => $sempro->id, 'examiner_order' => 2],
            [
                'examiner_user_id' => $dosen2->id,
                'decision' => SemproExaminerDecision::Approved->value,
                'decision_notes' => 'Lanjutkan ke tahap penelitian.',
                'score' => 82.00,
                'decided_at' => now()->subDays(36),
                'assigned_by' => $admin->id,
            ],
        );

        $primaryReplacement = $dosen3 ?? $dosen1;
        $secondaryLecturer = $dosen4 ?? $dosen2;

        MentorshipAssignment::withoutEvents(function () use ($admin, $dosen1, $primaryReplacement, $secondaryLecturer, $student): void {
            MentorshipAssignment::query()->updateOrCreate(
                [
                    'student_user_id' => $student->id,
                    'lecturer_user_id' => $dosen1->id,
                    'advisor_type' => AdvisorType::Primary->value,
                    'status' => AssignmentStatus::Ended->value,
                ],
                [
                    'assigned_by' => $admin->id,
                    'started_at' => now()->subDays(34),
                    'ended_at' => now()->subDays(22),
                    'notes' => 'Pembimbing utama awal sebelum rotasi.',
                ],
            );

            MentorshipAssignment::query()->updateOrCreate(
                [
                    'student_user_id' => $student->id,
                    'lecturer_user_id' => $primaryReplacement->id,
                    'advisor_type' => AdvisorType::Primary->value,
                    'status' => AssignmentStatus::Active->value,
                ],
                [
                    'assigned_by' => $admin->id,
                    'started_at' => now()->subDays(21),
                    'notes' => 'Rotasi pembimbing utama karena penyesuaian topik riset.',
                ],
            );

            MentorshipAssignment::query()->updateOrCreate(
                [
                    'student_user_id' => $student->id,
                    'lecturer_user_id' => $secondaryLecturer->id,
                    'advisor_type' => AdvisorType::Secondary->value,
                    'status' => AssignmentStatus::Active->value,
                ],
                [
                    'assigned_by' => $admin->id,
                    'started_at' => now()->subDays(21),
                    'notes' => 'Pembimbing kedua aktif setelah rotasi.',
                ],
            );
        });
    }

    private function seedFutureSidangScenarios(
        User $admin,
        User $dosen1,
        User $dosen2,
        ?User $dosen3,
        ?User $dosen4,
    ): void {
        $chair = $dosen3 ?? $dosen1;
        $secretary = $dosen4 ?? $dosen2;

        $sidangScheduledProject = ThesisProject::query()
            ->whereHas('student', static fn($query) => $query->where('email', 'putra@sita.test'))
            ->with('latestTitle')
            ->first();

        if ($sidangScheduledProject !== null) {
            $sidang = ThesisDefense::query()->updateOrCreate(
                [
                    'project_id' => $sidangScheduledProject->id,
                    'type' => 'sidang',
                    'attempt_no' => 1,
                ],
                [
                    'title_version_id' => $sidangScheduledProject->latestTitle?->id,
                    'status' => 'scheduled',
                    'result' => 'pending',
                    'scheduled_for' => now()->addDays(14)->setTime(9, 0),
                    'location' => 'Ruang Sidang A',
                    'mode' => 'offline',
                    'created_by' => $admin->id,
                    'decided_by' => null,
                    'decision_at' => null,
                    'notes' => 'Sidang perdana terjadwal untuk proyek aktif dengan pembimbing yang sudah lengkap.',
                ],
            );

            $this->upsertDefenseExaminer($sidang, $chair, 1, 'chair', 'pending', null, 'Ketua sidang.');
            $this->upsertDefenseExaminer($sidang, $secretary, 2, 'secretary', 'pending', null, 'Sekretaris sidang.');
            $this->upsertDefenseExaminer($sidang, $dosen2, 3, 'examiner', 'pending', null, 'Penguji eksternal sidang.');

            $sidangScheduledProject->forceFill([
                'phase' => 'sidang',
                'state' => 'active',
            ])->save();
        }

        $completedProject = ThesisProject::query()
            ->whereHas('student', static fn($query) => $query->where('email', 'laila@sita.test'))
            ->orderBy('started_at')
            ->with('latestTitle')
            ->first();

        if ($completedProject !== null) {
            $sidang = ThesisDefense::query()->updateOrCreate(
                [
                    'project_id' => $completedProject->id,
                    'type' => 'sidang',
                    'attempt_no' => 1,
                ],
                [
                    'title_version_id' => $completedProject->latestTitle?->id,
                    'status' => 'completed',
                    'result' => 'pass_with_revision',
                    'scheduled_for' => now()->subDays(120)->setTime(10, 0),
                    'location' => 'Ruang Sidang B',
                    'mode' => 'offline',
                    'created_by' => $admin->id,
                    'decided_by' => $admin->id,
                    'decision_at' => now()->subDays(120)->setTime(12, 0),
                    'notes' => 'Sidang historis untuk proyek yang telah ditutup.',
                ],
            );

            $this->upsertDefenseExaminer($sidang, $chair, 1, 'chair', 'pass', 83.50, 'Ketua menyetujui dengan revisi minor.', $admin->id, now()->subDays(120)->setTime(12, 5));
            $this->upsertDefenseExaminer($sidang, $secretary, 2, 'secretary', 'pass_with_revision', 81.00, 'Sekretaris menambahkan catatan format.', $admin->id, now()->subDays(120)->setTime(12, 7));
            $this->upsertDefenseExaminer($sidang, $dosen1, 3, 'examiner', 'pass', 84.00, 'Penguji menyetujui hasil sidang.', $admin->id, now()->subDays(120)->setTime(12, 10));

            ThesisRevision::query()->updateOrCreate(
                [
                    'project_id' => $completedProject->id,
                    'defense_id' => $sidang->id,
                    'notes' => 'Finalisasi format penulisan dan kelengkapan lampiran.',
                ],
                [
                    'requested_by_user_id' => $secretary->id,
                    'status' => 'resolved',
                    'due_at' => now()->subDays(112),
                    'submitted_at' => now()->subDays(111),
                    'resolved_at' => now()->subDays(109),
                    'resolved_by_user_id' => $admin->id,
                    'resolution_notes' => 'Revisi final sudah diterima.',
                ],
            );

            $completedProject->forceFill([
                'phase' => 'completed',
                'state' => 'completed',
                'completed_at' => now()->subDays(108),
                'closed_by' => $admin->id,
            ])->save();
        }
    }

    private function rebuildProjectTimelines(): void
    {
        ThesisProject::query()
            ->with([
                'titles.decidedBy',
                'supervisorAssignments.lecturer',
                'defenses.examiners.lecturer',
                'defenses.revisions',
                'revisions',
            ])
            ->get()
            ->each(function (ThesisProject $project): void {
                $project->events()->delete();

                $this->appendProjectEvent(
                    $project,
                    'project_created',
                    'Proyek tugas akhir dimulai',
                    'Record proyek tugas akhir dibuat.',
                    $project->created_by,
                    $project->started_at ?? $project->created_at,
                );

                foreach ($project->titles->sortBy('version_no') as $title) {
                    $this->appendProjectEvent(
                        $project,
                        'title_submitted',
                        'Judul diajukan',
                        $title->title_id,
                        $title->submitted_by_user_id,
                        $title->submitted_at,
                    );

                    if ($title->decided_at !== null) {
                        $this->appendProjectEvent(
                            $project,
                            $title->status === 'approved' ? 'title_approved' : 'title_reviewed',
                            $title->status === 'approved' ? 'Judul disetujui' : 'Judul direview',
                            $title->title_id,
                            $title->decided_by_user_id,
                            $title->decided_at,
                        );
                    }
                }

                foreach ($project->supervisorAssignments->sortBy('started_at') as $assignment) {
                    $this->appendProjectEvent(
                        $project,
                        'supervisor_assigned',
                        'Pembimbing ditetapkan',
                        sprintf('%s - %s', $assignment->role, $assignment->lecturer?->name ?? '-'),
                        $assignment->assigned_by,
                        $assignment->started_at ?? $assignment->created_at,
                    );

                    if ($assignment->ended_at !== null) {
                        $this->appendProjectEvent(
                            $project,
                            'supervisor_ended',
                            'Pembimbing diakhiri',
                            sprintf('%s - %s', $assignment->role, $assignment->lecturer?->name ?? '-'),
                            $assignment->assigned_by,
                            $assignment->ended_at,
                        );
                    }
                }

                foreach ($project->defenses->sortBy(fn(ThesisDefense $defense): string => $defense->type.$defense->attempt_no) as $defense) {
                    $this->appendProjectEvent(
                        $project,
                        $defense->type.'_scheduled',
                        strtoupper($defense->type).' dijadwalkan',
                        sprintf('Attempt #%d di %s.', $defense->attempt_no, $defense->location ?? 'lokasi belum ditentukan'),
                        $defense->created_by,
                        $defense->scheduled_for,
                    );

                    if ($defense->decision_at !== null) {
                        $this->appendProjectEvent(
                            $project,
                            $defense->type.'_completed',
                            strtoupper($defense->type).' selesai',
                            sprintf('Attempt #%d selesai dengan hasil %s.', $defense->attempt_no, $defense->result),
                            $defense->decided_by,
                            $defense->decision_at,
                        );
                    }
                }

                foreach ($project->revisions->sortBy('created_at') as $revision) {
                    $this->appendProjectEvent(
                        $project,
                        'revision_opened',
                        'Revisi dibuka',
                        $revision->notes,
                        $revision->requested_by_user_id,
                        $revision->created_at,
                    );

                    if ($revision->resolved_at !== null) {
                        $this->appendProjectEvent(
                            $project,
                            'revision_resolved',
                            'Revisi diselesaikan',
                            $revision->resolution_notes ?? $revision->notes,
                            $revision->resolved_by_user_id,
                            $revision->resolved_at,
                        );
                    }
                }

                if ($project->completed_at !== null) {
                    $this->appendProjectEvent(
                        $project,
                        'project_closed',
                        'Proyek ditutup',
                        'Proyek tugas akhir dinyatakan selesai.',
                        $project->closed_by,
                        $project->completed_at,
                    );
                }
            });
    }

    private function appendProjectEvent(
        ThesisProject $project,
        string $eventType,
        string $label,
        ?string $description,
        ?int $actorUserId,
        ?\DateTimeInterface $occurredAt,
    ): void {
        if ($occurredAt === null) {
            return;
        }

        ThesisProjectEvent::query()->create([
            'project_id' => $project->id,
            'actor_user_id' => $actorUserId,
            'event_type' => $eventType,
            'label' => $label,
            'description' => $description,
            'payload' => null,
            'occurred_at' => $occurredAt,
        ]);
    }

    private function upsertDefenseExaminer(
        ThesisDefense $defense,
        User $lecturer,
        int $orderNo,
        string $role,
        string $decision,
        ?float $score = null,
        ?string $notes = null,
        ?int $assignedBy = null,
        ?\DateTimeInterface $decidedAt = null,
    ): void {
        ThesisDefenseExaminer::query()->updateOrCreate(
            [
                'defense_id' => $defense->id,
                'lecturer_user_id' => $lecturer->id,
            ],
            [
                'role' => $role,
                'order_no' => $orderNo,
                'decision' => $decision,
                'score' => $score,
                'notes' => $notes,
                'decided_at' => $decidedAt,
                'assigned_by' => $assignedBy,
            ],
        );
    }

    /**
     * @param  User[]  $examiners
     */
    private function createPengujiThread(Sempro $sempro, User $student, array $examiners): ?MentorshipChatThread
    {
        $thread = MentorshipChatThread::query()->firstOrCreate(
            [
                'student_user_id' => $student->id,
                'type' => 'sempro',
                'context_id' => $sempro->id,
            ],
            [
                'label' => 'Sempro',
            ],
        );

        // Add student participant
        MentorshipChatThreadParticipant::query()->firstOrCreate([
            'thread_id' => $thread->id,
            'user_id' => $student->id,
        ], [
            'role' => 'student',
        ]);

        // Add examiner participants
        foreach ($examiners as $examiner) {
            MentorshipChatThreadParticipant::query()->firstOrCreate([
                'thread_id' => $thread->id,
                'user_id' => $examiner->id,
            ], [
                'role' => 'examiner',
            ]);
        }

        // System welcome message (only once)
        if ($thread->messages()->count() === 0) {
            $thread->messages()->create([
                'sender_user_id' => null,
                'message_type' => 'text',
                'message' => 'Thread Seminar Proposal telah dibuat. Silahkan berdiskusi mengenai sempro di sini.',
                'sent_at' => $sempro->scheduled_for ?? now(),
            ]);
        }

        return $thread;
    }
}
