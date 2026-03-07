<?php

namespace Database\Seeders;

use App\Enums\AdvisorType;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipChatThreadParticipant;
use App\Models\MentorshipDocument;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisDocument;
use App\Models\ThesisProject;
use App\Models\ThesisProjectEvent;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisRevision;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class ThesisWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@sita.test')->first();
        $ilkom = ProgramStudi::query()->where('slug', 'ilkom')->first();
        $dosen1 = User::query()->where('email', 'dosen@sita.test')->first();
        $dosen2 = User::query()->where('email', 'dosen2@sita.test')->first();
        $dosen3 = User::query()->where('email', 'dosen3@sita.test')->first();
        $dosen4 = User::query()->where('email', 'dosen4@sita.test')->first();

        if (! $admin instanceof User || ! $ilkom instanceof ProgramStudi || ! $dosen1 instanceof User || ! $dosen2 instanceof User) {
            return;
        }

        $this->seedFullyProgressed($admin, $ilkom, $dosen1, $dosen2, $dosen3 ?? $dosen1, $dosen4 ?? $dosen2);
        $this->seedSemproScheduled($admin, $ilkom, $dosen1, $dosen2);
        $this->seedSemproRevision($admin, $ilkom, $dosen1, $dosen2);
        $this->seedTitleReviewPending($ilkom);
        $this->seedSemproPassedWithoutSupervisors($admin, $ilkom, $dosen1, $dosen2);
        $this->seedMultipleSemproAttempts($admin, $ilkom, $dosen1, $dosen2);
        $this->seedHistoricalRestart($admin, $ilkom, $dosen1, $dosen2, $dosen3 ?? $dosen1, $dosen4 ?? $dosen2);
        $this->seedSupervisorRotationAndSidang($admin, $ilkom, $dosen1, $dosen2, $dosen3 ?? $dosen1, $dosen4 ?? $dosen2);
    }

    private function seedFullyProgressed(User $admin, ProgramStudi $prodi, User $examinerOne, User $examinerTwo, User $supervisorOne, User $supervisorTwo): void
    {
        $student = $this->student('mahasiswa@sita.test');

        if (! $student instanceof User) {
            return;
        }

        $startedAt = CarbonImmutable::parse('2026-01-10 09:00:00');
        $submittedAt = CarbonImmutable::parse('2026-01-10 10:00:00');
        $approvedAt = CarbonImmutable::parse('2026-01-14 13:00:00');
        $semproAt = CarbonImmutable::parse('2026-01-28 09:00:00');

        $project = $this->upsertProject($student, $prodi, $startedAt, 'research', 'active', null, null, 'Proyek aktif setelah sempro disetujui.');
        $title = $this->upsertTitle(
            $project,
            versionNo: 1,
            titleId: 'Sistem Rekomendasi Topik Bimbingan Berbasis Riwayat Interaksi',
            titleEn: 'Mentoring Topic Recommendation System Based on Interaction History',
            proposalSummary: 'Sistem rekomendasi topik bimbingan untuk meningkatkan efektivitas konsultasi mahasiswa.',
            status: 'approved',
            submittedBy: $student,
            submittedAt: $submittedAt,
            decidedBy: $admin,
            decidedAt: $approvedAt,
        );

        $this->upsertThesisDocument($project, $title, $student, 'proposal', 1, 'Proposal Skripsi', 'thesis/proposals/mahasiswa-proposal.pdf', $submittedAt);

        $sempro = $this->upsertDefense($project, $title, 'sempro', 1, 'completed', 'pass', $semproAt, 'Ruang Seminar 2', 'offline', $admin, $admin, $semproAt->addHours(2), 'Sempro disetujui tanpa revisi.');

        $this->upsertDefenseExaminer($sempro, $examinerOne, 'examiner', 1, 'pass', 82.5, 'Proposal sudah baik, lanjutkan ke pembimbingan.', $admin, $semproAt->addMinutes(90));
        $this->upsertDefenseExaminer($sempro, $examinerTwo, 'examiner', 2, 'pass', 78, 'Metodologi cukup jelas. Setuju untuk lanjut.', $admin, $semproAt->addMinutes(95));

        $this->upsertSupervisorAssignment($project, $supervisorOne, AdvisorType::Primary->value, 'active', $admin, CarbonImmutable::parse('2026-01-30 10:00:00'), null, 'Pembimbing utama untuk skripsi sistem rekomendasi.');
        $this->upsertSupervisorAssignment($project, $supervisorTwo, AdvisorType::Secondary->value, 'active', $admin, CarbonImmutable::parse('2026-01-30 10:05:00'), null, 'Pembimbing kedua.');

        $this->upsertMentorshipDocument($student, $supervisorOne, 'Draft Bab 1', 'draft-tugas-akhir', 1, 'mentorship/mahasiswa/draft-bab1-v1.pdf', 'approved', CarbonImmutable::parse('2026-02-05 15:00:00'), 'Dokumen awal yang sudah disetujui.');

        $this->recordEvent($project, $student, 'project_created', 'Proyek tugas akhir dimulai', 'Mahasiswa membuat pengajuan judul dan proposal baru.', $startedAt);
        $this->recordEvent($project, $student, 'title_submitted', 'Judul diajukan', $title->title_id, $submittedAt);
        $this->recordEvent($project, $admin, 'title_approved', 'Judul disetujui', $title->title_id, $approvedAt);
        $this->recordEvent($project, $admin, 'sempro_completed', 'Sempro selesai', 'Sempro dinyatakan lulus.', $semproAt->addHours(2));
        $this->recordEvent($project, $admin, 'supervisor_assigned', 'Pembimbing diperbarui', 'Dosen pembimbing telah ditetapkan.', CarbonImmutable::parse('2026-01-30 10:10:00'));
    }

    private function seedSemproScheduled(User $admin, ProgramStudi $prodi, User $examinerOne, User $examinerTwo): void
    {
        $student = $this->student('akbar@sita.test');

        if (! $student instanceof User) {
            return;
        }

        $startedAt = CarbonImmutable::parse('2026-02-10 09:00:00');
        $submittedAt = CarbonImmutable::parse('2026-02-10 09:30:00');
        $approvedAt = CarbonImmutable::parse('2026-02-15 11:00:00');
        $semproAt = CarbonImmutable::parse('2026-03-20 09:00:00');

        $project = $this->upsertProject($student, $prodi, $startedAt, 'sempro', 'active');
        $title = $this->upsertTitle(
            $project,
            versionNo: 1,
            titleId: 'Analisis Sentimen Media Sosial Menggunakan Deep Learning',
            titleEn: 'Social Media Sentiment Analysis Using Deep Learning',
            proposalSummary: 'Menganalisis sentimen publik di media sosial menggunakan teknik deep learning LSTM dan BERT.',
            status: 'approved',
            submittedBy: $student,
            submittedAt: $submittedAt,
            decidedBy: $admin,
            decidedAt: $approvedAt,
        );

        $this->upsertThesisDocument($project, $title, $student, 'proposal', 1, 'Proposal Skripsi', 'thesis/proposals/akbar-proposal.pdf', $submittedAt);

        $sempro = $this->upsertDefense($project, $title, 'sempro', 1, 'scheduled', 'pending', $semproAt, 'Ruang Seminar 1', 'offline', $admin);

        $this->upsertDefenseExaminer($sempro, $examinerOne, 'examiner', 1, 'pending', null, null, $admin, null);
        $this->upsertDefenseExaminer($sempro, $examinerTwo, 'examiner', 2, 'pending', null, null, $admin, null);
        $this->createSemproThread($sempro, $student, [$examinerOne, $examinerTwo], $semproAt);

        $this->recordEvent($project, $student, 'project_created', 'Proyek tugas akhir dimulai', 'Pengajuan judul telah dibuat.', $startedAt);
        $this->recordEvent($project, $admin, 'title_approved', 'Judul disetujui', $title->title_id, $approvedAt);
        $this->recordEvent($project, $admin, 'sempro_scheduled', 'Sempro dijadwalkan', 'Sempro terjadwal di Ruang Seminar 1.', $semproAt);
    }

    private function seedSemproRevision(User $admin, ProgramStudi $prodi, User $examinerOne, User $examinerTwo): void
    {
        $student = $this->student('nadia@sita.test');

        if (! $student instanceof User) {
            return;
        }

        $startedAt = CarbonImmutable::parse('2026-01-25 08:45:00');
        $submittedAt = CarbonImmutable::parse('2026-01-25 09:00:00');
        $approvedAt = CarbonImmutable::parse('2026-02-02 10:00:00');
        $semproAt = CarbonImmutable::parse('2026-02-28 13:00:00');

        $project = $this->upsertProject($student, $prodi, $startedAt, 'sempro', 'active');
        $title = $this->upsertTitle(
            $project,
            versionNo: 1,
            titleId: 'Pengembangan Aplikasi E-Learning Berbasis Gamifikasi',
            titleEn: 'Development of Gamification-Based E-Learning Application',
            proposalSummary: 'Membangun platform e-learning dengan elemen gamifikasi untuk meningkatkan motivasi belajar.',
            status: 'approved',
            submittedBy: $student,
            submittedAt: $submittedAt,
            decidedBy: $admin,
            decidedAt: $approvedAt,
        );

        $this->upsertThesisDocument($project, $title, $student, 'proposal', 1, 'Proposal Skripsi', 'thesis/proposals/nadia-proposal.pdf', $submittedAt);

        $sempro = $this->upsertDefense($project, $title, 'sempro', 1, 'completed', 'pass_with_revision', $semproAt, 'Ruang Seminar 3', 'online', $admin, $admin, $semproAt->addHours(2), 'Sempro diterima dengan revisi metodologi.');

        $this->upsertDefenseExaminer($sempro, $examinerOne, 'examiner', 1, 'pass_with_revision', 65, 'Metodologi kurang jelas, perlu diperbaiki bagian analisis data.', $admin, $semproAt->addMinutes(90));
        $this->upsertDefenseExaminer($sempro, $examinerTwo, 'examiner', 2, 'pass', 72.5, 'Secara umum baik, tapi ikuti catatan penguji 1.', $admin, $semproAt->addMinutes(95));
        $this->upsertRevision($project, $sempro, $examinerOne, 'open', 'Lengkapi metodologi penelitian.', CarbonImmutable::parse('2026-03-15 23:59:00'));
        $this->createSemproThread($sempro, $student, [$examinerOne, $examinerTwo], $semproAt);

        $this->upsertMentorshipDocument($student, $examinerOne, 'Revisi Metodologi', 'revisi-sempro', 1, 'mentorship/nadia/revisi-metodologi-v1.pdf', 'needs_revision', CarbonImmutable::parse('2026-03-02 14:00:00'), 'Masih perlu melengkapi penjelasan teknik pengumpulan data.');
        $this->upsertMentorshipDocument($student, $examinerOne, 'Revisi Metodologi', 'revisi-sempro', 2, 'mentorship/nadia/revisi-metodologi-v2.pdf', 'submitted', CarbonImmutable::parse('2026-03-04 10:00:00'), null);

        $this->recordEvent($project, $student, 'project_created', 'Proyek tugas akhir dimulai', 'Pengajuan judul telah dibuat.', $startedAt);
        $this->recordEvent($project, $admin, 'title_approved', 'Judul disetujui', $title->title_id, $approvedAt);
        $this->recordEvent($project, $admin, 'revision_opened', 'Revisi sempro dibuka', 'Mahasiswa perlu memperbaiki metodologi.', $semproAt->addHours(2));
    }

    private function seedTitleReviewPending(ProgramStudi $prodi): void
    {
        $student = $this->student('rizky@sita.test');

        if (! $student instanceof User) {
            return;
        }

        $startedAt = CarbonImmutable::parse('2026-03-03 10:00:00');
        $submittedAt = CarbonImmutable::parse('2026-03-03 10:15:00');

        $project = $this->upsertProject($student, $prodi, $startedAt, 'title_review', 'active');
        $title = $this->upsertTitle(
            $project,
            versionNo: 1,
            titleId: 'Implementasi Blockchain untuk Sistem Voting Digital',
            titleEn: 'Blockchain Implementation for Digital Voting System',
            proposalSummary: 'Membangun sistem voting digital berbasis blockchain untuk meningkatkan transparansi dan keamanan.',
            status: 'submitted',
            submittedBy: $student,
            submittedAt: $submittedAt,
        );

        $this->upsertThesisDocument($project, $title, $student, 'proposal', 1, 'Proposal Skripsi', 'thesis/proposals/rizky-proposal.pdf', $submittedAt);

        $this->recordEvent($project, $student, 'project_created', 'Proyek tugas akhir dimulai', 'Pengajuan judul menunggu review admin.', $startedAt);
        $this->recordEvent($project, $student, 'title_submitted', 'Judul diajukan', $title->title_id, $submittedAt);
    }

    private function seedSemproPassedWithoutSupervisors(User $admin, ProgramStudi $prodi, User $examinerOne, User $examinerTwo): void
    {
        $student = $this->student('siti@sita.test');

        if (! $student instanceof User) {
            return;
        }

        $startedAt = CarbonImmutable::parse('2026-01-18 08:30:00');
        $submittedAt = CarbonImmutable::parse('2026-01-18 09:00:00');
        $approvedAt = CarbonImmutable::parse('2026-01-22 13:00:00');
        $semproAt = CarbonImmutable::parse('2026-02-05 10:00:00');

        $project = $this->upsertProject($student, $prodi, $startedAt, 'sempro', 'active');
        $title = $this->upsertTitle(
            $project,
            versionNo: 1,
            titleId: 'Sistem Deteksi Intrusi Jaringan Menggunakan Machine Learning',
            titleEn: 'Network Intrusion Detection System Using Machine Learning',
            proposalSummary: 'Merancang sistem deteksi intrusi jaringan yang memanfaatkan algoritma machine learning.',
            status: 'approved',
            submittedBy: $student,
            submittedAt: $submittedAt,
            decidedBy: $admin,
            decidedAt: $approvedAt,
        );

        $this->upsertThesisDocument($project, $title, $student, 'proposal', 1, 'Proposal Skripsi', 'thesis/proposals/siti-proposal.pdf', $submittedAt);

        $sempro = $this->upsertDefense($project, $title, 'sempro', 1, 'completed', 'pass', $semproAt, 'Ruang Seminar 1', 'offline', $admin, $admin, $semproAt->addHours(2), 'Sempro selesai, pembimbing belum ditetapkan.');

        $this->upsertDefenseExaminer($sempro, $examinerOne, 'examiner', 1, 'pass', 85, 'Topik sangat relevan. Lanjutkan.', $admin, $semproAt->addMinutes(80));
        $this->upsertDefenseExaminer($sempro, $examinerTwo, 'examiner', 2, 'pass', 80, 'Setuju. Dataset sudah jelas.', $admin, $semproAt->addMinutes(90));

        $this->recordEvent($project, $student, 'project_created', 'Proyek tugas akhir dimulai', 'Pengajuan judul dibuat.', $startedAt);
        $this->recordEvent($project, $admin, 'title_approved', 'Judul disetujui', $title->title_id, $approvedAt);
        $this->recordEvent($project, $admin, 'sempro_completed', 'Sempro selesai', 'Sempro dinyatakan lulus.', $semproAt->addHours(2));
    }

    private function seedMultipleSemproAttempts(User $admin, ProgramStudi $prodi, User $examinerOne, User $examinerTwo): void
    {
        $student = $this->student('bagas@sita.test');

        if (! $student instanceof User) {
            return;
        }

        $startedAt = CarbonImmutable::parse('2026-02-01 08:30:00');
        $submittedAt = CarbonImmutable::parse('2026-02-01 09:00:00');
        $approvedAt = CarbonImmutable::parse('2026-02-04 11:00:00');

        $project = $this->upsertProject($student, $prodi, $startedAt, 'sempro', 'active');
        $title = $this->upsertTitle(
            $project,
            versionNo: 1,
            titleId: 'Platform Asesmen Otomatis Kualitas Proposal Skripsi',
            titleEn: 'Automated Thesis Proposal Quality Assessment Platform',
            proposalSummary: 'Membangun platform penilaian otomatis kualitas proposal skripsi dengan analisis rubrik.',
            status: 'approved',
            submittedBy: $student,
            submittedAt: $submittedAt,
            decidedBy: $admin,
            decidedAt: $approvedAt,
        );

        $this->upsertThesisDocument($project, $title, $student, 'proposal', 1, 'Proposal Skripsi', 'thesis/proposals/bagas-proposal.pdf', $submittedAt);

        $attemptOneAt = CarbonImmutable::parse('2026-02-18 09:30:00');
        $attemptOne = $this->upsertDefense($project, $title, 'sempro', 1, 'completed', 'pass', $attemptOneAt, 'Ruang Seminar 4A', 'offline', $admin, $admin, $attemptOneAt->addHours(2), 'Attempt pertama selesai.');
        $this->upsertDefenseExaminer($attemptOne, $examinerOne, 'examiner', 1, 'pass', 79.5, 'Attempt pertama dinyatakan layak, tetapi perlu penjadwalan ulang presentasi akhir.', $admin, $attemptOneAt->addMinutes(90));
        $this->upsertDefenseExaminer($attemptOne, $examinerTwo, 'examiner', 2, 'pass', 81, 'Attempt pertama selesai, lanjut ke penjadwalan ulang presentasi lanjutan.', $admin, $attemptOneAt->addMinutes(95));

        $attemptTwoAt = CarbonImmutable::parse('2026-03-18 13:30:00');
        $attemptTwo = $this->upsertDefense($project, $title, 'sempro', 2, 'scheduled', 'pending', $attemptTwoAt, 'Ruang Seminar 4B', 'online', $admin);
        $this->upsertDefenseExaminer($attemptTwo, $examinerOne, 'examiner', 1, 'pending', null, null, $admin, null);
        $this->upsertDefenseExaminer($attemptTwo, $examinerTwo, 'examiner', 2, 'pending', null, null, $admin, null);
        $this->createSemproThread($attemptTwo, $student, [$examinerOne, $examinerTwo], $attemptTwoAt);

        $this->recordEvent($project, $student, 'project_created', 'Proyek tugas akhir dimulai', 'Pengajuan judul dibuat.', $startedAt);
        $this->recordEvent($project, $admin, 'sempro_completed', 'Sempro selesai', 'Attempt pertama selesai.', $attemptOneAt->addHours(2));
        $this->recordEvent($project, $admin, 'sempro_scheduled', 'Sempro dijadwalkan', 'Attempt kedua dijadwalkan ulang.', $attemptTwoAt);
    }

    private function seedHistoricalRestart(User $admin, ProgramStudi $prodi, User $examinerOne, User $examinerTwo, User $supervisorOne, User $supervisorTwo): void
    {
        $student = $this->student('laila@sita.test');

        if (! $student instanceof User) {
            return;
        }

        $historicalStartedAt = CarbonImmutable::parse('2025-09-10 09:00:00');
        $historicalSubmittedAt = CarbonImmutable::parse('2025-09-10 09:30:00');
        $historicalApprovedAt = CarbonImmutable::parse('2025-09-18 14:00:00');
        $historicalSemproAt = CarbonImmutable::parse('2025-10-05 10:00:00');
        $historicalSidangAt = CarbonImmutable::parse('2025-11-08 10:00:00');

        $historicalProject = $this->upsertProject(
            $student,
            $prodi,
            $historicalStartedAt,
            'completed',
            'completed',
            CarbonImmutable::parse('2025-11-19 16:00:00'),
            $admin,
            'Attempt lama yang sudah selesai dan ditutup.',
        );

        $historicalTitle = $this->upsertTitle(
            $historicalProject,
            versionNo: 1,
            titleId: 'Sistem Rekomendasi Ruang Belajar Kolaboratif Berbasis IoT',
            titleEn: 'IoT-Based Collaborative Study Space Recommendation System',
            proposalSummary: 'Proyek lama yang telah melewati sempro dan diarsipkan karena mahasiswa mengganti arah penelitian.',
            status: 'approved',
            submittedBy: $student,
            submittedAt: $historicalSubmittedAt,
            decidedBy: $admin,
            decidedAt: $historicalApprovedAt,
        );

        $this->upsertThesisDocument($historicalProject, $historicalTitle, $student, 'proposal', 1, 'Proposal Skripsi', 'thesis/proposals/laila-historical-proposal.pdf', $historicalSubmittedAt);

        $historicalSempro = $this->upsertDefense($historicalProject, $historicalTitle, 'sempro', 1, 'completed', 'pass', $historicalSemproAt, 'Ruang Seminar 2', 'offline', $admin, $admin, $historicalSemproAt->addHours(2), 'Sempro proyek lama disetujui.');
        $this->upsertDefenseExaminer($historicalSempro, $examinerOne, 'examiner', 1, 'pass', 78, 'Sempro proyek lama disetujui.', $admin, $historicalSemproAt->addMinutes(80));
        $this->upsertDefenseExaminer($historicalSempro, $examinerTwo, 'examiner', 2, 'pass', 80.5, 'Dokumen proyek lama cukup baik.', $admin, $historicalSemproAt->addMinutes(85));

        $this->upsertSupervisorAssignment($historicalProject, $supervisorOne, AdvisorType::Primary->value, 'ended', $admin, CarbonImmutable::parse('2025-10-10 09:00:00'), CarbonImmutable::parse('2025-11-18 10:00:00'), 'Pembimbing proyek lama.');
        $this->upsertSupervisorAssignment($historicalProject, $supervisorTwo, AdvisorType::Secondary->value, 'ended', $admin, CarbonImmutable::parse('2025-10-10 09:05:00'), CarbonImmutable::parse('2025-11-18 10:00:00'), 'Pembimbing kedua proyek lama.');

        $historicalSidang = $this->upsertDefense($historicalProject, $historicalTitle, 'sidang', 1, 'completed', 'pass_with_revision', $historicalSidangAt, 'Ruang Sidang B', 'offline', $admin, $admin, $historicalSidangAt->addHours(2), 'Sidang historis untuk proyek yang telah ditutup.');
        $this->upsertDefenseExaminer($historicalSidang, $supervisorOne, 'chair', 1, 'pass', 83.5, 'Ketua menyetujui dengan revisi minor.', $admin, $historicalSidangAt->addMinutes(95));
        $this->upsertDefenseExaminer($historicalSidang, $supervisorTwo, 'secretary', 2, 'pass_with_revision', 81, 'Sekretaris menambahkan catatan format.', $admin, $historicalSidangAt->addMinutes(100));
        $this->upsertDefenseExaminer($historicalSidang, $examinerOne, 'examiner', 3, 'pass', 84, 'Penguji menyetujui hasil sidang.', $admin, $historicalSidangAt->addMinutes(105));

        $revision = $this->upsertRevision(
            $historicalProject,
            $historicalSidang,
            $supervisorTwo,
            'resolved',
            'Finalisasi format penulisan dan kelengkapan lampiran.',
            CarbonImmutable::parse('2025-11-15 23:59:00'),
            CarbonImmutable::parse('2025-11-16 15:00:00'),
            CarbonImmutable::parse('2025-11-19 12:00:00'),
            $admin,
            'Revisi final sudah diterima.',
        );

        $this->upsertThesisDocument($historicalProject, $historicalTitle, $student, 'final_manuscript', 1, 'Naskah Akhir', 'thesis/final/laila-final-manuscript.pdf', CarbonImmutable::parse('2025-11-16 15:00:00'), $historicalSidang, $revision);

        $newStartedAt = CarbonImmutable::parse('2026-03-01 08:00:00');
        $newSubmittedAt = CarbonImmutable::parse('2026-03-01 08:30:00');

        $newProject = $this->upsertProject($student, $prodi, $newStartedAt, 'title_review', 'active', null, null, 'Attempt baru setelah mahasiswa merombak total topik tugas akhir.');
        $newTitle = $this->upsertTitle(
            $newProject,
            versionNo: 1,
            titleId: 'Asisten Akademik Berbasis Retrieval-Augmented Generation',
            titleEn: 'Retrieval-Augmented Generation Academic Assistant',
            proposalSummary: 'Attempt baru setelah mahasiswa merombak total topik tugas akhir.',
            status: 'submitted',
            submittedBy: $student,
            submittedAt: $newSubmittedAt,
        );
        $this->upsertThesisDocument($newProject, $newTitle, $student, 'proposal', 1, 'Proposal Skripsi', 'thesis/proposals/laila-restart-proposal.pdf', $newSubmittedAt);

        $this->recordEvent($historicalProject, $admin, 'project_closed', 'Proyek ditutup', 'Proyek tugas akhir dinyatakan selesai.', CarbonImmutable::parse('2025-11-19 16:00:00'));
        $this->recordEvent($newProject, $student, 'project_created', 'Proyek tugas akhir dimulai', 'Attempt baru dimulai setelah restart topik.', $newStartedAt);
    }

    private function seedSupervisorRotationAndSidang(User $admin, ProgramStudi $prodi, User $initialSupervisor, User $semproExaminer, User $newPrimarySupervisor, User $secondarySupervisor): void
    {
        $student = $this->student('putra@sita.test');

        if (! $student instanceof User) {
            return;
        }

        $startedAt = CarbonImmutable::parse('2026-01-05 09:00:00');
        $submittedAt = CarbonImmutable::parse('2026-01-05 10:00:00');
        $approvedAt = CarbonImmutable::parse('2026-01-12 14:00:00');
        $semproAt = CarbonImmutable::parse('2026-02-02 14:00:00');
        $sidangAt = CarbonImmutable::parse('2026-03-25 09:00:00');

        $project = $this->upsertProject($student, $prodi, $startedAt, 'sidang', 'active');
        $title = $this->upsertTitle(
            $project,
            versionNo: 1,
            titleId: 'Deteksi Dini Risiko Dropout Mahasiswa Menggunakan Pembelajaran Mesin',
            titleEn: 'Early Detection of Student Dropout Risk Using Machine Learning',
            proposalSummary: 'Model prediksi risiko dropout untuk membantu intervensi akademik lebih dini.',
            status: 'approved',
            submittedBy: $student,
            submittedAt: $submittedAt,
            decidedBy: $admin,
            decidedAt: $approvedAt,
        );

        $this->upsertThesisDocument($project, $title, $student, 'proposal', 1, 'Proposal Skripsi', 'thesis/proposals/putra-proposal.pdf', $submittedAt);

        $sempro = $this->upsertDefense($project, $title, 'sempro', 1, 'completed', 'pass', $semproAt, 'Ruang Seminar 5', 'offline', $admin, $admin, $semproAt->addHours(2), 'Lanjutkan ke tahap penelitian.');
        $this->upsertDefenseExaminer($sempro, $initialSupervisor, 'examiner', 1, 'pass', 84, 'Analisis sudah matang.', $admin, $semproAt->addMinutes(80));
        $this->upsertDefenseExaminer($sempro, $semproExaminer, 'examiner', 2, 'pass', 82, 'Lanjutkan ke tahap penelitian.', $admin, $semproAt->addMinutes(85));

        $this->upsertSupervisorAssignment($project, $initialSupervisor, AdvisorType::Primary->value, 'ended', $admin, CarbonImmutable::parse('2026-02-03 09:00:00'), CarbonImmutable::parse('2026-02-20 09:00:00'), 'Pembimbing utama awal sebelum rotasi.');
        $this->upsertSupervisorAssignment($project, $newPrimarySupervisor, AdvisorType::Primary->value, 'active', $admin, CarbonImmutable::parse('2026-02-21 09:00:00'), null, 'Rotasi pembimbing utama karena penyesuaian topik riset.');
        $this->upsertSupervisorAssignment($project, $secondarySupervisor, AdvisorType::Secondary->value, 'active', $admin, CarbonImmutable::parse('2026-02-21 09:05:00'), null, 'Pembimbing kedua aktif setelah rotasi.');

        $sidang = $this->upsertDefense($project, $title, 'sidang', 1, 'scheduled', 'pending', $sidangAt, 'Ruang Sidang A', 'offline', $admin, null, null, 'Sidang perdana terjadwal untuk proyek aktif dengan pembimbing yang sudah lengkap.');
        $this->upsertDefenseExaminer($sidang, $newPrimarySupervisor, 'chair', 1, 'pending', null, 'Ketua sidang.', $admin, null);
        $this->upsertDefenseExaminer($sidang, $secondarySupervisor, 'secretary', 2, 'pending', null, 'Sekretaris sidang.', $admin, null);
        $this->upsertDefenseExaminer($sidang, $semproExaminer, 'examiner', 3, 'pending', null, 'Penguji eksternal sidang.', $admin, null);

        $this->upsertMentorshipDocument($student, $newPrimarySupervisor, 'Draft Bab 4', 'draft-tugas-akhir', 1, 'mentorship/putra/draft-bab4-v1.pdf', 'submitted', CarbonImmutable::parse('2026-03-01 11:00:00'), null);
        $this->upsertMentorshipDocument($student, $secondarySupervisor, 'Draft Bab 4', 'draft-tugas-akhir', 1, 'mentorship/putra/draft-bab4-v1.pdf', 'submitted', CarbonImmutable::parse('2026-03-01 11:00:00'), null);

        $this->recordEvent($project, $student, 'project_created', 'Proyek tugas akhir dimulai', 'Pengajuan judul dibuat.', $startedAt);
        $this->recordEvent($project, $admin, 'sempro_completed', 'Sempro selesai', 'Sempro dinyatakan lulus.', $semproAt->addHours(2));
        $this->recordEvent($project, $admin, 'supervisor_assigned', 'Pembimbing diperbarui', 'Terjadi rotasi pembimbing.', CarbonImmutable::parse('2026-02-21 09:10:00'));
        $this->recordEvent($project, $admin, 'sidang_scheduled', 'Sidang dijadwalkan', 'Sidang perdana terjadwal.', $sidangAt);
    }

    private function student(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    private function upsertProject(
        User $student,
        ProgramStudi $prodi,
        CarbonImmutable $startedAt,
        string $phase,
        string $state,
        ?CarbonImmutable $completedAt = null,
        ?User $closedBy = null,
        ?string $notes = null,
    ): ThesisProject {
        return ThesisProject::query()->updateOrCreate(
            [
                'student_user_id' => $student->id,
                'started_at' => $startedAt,
            ],
            [
                'program_studi_id' => $prodi->id,
                'phase' => $phase,
                'state' => $state,
                'completed_at' => $completedAt,
                'closed_by' => $closedBy?->id,
                'created_by' => $student->id,
                'notes' => $notes,
            ],
        );
    }

    private function upsertTitle(
        ThesisProject $project,
        int $versionNo,
        string $titleId,
        string $titleEn,
        string $proposalSummary,
        string $status,
        User $submittedBy,
        CarbonImmutable $submittedAt,
        ?User $decidedBy = null,
        ?CarbonImmutable $decidedAt = null,
    ): ThesisProjectTitle {
        return ThesisProjectTitle::query()->updateOrCreate(
            [
                'project_id' => $project->id,
                'version_no' => $versionNo,
            ],
            [
                'title_id' => $titleId,
                'title_en' => $titleEn,
                'proposal_summary' => $proposalSummary,
                'status' => $status,
                'submitted_by_user_id' => $submittedBy->id,
                'submitted_at' => $submittedAt,
                'decided_by_user_id' => $decidedBy?->id,
                'decided_at' => $decidedAt,
            ],
        );
    }

    private function upsertDefense(
        ThesisProject $project,
        ThesisProjectTitle $title,
        string $type,
        int $attemptNo,
        string $status,
        string $result,
        CarbonImmutable $scheduledFor,
        string $location,
        string $mode,
        User $createdBy,
        ?User $decidedBy = null,
        ?CarbonImmutable $decisionAt = null,
        ?string $notes = null,
    ): ThesisDefense {
        return ThesisDefense::query()->updateOrCreate(
            [
                'project_id' => $project->id,
                'type' => $type,
                'attempt_no' => $attemptNo,
            ],
            [
                'title_version_id' => $title->id,
                'status' => $status,
                'result' => $result,
                'scheduled_for' => $scheduledFor,
                'location' => $location,
                'mode' => $mode,
                'created_by' => $createdBy->id,
                'decided_by' => $decidedBy?->id,
                'decision_at' => $decisionAt,
                'notes' => $notes,
            ],
        );
    }

    private function upsertDefenseExaminer(
        ThesisDefense $defense,
        User $lecturer,
        string $role,
        int $orderNo,
        string $decision,
        ?float $score,
        ?string $notes,
        User $assignedBy,
        ?CarbonImmutable $decidedAt,
    ): ThesisDefenseExaminer {
        return ThesisDefenseExaminer::query()->updateOrCreate(
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
                'assigned_by' => $assignedBy->id,
            ],
        );
    }

    private function upsertSupervisorAssignment(
        ThesisProject $project,
        User $lecturer,
        string $role,
        string $status,
        User $assignedBy,
        CarbonImmutable $startedAt,
        ?CarbonImmutable $endedAt,
        ?string $notes,
    ): ThesisSupervisorAssignment {
        return ThesisSupervisorAssignment::query()->updateOrCreate(
            [
                'project_id' => $project->id,
                'lecturer_user_id' => $lecturer->id,
                'role' => $role,
                'started_at' => $startedAt,
            ],
            [
                'status' => $status,
                'assigned_by' => $assignedBy->id,
                'ended_at' => $endedAt,
                'notes' => $notes,
            ],
        );
    }

    private function upsertRevision(
        ThesisProject $project,
        ThesisDefense $defense,
        User $requestedBy,
        string $status,
        string $notes,
        CarbonImmutable $dueAt,
        ?CarbonImmutable $submittedAt = null,
        ?CarbonImmutable $resolvedAt = null,
        ?User $resolvedBy = null,
        ?string $resolutionNotes = null,
    ): ThesisRevision {
        return ThesisRevision::query()->updateOrCreate(
            [
                'project_id' => $project->id,
                'defense_id' => $defense->id,
                'notes' => $notes,
            ],
            [
                'requested_by_user_id' => $requestedBy->id,
                'status' => $status,
                'due_at' => $dueAt,
                'submitted_at' => $submittedAt,
                'resolved_at' => $resolvedAt,
                'resolved_by_user_id' => $resolvedBy?->id,
                'resolution_notes' => $resolutionNotes,
            ],
        );
    }

    private function upsertThesisDocument(
        ThesisProject $project,
        ThesisProjectTitle $title,
        User $uploadedBy,
        string $kind,
        int $versionNo,
        string $titleLabel,
        string $storagePath,
        CarbonImmutable $uploadedAt,
        ?ThesisDefense $defense = null,
        ?ThesisRevision $revision = null,
    ): ThesisDocument {
        Storage::disk('public')->put($storagePath, 'demo thesis document');

        return ThesisDocument::query()->updateOrCreate(
            [
                'project_id' => $project->id,
                'kind' => $kind,
                'version_no' => $versionNo,
            ],
            [
                'title_version_id' => $title->id,
                'defense_id' => $defense?->id,
                'revision_id' => $revision?->id,
                'uploaded_by_user_id' => $uploadedBy->id,
                'status' => 'active',
                'title' => $titleLabel,
                'storage_disk' => 'public',
                'storage_path' => $storagePath,
                'stored_file_name' => basename($storagePath),
                'file_name' => basename($storagePath),
                'mime_type' => 'application/pdf',
                'file_size_kb' => 1,
                'uploaded_at' => $uploadedAt,
            ],
        );
    }

    private function upsertMentorshipDocument(
        User $student,
        User $lecturer,
        string $title,
        string $category,
        int $version,
        string $storagePath,
        string $status,
        CarbonImmutable $uploadedAt,
        ?string $revisionNotes,
    ): MentorshipDocument {
        Storage::disk('public')->put($storagePath, 'demo mentorship document');

        return MentorshipDocument::query()->updateOrCreate(
            [
                'student_user_id' => $student->id,
                'lecturer_user_id' => $lecturer->id,
                'document_group' => sprintf('%d:%s', $student->id, $category),
                'version_number' => $version,
            ],
            [
                'mentorship_assignment_id' => null,
                'title' => $title,
                'category' => $category,
                'file_name' => basename($storagePath),
                'file_url' => null,
                'storage_disk' => 'public',
                'storage_path' => $storagePath,
                'stored_file_name' => basename($storagePath),
                'mime_type' => 'application/pdf',
                'file_size_kb' => 1,
                'status' => $status,
                'revision_notes' => $revisionNotes,
                'reviewed_at' => $status === 'submitted' ? null : $uploadedAt->addDay(),
                'uploaded_by_user_id' => $student->id,
                'uploaded_by_role' => 'mahasiswa',
                'created_at' => $uploadedAt,
                'updated_at' => $uploadedAt,
            ],
        );
    }

    /**
     * @param  array<int, User>  $examiners
     */
    private function createSemproThread(ThesisDefense $defense, User $student, array $examiners, CarbonImmutable $createdAt): void
    {
        $thread = MentorshipChatThread::query()->updateOrCreate(
            [
                'student_user_id' => $student->id,
                'type' => 'sempro',
                'context_id' => $defense->id,
            ],
            [
                'label' => 'Sempro',
            ],
        );

        MentorshipChatThreadParticipant::query()->updateOrCreate(
            [
                'thread_id' => $thread->id,
                'user_id' => $student->id,
            ],
            [
                'role' => 'student',
            ],
        );

        foreach ($examiners as $examiner) {
            MentorshipChatThreadParticipant::query()->updateOrCreate(
                [
                    'thread_id' => $thread->id,
                    'user_id' => $examiner->id,
                ],
                [
                    'role' => 'examiner',
                ],
            );
        }

        if ($thread->messages()->count() === 0) {
            $thread->messages()->create([
                'sender_user_id' => null,
                'message_type' => 'text',
                'message' => 'Thread Seminar Proposal telah dibuat. Silahkan berdiskusi mengenai sempro di sini.',
                'sent_at' => $createdAt,
            ]);
        }
    }

    private function recordEvent(
        ThesisProject $project,
        ?User $actor,
        string $eventType,
        string $label,
        ?string $description,
        CarbonImmutable $occurredAt,
    ): void {
        ThesisProjectEvent::query()->updateOrCreate(
            [
                'project_id' => $project->id,
                'event_type' => $eventType,
                'occurred_at' => $occurredAt,
            ],
            [
                'actor_user_id' => $actor?->id,
                'label' => $label,
                'description' => $description,
                'payload' => null,
            ],
        );
    }
}
