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
use App\Models\ThesisSubmission;
use App\Models\User;
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
 */
class ThesisWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@sita.test')->first();

        if ($admin === null) {
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
        $this->seedFullyProgressed($admin, $dosen1, $dosen2, $dosen3, $dosen4);

        // === 2. akbar@sita.test: Sempro dijadwalkan ===
        $this->seedSemproScheduled($admin, $dosen1, $dosen2);

        // === 3. nadia@sita.test: Sempro revisi ===
        $this->seedSemproRevision($admin, $dosen1, $dosen2);

        // === 4. rizky@sita.test: Menunggu persetujuan ===
        $this->seedMenungguPersetujuan();

        // === 5. siti@sita.test: Sempro approved, belum ada pembimbing ===
        $this->seedSemproApproved($admin, $dosen1, $dosen2);
    }

    private function seedFullyProgressed(User $admin, User $dosen1, User $dosen2, ?User $dosen3, ?User $dosen4): void
    {
        $student = User::query()->where('email', 'mahasiswa@sita.test')->first();

        if ($student === null) {
            return;
        }

        $submission = ThesisSubmission::query()->updateOrCreate(
            ['student_user_id' => $student->id, 'title_id' => 'Sistem Rekomendasi Topik Bimbingan Berbasis Riwayat Interaksi'],
            [
                'program_studi' => 'Informatika',
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
    }

    private function seedSemproScheduled(User $admin, User $dosen1, User $dosen2): void
    {
        $student = User::query()->where('email', 'akbar@sita.test')->first();

        if ($student === null) {
            return;
        }

        $submission = ThesisSubmission::query()->updateOrCreate(
            ['student_user_id' => $student->id, 'title_id' => 'Analisis Sentimen Media Sosial Menggunakan Deep Learning'],
            [
                'program_studi' => 'Informatika',
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

    private function seedSemproRevision(User $admin, User $dosen1, User $dosen2): void
    {
        $student = User::query()->where('email', 'nadia@sita.test')->first();

        if ($student === null) {
            return;
        }

        $submission = ThesisSubmission::query()->updateOrCreate(
            ['student_user_id' => $student->id, 'title_id' => 'Pengembangan Aplikasi E-Learning Berbasis Gamifikasi'],
            [
                'program_studi' => 'Informatika',
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

    private function seedMenungguPersetujuan(): void
    {
        $student = User::query()->where('email', 'rizky@sita.test')->first();

        if ($student === null) {
            return;
        }

        ThesisSubmission::query()->updateOrCreate(
            ['student_user_id' => $student->id, 'title_id' => 'Implementasi Blockchain untuk Sistem Voting Digital'],
            [
                'program_studi' => 'Informatika',
                'title_en' => 'Blockchain Implementation for Digital Voting System',
                'proposal_summary' => 'Membangun sistem voting digital berbasis blockchain untuk meningkatkan transparansi dan keamanan.',
                'status' => ThesisSubmissionStatus::MenungguPersetujuan->value,
                'is_active' => true,
                'submitted_at' => now()->subDays(3),
            ],
        );
    }

    private function seedSemproApproved(User $admin, User $dosen1, User $dosen2): void
    {
        $student = User::query()->where('email', 'siti@sita.test')->first();

        if ($student === null) {
            return;
        }

        $submission = ThesisSubmission::query()->updateOrCreate(
            ['student_user_id' => $student->id, 'title_id' => 'Sistem Deteksi Intrusi Jaringan Menggunakan Machine Learning'],
            [
                'program_studi' => 'Informatika',
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
