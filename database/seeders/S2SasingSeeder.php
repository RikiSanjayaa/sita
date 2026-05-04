<?php

namespace Database\Seeders;

use App\Enums\AdvisorType;
use App\Enums\AppRole;
use App\Models\AdminProfile;
use App\Models\DosenProfile;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipChatThreadParticipant;
use App\Models\MentorshipDocument;
use App\Models\MentorshipSchedule;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\SystemAnnouncement;
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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class S2SasingSeeder extends Seeder
{
    private const PRODI_NAME = 'S2 Sastra Inggris';

    private const PRODI_SLUG = 's2-sastra-inggris';

    private const CONCENTRATION = ProgramStudi::DEFAULT_GENERAL_CONCENTRATION;

    private const SHARED_PASSWORD = 'password';

    /**
     * @var array<int, array{key: string, name: string, email: string, nik: string}>
     */
    private const LECTURERS = [
        ['key' => 'diana', 'name' => 'Dr. Diana Permata, M.Hum.', 'email' => 'dosen1.s2.sasing@gmail.com', 'nik' => '7302010101010001'],
        ['key' => 'rudi', 'name' => 'Rudi Hartono, M.A.', 'email' => 'dosen2.s2.sasing@gmail.com', 'nik' => '7302010101010002'],
        ['key' => 'maya', 'name' => 'Dr. Maya Salsabila, M.Phil.', 'email' => 'dosen3.s2.sasing@gmail.com', 'nik' => '7302010101010003'],
        ['key' => 'arman', 'name' => 'Prof. Arman Wijaya, Ph.D.', 'email' => 'dosen4.s2.sasing@gmail.com', 'nik' => '7302010101010004'],
        ['key' => 'nisa', 'name' => 'Dr. Nisa Rahmawati, M.Hum.', 'email' => 'dosen5.s2.sasing@gmail.com', 'nik' => '7302010101010005'],
        ['key' => 'bintang', 'name' => 'Bintang Prakoso, M.A.', 'email' => 'dosen6.s2.sasing@gmail.com', 'nik' => '7302010101010006'],
        ['key' => 'sekar', 'name' => 'Dr. Sekar Lestari, M.Litt.', 'email' => 'dosen7.s2.sasing@gmail.com', 'nik' => '7302010101010007'],
        ['key' => 'fikri', 'name' => 'Fikri Mahendra, M.Hum.', 'email' => 'dosen8.s2.sasing@gmail.com', 'nik' => '7302010101010008'],
    ];

    /**
     * @var array<int, array{key: string, name: string, email: string, nim: string, angkatan: int}>
     */
    private const STUDENTS = [
        ['key' => 'alya', 'name' => 'Alya Nurfadila', 'email' => 'siswa1.s2.sasing@gmail.com', 'nim' => '2406200001', 'angkatan' => 2024],
        ['key' => 'bagus', 'name' => 'Bagus Ramadhan', 'email' => 'siswa2.s2.sasing@gmail.com', 'nim' => '2406200002', 'angkatan' => 2024],
        ['key' => 'citra', 'name' => 'Citra Maharani', 'email' => 'siswa3.s2.sasing@gmail.com', 'nim' => '2406200003', 'angkatan' => 2024],
        ['key' => 'erina', 'name' => 'Erina Putri', 'email' => 'siswa4.s2.sasing@gmail.com', 'nim' => '2406200004', 'angkatan' => 2024],
        ['key' => 'faris', 'name' => 'Faris Hidayat', 'email' => 'siswa5.s2.sasing@gmail.com', 'nim' => '2406200005', 'angkatan' => 2024],
        ['key' => 'ghea', 'name' => 'Ghea Lestari', 'email' => 'siswa6.s2.sasing@gmail.com', 'nim' => '2406200006', 'angkatan' => 2024],
        ['key' => 'hanif', 'name' => 'Hanif Kurniawan', 'email' => 'siswa7.s2.sasing@gmail.com', 'nim' => '2406200007', 'angkatan' => 2024],
        ['key' => 'intan', 'name' => 'Intan Maharani', 'email' => 'siswa8.s2.sasing@gmail.com', 'nim' => '2406200008', 'angkatan' => 2024],
        ['key' => 'jovan', 'name' => 'Jovan Saputra', 'email' => 'siswa9.s2.sasing@gmail.com', 'nim' => '2406200009', 'angkatan' => 2024],
        ['key' => 'kirana', 'name' => 'Kirana Azzahra', 'email' => 'siswa10.s2.sasing@gmail.com', 'nim' => '2406200010', 'angkatan' => 2024],
        ['key' => 'luthfi', 'name' => 'Luthfi Maulana', 'email' => 'siswa11.s2.sasing@gmail.com', 'nim' => '2406200011', 'angkatan' => 2024],
        ['key' => 'mentari', 'name' => 'Mentari Puspita', 'email' => 'siswa12.s2.sasing@gmail.com', 'nim' => '2406200012', 'angkatan' => 2024],
        ['key' => 'nabila', 'name' => 'Nabila Paramita', 'email' => 'siswa13.s2.sasing@gmail.com', 'nim' => '2406200013', 'angkatan' => 2023],
        ['key' => 'oka', 'name' => 'Oka Prasetyo', 'email' => 'siswa14.s2.sasing@gmail.com', 'nim' => '2406200014', 'angkatan' => 2023],
        ['key' => 'putri', 'name' => 'Putri Anindita', 'email' => 'siswa15.s2.sasing@gmail.com', 'nim' => '2406200015', 'angkatan' => 2023],
    ];

    private CarbonImmutable $anchor;

    public function run(): void
    {
        $this->anchor = CarbonImmutable::now()->startOfDay();

        $roles = $this->seedRoles();
        $programStudi = $this->upsertProgramStudi();
        $superAdmin = $this->upsertSuperAdmin($roles[AppRole::SuperAdmin->value]);
        $admin = $this->upsertAdmin($roles[AppRole::Admin->value], $programStudi);
        $lecturers = $this->seedLecturers($roles[AppRole::Dosen->value], $programStudi);
        $students = $this->seedStudents($roles[AppRole::Mahasiswa->value], $programStudi);

        $this->seedFreshSubmissions($programStudi, $students);
        $this->seedSemproScheduled($admin, $programStudi, $students['erina'], $lecturers['diana'], $lecturers['rudi']);
        $this->seedSemproAwaitingFinalization($admin, $programStudi, $students['faris'], $lecturers['maya'], $lecturers['arman']);
        $this->seedSemproRevision($admin, $programStudi, $students['ghea'], $lecturers['nisa'], $lecturers['bintang']);
        $this->seedSemproFailed($admin, $programStudi, $students['hanif'], $lecturers['sekar'], $lecturers['fikri']);
        $this->seedResearchComplete($admin, $programStudi, $students['intan'], $lecturers['diana'], $lecturers['maya'], $lecturers['rudi'], $lecturers['arman']);
        $this->seedResearchIncomplete($admin, $programStudi, $students['jovan'], $lecturers['nisa'], $lecturers['fikri'], $lecturers['sekar']);
        $this->seedSidangScheduled($admin, $programStudi, $students['kirana'], $lecturers['diana'], $lecturers['maya'], $lecturers['bintang'], $lecturers['nisa']);
        $this->seedSidangAwaitingFinalization($admin, $programStudi, $students['luthfi'], $lecturers['rudi'], $lecturers['sekar'], $lecturers['arman']);
        $this->seedSidangRevision($admin, $programStudi, $students['mentari'], $lecturers['maya'], $lecturers['fikri'], $lecturers['bintang']);
        $this->seedCompletedProject($admin, $programStudi, $students['nabila'], $lecturers['diana'], $lecturers['rudi'], $lecturers['arman']);
        $this->seedOnHoldProject($admin, $programStudi, $students['oka'], $lecturers['nisa'], $lecturers['sekar'], $lecturers['fikri']);
        $this->seedRestartedProject($admin, $programStudi, $students['putri'], $lecturers['maya'], $lecturers['arman'], $lecturers['bintang']);
        $this->seedAnnouncement($admin, $programStudi);

        $superAdmin->refresh();
    }

    /**
     * @return array<string, Role>
     */
    private function seedRoles(): array
    {
        return collect(AppRole::values())
            ->mapWithKeys(fn(string $role): array => [
                $role => Role::query()->firstOrCreate(['name' => $role]),
            ])
            ->all();
    }

    private function upsertProgramStudi(): ProgramStudi
    {
        return ProgramStudi::query()->updateOrCreate(
            ['slug' => self::PRODI_SLUG],
            [
                'name' => self::PRODI_NAME,
                'concentrations' => [self::CONCENTRATION],
            ],
        );
    }

    private function upsertSuperAdmin(Role $role): User
    {
        $user = $this->upsertUser(
            name: 'Super Admin SiTA',
            email: 'superadmin.s2.sasing@gmail.com',
            lastActiveRole: AppRole::SuperAdmin->value,
        );

        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function upsertAdmin(Role $role, ProgramStudi $programStudi): User
    {
        $user = $this->upsertUser(
            name: 'Admin S2 Sastra Inggris',
            email: 'admin.s2.sasing@gmail.com',
            lastActiveRole: AppRole::Admin->value,
        );

        $user->roles()->syncWithoutDetaching([$role->id]);

        AdminProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['program_studi_id' => $programStudi->id],
        );

        return $user;
    }

    /**
     * @return array<string, User>
     */
    private function seedLecturers(Role $role, ProgramStudi $programStudi): array
    {
        return collect(self::LECTURERS)
            ->mapWithKeys(function (array $lecturer) use ($role, $programStudi): array {
                $user = $this->upsertUser(
                    name: $lecturer['name'],
                    email: $lecturer['email'],
                    lastActiveRole: AppRole::Dosen->value,
                );

                $user->roles()->syncWithoutDetaching([$role->id]);

                DosenProfile::query()->updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'nik' => $lecturer['nik'],
                        'program_studi_id' => $programStudi->id,
                        'concentration' => self::CONCENTRATION,
                        'supervision_quota' => 12,
                        'is_active' => true,
                    ],
                );

                return [$lecturer['key'] => $user];
            })
            ->all();
    }

    /**
     * @return array<string, User>
     */
    private function seedStudents(Role $role, ProgramStudi $programStudi): array
    {
        return collect(self::STUDENTS)
            ->mapWithKeys(function (array $student) use ($role, $programStudi): array {
                $user = $this->upsertUser(
                    name: $student['name'],
                    email: $student['email'],
                    lastActiveRole: AppRole::Mahasiswa->value,
                );

                $user->roles()->syncWithoutDetaching([$role->id]);

                MahasiswaProfile::query()->updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'nim' => $student['nim'],
                        'program_studi_id' => $programStudi->id,
                        'concentration' => self::CONCENTRATION,
                        'angkatan' => $student['angkatan'],
                        'is_active' => true,
                    ],
                );

                return [$student['key'] => $user];
            })
            ->all();
    }

    private function seedFreshSubmissions(ProgramStudi $programStudi, array $students): void
    {
        $freshSubmissions = [
            [
                'student' => $students['alya'],
                'started_offset' => -3,
                'title_id' => 'Negosiasi Identitas dalam Memoar Penulis Perempuan Diaspora',
                'title_en' => 'Identity Negotiation in Memoirs by Women Diaspora Writers',
                'summary' => 'Kajian awal terhadap strategi naratif dan pembentukan identitas pada memoar penulis perempuan diaspora berbahasa Inggris.',
            ],
            [
                'student' => $students['bagus'],
                'started_offset' => -2,
                'title_id' => 'Praktik Translanguaging pada Diskusi Akademik Mahasiswa Pascasarjana',
                'title_en' => 'Translanguaging Practices in Graduate Students Academic Discussions',
                'summary' => 'Penelitian awal mengenai perpindahan kode dan strategi translanguaging dalam forum akademik mahasiswa S2.',
            ],
            [
                'student' => $students['citra'],
                'started_offset' => -1,
                'title_id' => 'Representasi Memori Kolektif dalam Novel Pascakolonial Kontemporer',
                'title_en' => 'Collective Memory Representation in Contemporary Postcolonial Novels',
                'summary' => 'Rencana tesis tentang representasi memori kolektif dan trauma sejarah dalam novel pascakolonial kontemporer.',
            ],
        ];

        foreach ($freshSubmissions as $submission) {
            $startedAt = $this->at($submission['started_offset'], 9);
            $submittedAt = $this->at($submission['started_offset'], 10, 15);

            $project = $this->upsertProject(
                student: $submission['student'],
                programStudi: $programStudi,
                startedAt: $startedAt,
                phase: 'title_review',
                state: 'active',
                notes: 'Mahasiswa baru memulai tesis dan menunggu review judul.',
            );

            $title = $this->upsertTitle(
                project: $project,
                versionNo: 1,
                titleId: $submission['title_id'],
                titleEn: $submission['title_en'],
                proposalSummary: $submission['summary'],
                status: 'submitted',
                submittedBy: $submission['student'],
                submittedAt: $submittedAt,
            );

            $this->upsertThesisDocument(
                project: $project,
                title: $title,
                uploadedBy: $submission['student'],
                kind: 'proposal',
                versionNo: 1,
                titleLabel: 'Proposal Tesis',
                storagePath: sprintf('showcase/s2-sasing/proposals/%s-v1.pdf', $submission['student']->id),
                uploadedAt: $submittedAt,
            );

            $this->recordEvent($project, $submission['student'], 'project_created', 'Proyek tesis dimulai', 'Mahasiswa membuat pengajuan judul awal.', $startedAt);
            $this->recordEvent($project, $submission['student'], 'title_submitted', 'Judul diajukan', $submission['title_id'], $submittedAt);
        }
    }

    private function seedSemproScheduled(User $admin, ProgramStudi $programStudi, User $student, User $examinerOne, User $examinerTwo): void
    {
        $startedAt = $this->at(-75, 9);
        $submittedAt = $this->at(-75, 10);
        $approvedAt = $this->at(-66, 14);
        $semproAt = $this->at(4, 9);

        $project = $this->upsertProject($student, $programStudi, $startedAt, 'sempro', 'active', notes: 'Sempro pertama sudah dijadwalkan.');
        $title = $this->upsertTitle(
            $project,
            1,
            'Strategi Kesantunan dalam Presentasi Akademik Mahasiswa S2',
            'Politeness Strategies in Graduate Students Academic Presentations',
            'Analisis pragmatik terhadap strategi kesantunan yang muncul dalam presentasi akademik mahasiswa pascasarjana.',
            'approved',
            $student,
            $submittedAt,
            $admin,
            $approvedAt,
        );

        $this->upsertThesisDocument($project, $title, $student, 'proposal', 1, 'Proposal Tesis', 'showcase/s2-sasing/proposals/erina-sempro.pdf', $submittedAt);

        $sempro = $this->upsertDefense($project, $title, 'sempro', 1, 'scheduled', 'pending', $semproAt, 'Ruang Seminar Pascasarjana', 'offline', $admin, notes: 'Sempro terjadwal untuk pekan ini.');
        $this->upsertDefenseExaminer($sempro, $examinerOne, 'examiner', 1, 'pending', null, 'Penguji utama sempro.', null, $admin, null);
        $this->upsertDefenseExaminer($sempro, $examinerTwo, 'examiner', 2, 'pending', null, 'Penguji kedua sempro.', null, $admin, null);
        $this->createSemproThread($sempro, $student, [$examinerOne, $examinerTwo], $semproAt);

        $this->recordEvent($project, $student, 'project_created', 'Proyek tesis dimulai', 'Mahasiswa memulai pengajuan tesis.', $startedAt);
        $this->recordEvent($project, $admin, 'title_approved', 'Judul disetujui', $title->title_id, $approvedAt);
        $this->recordEvent($project, $admin, 'sempro_scheduled', 'Sempro dijadwalkan', 'Sempro akan dilaksanakan dalam waktu dekat.', $semproAt);
    }

    private function seedSemproAwaitingFinalization(User $admin, ProgramStudi $programStudi, User $student, User $examinerOne, User $examinerTwo): void
    {
        $startedAt = $this->at(-90, 9);
        $submittedAt = $this->at(-90, 10);
        $approvedAt = $this->at(-82, 13);
        $semproAt = $this->at(-1, 13);

        $project = $this->upsertProject($student, $programStudi, $startedAt, 'sempro', 'active', notes: 'Sempro selesai dinilai dan menunggu finalisasi admin.');
        $title = $this->upsertTitle(
            $project,
            1,
            'Konstruksi Identitas Queer dalam Cerpen Inggris Modern',
            'Queer Identity Construction in Modern English Short Stories',
            'Studi kritis mengenai representasi identitas queer dan negosiasi ruang sosial dalam cerpen Inggris modern.',
            'approved',
            $student,
            $submittedAt,
            $admin,
            $approvedAt,
        );

        $this->upsertThesisDocument($project, $title, $student, 'proposal', 1, 'Proposal Tesis', 'showcase/s2-sasing/proposals/faris-sempro.pdf', $submittedAt);

        $sempro = $this->upsertDefense($project, $title, 'sempro', 1, 'awaiting_finalization', 'pending', $semproAt, 'Zoom Meeting Pascasarjana', 'online', $admin);
        $this->upsertDefenseExaminer($sempro, $examinerOne, 'examiner', 1, 'pass', 84, 'Argumentasi sudah kuat dan layak dilanjutkan.', null, $admin, $semproAt->addMinutes(95));
        $this->upsertDefenseExaminer($sempro, $examinerTwo, 'examiner', 2, 'pass_with_revision', 80, 'Perlu memperjelas kerangka teori pada bab kajian pustaka.', 'Tegaskan posisi teori queer yang dipakai.', $admin, $semproAt->addMinutes(100));
        $this->createSemproThread($sempro, $student, [$examinerOne, $examinerTwo], $semproAt);

        $this->recordEvent($project, $student, 'project_created', 'Proyek tesis dimulai', 'Mahasiswa memulai pengajuan tesis.', $startedAt);
        $this->recordEvent($project, $admin, 'title_approved', 'Judul disetujui', $title->title_id, $approvedAt);
        $this->recordEvent($project, $admin, 'sempro_scheduled', 'Sempro dijadwalkan', 'Sempro sudah terlaksana dan menunggu finalisasi.', $semproAt);
    }

    private function seedSemproRevision(User $admin, ProgramStudi $programStudi, User $student, User $examinerOne, User $examinerTwo): void
    {
        $startedAt = $this->at(-100, 8);
        $submittedAt = $this->at(-100, 9);
        $approvedAt = $this->at(-92, 10);
        $semproAt = $this->at(-12, 13);
        $revisionDueAt = $this->at(5, 23, 59);

        $project = $this->upsertProject($student, $programStudi, $startedAt, 'sempro', 'active', notes: 'Sempro lulus dengan revisi terbuka.');
        $title = $this->upsertTitle(
            $project,
            1,
            'Penerjemahan Humor Sarkastik dalam Subtitle Serial Inggris',
            'Translating Sarcastic Humor in English Series Subtitles',
            'Analisis strategi penerjemahan humor sarkastik dari bahasa Inggris ke bahasa Indonesia pada subtitle serial televisi.',
            'approved',
            $student,
            $submittedAt,
            $admin,
            $approvedAt,
        );

        $this->upsertThesisDocument($project, $title, $student, 'proposal', 1, 'Proposal Tesis', 'showcase/s2-sasing/proposals/ghea-sempro.pdf', $submittedAt);

        $sempro = $this->upsertDefense(
            $project,
            $title,
            'sempro',
            1,
            'completed',
            'pass_with_revision',
            $semproAt,
            'Ruang 3 Pascasarjana',
            'offline',
            $admin,
            $admin,
            $semproAt->addHours(2),
            'Sempro dinyatakan lulus dengan revisi metode dan contoh data.',
        );
        $this->upsertDefenseExaminer($sempro, $examinerOne, 'examiner', 1, 'pass_with_revision', 76, 'Kerangka analisis perlu dipertajam.', 'Tambahkan justifikasi pemilihan contoh subtitle dan kategori humor.', $admin, $semproAt->addMinutes(90));
        $this->upsertDefenseExaminer($sempro, $examinerTwo, 'examiner', 2, 'pass', 81, 'Secara umum sudah siap lanjut dengan revisi minor.', null, $admin, $semproAt->addMinutes(96));
        $this->createSemproThread($sempro, $student, [$examinerOne, $examinerTwo], $semproAt);

        $revision = $this->upsertRevision($project, $sempro, $examinerOne, 'open', 'Lengkapi kategori humor dan justifikasi data penelitian.', $revisionDueAt);

        $revisionDocument = $this->upsertMentorshipDocument(
            student: $student,
            lecturer: $examinerOne,
            title: 'Revisi Proposal Sempro',
            category: 'revisi-sempro',
            version: 1,
            storagePath: 'showcase/s2-sasing/mentorship/ghea-revisi-sempro-v1.pdf',
            status: 'submitted',
            uploadedAt: $this->at(-4, 10),
            revisionNotes: null,
        );

        $this->seedMentorshipWorkspace(
            student: $student,
            lecturer: $examinerOne,
            document: $revisionDocument,
            pendingRequestedAt: $this->at(1, 9),
            approvedScheduledAt: $this->at(3, 10),
        );

        $this->recordEvent($project, $student, 'project_created', 'Proyek tesis dimulai', 'Mahasiswa memulai pengajuan tesis.', $startedAt);
        $this->recordEvent($project, $admin, 'title_approved', 'Judul disetujui', $title->title_id, $approvedAt);
        $this->recordEvent($project, $admin, 'sempro_completed', 'Sempro selesai dengan revisi', 'Mahasiswa perlu menindaklanjuti catatan penguji.', $semproAt->addHours(2));
        $this->recordEvent($project, $admin, 'revision_opened', 'Revisi sempro dibuka', $revision->notes, $semproAt->addHours(2));
    }

    private function seedSemproFailed(User $admin, ProgramStudi $programStudi, User $student, User $examinerOne, User $examinerTwo): void
    {
        $startedAt = $this->at(-110, 9);
        $submittedAt = $this->at(-110, 10);
        $approvedAt = $this->at(-101, 15);
        $semproAt = $this->at(-18, 9);

        $project = $this->upsertProject($student, $programStudi, $startedAt, 'sempro', 'active', notes: 'Sempro belum lulus dan menunggu perbaikan untuk attempt berikutnya.');
        $title = $this->upsertTitle(
            $project,
            1,
            'Pembacaan Ekokritik atas Puisi Kontemporer Berbahasa Inggris',
            'An Ecocritical Reading of Contemporary English Poetry',
            'Kajian ekokritik terhadap puisi kontemporer berbahasa Inggris dengan fokus pada representasi krisis lingkungan.',
            'approved',
            $student,
            $submittedAt,
            $admin,
            $approvedAt,
        );

        $this->upsertThesisDocument($project, $title, $student, 'proposal', 1, 'Proposal Tesis', 'showcase/s2-sasing/proposals/hanif-sempro.pdf', $submittedAt);

        $sempro = $this->upsertDefense(
            $project,
            $title,
            'sempro',
            1,
            'completed',
            'fail',
            $semproAt,
            'Ruang 2 Pascasarjana',
            'offline',
            $admin,
            $admin,
            $semproAt->addHours(2),
            'Mahasiswa diminta merombak fokus objek kajian dan metodologi.',
        );
        $this->upsertDefenseExaminer($sempro, $examinerOne, 'examiner', 1, 'fail', 61, 'Rumusan masalah dan corpus belum meyakinkan.', null, $admin, $semproAt->addMinutes(92));
        $this->upsertDefenseExaminer($sempro, $examinerTwo, 'examiner', 2, 'fail', 59, 'Metode dan kontribusi ilmiah belum cukup jelas.', null, $admin, $semproAt->addMinutes(97));
        $this->createSemproThread($sempro, $student, [$examinerOne, $examinerTwo], $semproAt);

        $this->recordEvent($project, $student, 'project_created', 'Proyek tesis dimulai', 'Mahasiswa memulai pengajuan tesis.', $startedAt);
        $this->recordEvent($project, $admin, 'title_approved', 'Judul disetujui', $title->title_id, $approvedAt);
        $this->recordEvent($project, $admin, 'sempro_failed', 'Sempro tidak lulus', 'Mahasiswa perlu memperbaiki proposal untuk attempt berikutnya.', $semproAt->addHours(2));
    }

    private function seedResearchComplete(User $admin, ProgramStudi $programStudi, User $student, User $primarySupervisor, User $secondarySupervisor, User $semproExaminerOne, User $semproExaminerTwo): void
    {
        $startedAt = $this->at(-130, 9);
        $submittedAt = $this->at(-130, 10);
        $approvedAt = $this->at(-121, 13);
        $semproAt = $this->at(-90, 10);
        $assignedAt = $this->at(-88, 9);

        $project = $this->upsertProject($student, $programStudi, $startedAt, 'research', 'active', notes: 'Mahasiswa berada pada tahap penelitian aktif dengan dua pembimbing.');
        $title = $this->upsertTitle(
            $project,
            1,
            'Wacana Gender dalam Ulasan Film oleh Kritikus Perempuan',
            'Gender Discourse in Film Reviews by Women Critics',
            'Analisis wacana kritis atas representasi gender dalam ulasan film berbahasa Inggris yang ditulis kritikus perempuan.',
            'approved',
            $student,
            $submittedAt,
            $admin,
            $approvedAt,
        );

        $this->upsertThesisDocument($project, $title, $student, 'proposal', 1, 'Proposal Tesis', 'showcase/s2-sasing/proposals/intan-research.pdf', $submittedAt);

        $sempro = $this->upsertDefense(
            $project,
            $title,
            'sempro',
            1,
            'completed',
            'pass',
            $semproAt,
            'Ruang Seminar 1',
            'offline',
            $admin,
            $admin,
            $semproAt->addHours(2),
            'Sempro disetujui tanpa revisi.',
        );
        $this->upsertDefenseExaminer($sempro, $semproExaminerOne, 'examiner', 1, 'pass', 85, 'Proposal matang dan fokus.', null, $admin, $semproAt->addMinutes(88));
        $this->upsertDefenseExaminer($sempro, $semproExaminerTwo, 'examiner', 2, 'pass', 83, 'Lanjutkan ke penelitian utama.', null, $admin, $semproAt->addMinutes(94));
        $this->createSemproThread($sempro, $student, [$semproExaminerOne, $semproExaminerTwo], $semproAt);

        $this->upsertSupervisorAssignment($project, $primarySupervisor, AdvisorType::Primary->value, 'active', $admin, $assignedAt, null, 'Pembimbing utama aktif.');
        $this->upsertSupervisorAssignment($project, $secondarySupervisor, AdvisorType::Secondary->value, 'active', $admin, $assignedAt->addMinutes(5), null, 'Pembimbing kedua aktif.');

        $workspaceDocument = $this->upsertMentorshipDocument(
            student: $student,
            lecturer: $primarySupervisor,
            title: 'Draft Bab 2 Telaah Pustaka',
            category: 'draft-tugas-akhir',
            version: 1,
            storagePath: 'showcase/s2-sasing/mentorship/intan-bab2-v1.pdf',
            status: 'submitted',
            uploadedAt: $this->at(-3, 14),
            revisionNotes: null,
        );

        $this->seedMentorshipWorkspace(
            student: $student,
            lecturer: $primarySupervisor,
            document: $workspaceDocument,
            pendingRequestedAt: $this->at(2, 9),
            approvedScheduledAt: $this->at(5, 10),
        );

        $this->recordEvent($project, $student, 'project_created', 'Proyek tesis dimulai', 'Mahasiswa memulai pengajuan tesis.', $startedAt);
        $this->recordEvent($project, $admin, 'title_approved', 'Judul disetujui', $title->title_id, $approvedAt);
        $this->recordEvent($project, $admin, 'sempro_completed', 'Sempro selesai', 'Mahasiswa lanjut ke tahap penelitian.', $semproAt->addHours(2));
        $this->recordEvent($project, $admin, 'supervisor_assigned', 'Pembimbing diperbarui', 'Dua dosen pembimbing aktif telah ditetapkan.', $assignedAt->addMinutes(10));
    }

    private function seedResearchIncomplete(User $admin, ProgramStudi $programStudi, User $student, User $primarySupervisor, User $semproExaminerOne, User $semproExaminerTwo): void
    {
        $startedAt = $this->at(-125, 8);
        $submittedAt = $this->at(-125, 9);
        $approvedAt = $this->at(-118, 11);
        $semproAt = $this->at(-86, 9);
        $assignedAt = $this->at(-83, 10);

        $project = $this->upsertProject($student, $programStudi, $startedAt, 'research', 'active', notes: 'Tahap penelitian aktif tetapi pembimbing kedua belum ditetapkan.');
        $title = $this->upsertTitle(
            $project,
            1,
            'Metafora Perjalanan dalam Novel Migran Kontemporer',
            'Journey Metaphors in Contemporary Migrant Novels',
            'Kajian metafora konseptual mengenai motif perjalanan, rumah, dan perpindahan dalam novel migran kontemporer.',
            'approved',
            $student,
            $submittedAt,
            $admin,
            $approvedAt,
        );

        $this->upsertThesisDocument($project, $title, $student, 'proposal', 1, 'Proposal Tesis', 'showcase/s2-sasing/proposals/jovan-research.pdf', $submittedAt);

        $sempro = $this->upsertDefense(
            $project,
            $title,
            'sempro',
            1,
            'completed',
            'pass',
            $semproAt,
            'Ruang Seminar 4',
            'offline',
            $admin,
            $admin,
            $semproAt->addHours(2),
            'Sempro disetujui dan menunggu kelengkapan pembimbing.',
        );
        $this->upsertDefenseExaminer($sempro, $semproExaminerOne, 'examiner', 1, 'pass', 82, 'Topik relevan dan dapat dikembangkan.', null, $admin, $semproAt->addMinutes(87));
        $this->upsertDefenseExaminer($sempro, $semproExaminerTwo, 'examiner', 2, 'pass', 80, 'Lanjutkan ke penelitian.', null, $admin, $semproAt->addMinutes(91));
        $this->createSemproThread($sempro, $student, [$semproExaminerOne, $semproExaminerTwo], $semproAt);

        $this->upsertSupervisorAssignment($project, $primarySupervisor, AdvisorType::Primary->value, 'active', $admin, $assignedAt, null, 'Pembimbing utama aktif, pembimbing kedua belum ada.');

        $workspaceDocument = $this->upsertMentorshipDocument(
            student: $student,
            lecturer: $primarySupervisor,
            title: 'Draft Instrumen Analisis',
            category: 'draft-tugas-akhir',
            version: 1,
            storagePath: 'showcase/s2-sasing/mentorship/jovan-instrumen-v1.pdf',
            status: 'needs_revision',
            uploadedAt: $this->at(-2, 11),
            revisionNotes: 'Perjelas definisi kategori metafora dan unit analisis.',
        );

        $this->seedMentorshipWorkspace(
            student: $student,
            lecturer: $primarySupervisor,
            document: $workspaceDocument,
            pendingRequestedAt: $this->at(1, 13),
            approvedScheduledAt: $this->at(6, 9),
        );

        $this->recordEvent($project, $student, 'project_created', 'Proyek tesis dimulai', 'Mahasiswa memulai pengajuan tesis.', $startedAt);
        $this->recordEvent($project, $admin, 'title_approved', 'Judul disetujui', $title->title_id, $approvedAt);
        $this->recordEvent($project, $admin, 'sempro_completed', 'Sempro selesai', 'Mahasiswa melanjutkan penelitian dengan satu pembimbing aktif.', $semproAt->addHours(2));
        $this->recordEvent($project, $admin, 'supervisor_assigned', 'Pembimbing diperbarui', 'Pembimbing utama telah ditetapkan.', $assignedAt);
    }

    private function seedSidangScheduled(User $admin, ProgramStudi $programStudi, User $student, User $initialPrimary, User $newPrimary, User $secondarySupervisor, User $externalExaminer): void
    {
        $startedAt = $this->at(-170, 8);
        $submittedAt = $this->at(-170, 9);
        $approvedAt = $this->at(-161, 13);
        $semproAt = $this->at(-125, 10);
        $rotationAt = $this->at(-78, 9);
        $sidangAt = $this->at(8, 9);

        $project = $this->upsertProject($student, $programStudi, $startedAt, 'sidang', 'active', notes: 'Proyek aktif dengan riwayat rotasi pembimbing dan sidang terjadwal.');
        $title = $this->upsertTitle(
            $project,
            1,
            'Representasi Kelas Sosial dalam Novel Kampus Kontemporer',
            'Social Class Representation in Contemporary Campus Novels',
            'Penelitian mengenai representasi kelas sosial dan mobilitas akademik dalam novel kampus kontemporer berbahasa Inggris.',
            'approved',
            $student,
            $submittedAt,
            $admin,
            $approvedAt,
        );

        $this->upsertThesisDocument($project, $title, $student, 'proposal', 1, 'Proposal Tesis', 'showcase/s2-sasing/proposals/kirana-proposal.pdf', $submittedAt);

        $sempro = $this->upsertDefense(
            $project,
            $title,
            'sempro',
            1,
            'completed',
            'pass',
            $semproAt,
            'Ruang Seminar 5',
            'offline',
            $admin,
            $admin,
            $semproAt->addHours(2),
            'Sempro selesai dan mahasiswa lanjut riset.',
        );
        $this->upsertDefenseExaminer($sempro, $initialPrimary, 'examiner', 1, 'pass', 84, 'Sempro layak dilanjutkan.', null, $admin, $semproAt->addMinutes(84));
        $this->upsertDefenseExaminer($sempro, $externalExaminer, 'examiner', 2, 'pass', 82, 'Kerangka teoretis sudah memadai.', null, $admin, $semproAt->addMinutes(90));
        $this->createSemproThread($sempro, $student, [$initialPrimary, $externalExaminer], $semproAt);

        $this->upsertSupervisorAssignment($project, $initialPrimary, AdvisorType::Primary->value, 'ended', $admin, $this->at(-123, 9), $rotationAt->subDay(), 'Pembimbing utama awal sebelum rotasi.');
        $this->upsertSupervisorAssignment($project, $newPrimary, AdvisorType::Primary->value, 'active', $admin, $rotationAt, null, 'Pembimbing utama baru setelah rotasi topik.');
        $this->upsertSupervisorAssignment($project, $secondarySupervisor, AdvisorType::Secondary->value, 'active', $admin, $rotationAt->addMinutes(5), null, 'Pembimbing kedua aktif.');

        $draftDocument = $this->upsertMentorshipDocument(
            student: $student,
            lecturer: $newPrimary,
            title: 'Draft Bab 4 Analisis',
            category: 'draft-tugas-akhir',
            version: 1,
            storagePath: 'showcase/s2-sasing/mentorship/kirana-bab4-v1.pdf',
            status: 'submitted',
            uploadedAt: $this->at(-3, 9),
            revisionNotes: null,
        );

        $this->seedMentorshipWorkspace(
            student: $student,
            lecturer: $newPrimary,
            document: $draftDocument,
            pendingRequestedAt: $this->at(2, 10),
            approvedScheduledAt: $this->at(7, 13),
        );

        $sidang = $this->upsertDefense($project, $title, 'sidang', 1, 'scheduled', 'pending', $sidangAt, 'Ruang Sidang A', 'offline', $admin, notes: 'Sidang perdana sudah terjadwal.');
        $this->upsertDefenseExaminer($sidang, $newPrimary, 'primary_supervisor', 1, 'pending', null, 'Pembimbing utama sidang.', null, $admin, null);
        $this->upsertDefenseExaminer($sidang, $secondarySupervisor, 'secondary_supervisor', 2, 'pending', null, 'Pembimbing kedua sidang.', null, $admin, null);
        $this->upsertDefenseExaminer($sidang, $externalExaminer, 'examiner', 3, 'pending', null, 'Penguji eksternal sidang.', null, $admin, null);
        $this->createSidangThread($sidang, $student, [$newPrimary, $secondarySupervisor, $externalExaminer], $sidangAt);
        $this->upsertThesisDocument($project, $title, $student, 'final_manuscript', 1, 'Naskah Akhir', 'showcase/s2-sasing/final/kirana-naskah-akhir.pdf', $this->at(-1, 15), $sidang);

        $this->recordEvent($project, $student, 'project_created', 'Proyek tesis dimulai', 'Mahasiswa memulai pengajuan tesis.', $startedAt);
        $this->recordEvent($project, $admin, 'sempro_completed', 'Sempro selesai', 'Mahasiswa dinyatakan lulus sempro.', $semproAt->addHours(2));
        $this->recordEvent($project, $admin, 'supervisor_assigned', 'Pembimbing diperbarui', 'Terjadi rotasi pembimbing utama.', $rotationAt);
        $this->recordEvent($project, $admin, 'sidang_scheduled', 'Sidang dijadwalkan', 'Sidang perdana sudah masuk agenda publik.', $sidangAt);
    }

    private function seedSidangAwaitingFinalization(User $admin, ProgramStudi $programStudi, User $student, User $primarySupervisor, User $secondarySupervisor, User $externalExaminer): void
    {
        $startedAt = $this->at(-180, 9);
        $submittedAt = $this->at(-180, 10);
        $approvedAt = $this->at(-172, 12);
        $semproAt = $this->at(-145, 9);
        $assignedAt = $this->at(-143, 10);
        $sidangAt = $this->at(-2, 9);

        $project = $this->upsertProject($student, $programStudi, $startedAt, 'sidang', 'active', notes: 'Sidang menunggu finalisasi hasil oleh admin.');
        $title = $this->upsertTitle(
            $project,
            1,
            'Praktik Sitasi dan Otoritas Akademik dalam Artikel Mahasiswa S2',
            'Citation Practices and Academic Authority in Graduate Student Articles',
            'Kajian retorika akademik terhadap praktik sitasi, posisi penulis, dan pembentukan otoritas dalam artikel mahasiswa pascasarjana.',
            'approved',
            $student,
            $submittedAt,
            $admin,
            $approvedAt,
        );

        $this->upsertThesisDocument($project, $title, $student, 'proposal', 1, 'Proposal Tesis', 'showcase/s2-sasing/proposals/luthfi-proposal.pdf', $submittedAt);

        $sempro = $this->upsertDefense(
            $project,
            $title,
            'sempro',
            1,
            'completed',
            'pass',
            $semproAt,
            'Ruang Seminar 2',
            'offline',
            $admin,
            $admin,
            $semproAt->addHours(2),
            'Sempro disetujui.',
        );
        $this->upsertDefenseExaminer($sempro, $primarySupervisor, 'examiner', 1, 'pass', 83, 'Proposal solid.', null, $admin, $semproAt->addMinutes(82));
        $this->upsertDefenseExaminer($sempro, $externalExaminer, 'examiner', 2, 'pass', 82, 'Lanjut ke penelitian.', null, $admin, $semproAt->addMinutes(88));
        $this->createSemproThread($sempro, $student, [$primarySupervisor, $externalExaminer], $semproAt);

        $this->upsertSupervisorAssignment($project, $primarySupervisor, AdvisorType::Primary->value, 'active', $admin, $assignedAt, null, 'Pembimbing utama aktif.');
        $this->upsertSupervisorAssignment($project, $secondarySupervisor, AdvisorType::Secondary->value, 'active', $admin, $assignedAt->addMinutes(5), null, 'Pembimbing kedua aktif.');

        $sidang = $this->upsertDefense($project, $title, 'sidang', 1, 'awaiting_finalization', 'pending', $sidangAt, 'Ruang Sidang B', 'offline', $admin);
        $this->upsertDefenseExaminer($sidang, $primarySupervisor, 'primary_supervisor', 1, 'pass', 86, 'Naskah akhir sangat rapi.', null, $admin, $sidangAt->addMinutes(96));
        $this->upsertDefenseExaminer($sidang, $secondarySupervisor, 'secondary_supervisor', 2, 'pass', 85, 'Argumen konsisten dan metodologi tepat.', null, $admin, $sidangAt->addMinutes(101));
        $this->upsertDefenseExaminer($sidang, $externalExaminer, 'examiner', 3, 'pass_with_revision', 82, 'Perlu sedikit perbaikan pada abstrak dan sitasi.', 'Rapikan abstrak bahasa Inggris dan format sitasi akhir.', $admin, $sidangAt->addMinutes(106));
        $this->createSidangThread($sidang, $student, [$primarySupervisor, $secondarySupervisor, $externalExaminer], $sidangAt);
        $this->upsertThesisDocument($project, $title, $student, 'final_manuscript', 1, 'Naskah Akhir', 'showcase/s2-sasing/final/luthfi-naskah-akhir.pdf', $this->at(-3, 15), $sidang);

        $this->recordEvent($project, $student, 'project_created', 'Proyek tesis dimulai', 'Mahasiswa memulai pengajuan tesis.', $startedAt);
        $this->recordEvent($project, $admin, 'sempro_completed', 'Sempro selesai', 'Mahasiswa melanjutkan penelitian.', $semproAt->addHours(2));
        $this->recordEvent($project, $admin, 'supervisor_assigned', 'Pembimbing diperbarui', 'Dua pembimbing aktif telah ditetapkan.', $assignedAt->addMinutes(10));
        $this->recordEvent($project, $admin, 'sidang_scheduled', 'Sidang dijadwalkan', 'Sidang telah terlaksana dan menunggu finalisasi admin.', $sidangAt);
    }

    private function seedSidangRevision(User $admin, ProgramStudi $programStudi, User $student, User $primarySupervisor, User $secondarySupervisor, User $externalExaminer): void
    {
        $startedAt = $this->at(-190, 9);
        $submittedAt = $this->at(-190, 10);
        $approvedAt = $this->at(-182, 11);
        $semproAt = $this->at(-155, 10);
        $assignedAt = $this->at(-153, 9);
        $sidangAt = $this->at(-14, 13);
        $revisionDueAt = $this->at(6, 23, 59);

        $project = $this->upsertProject($student, $programStudi, $startedAt, 'sidang', 'active', notes: 'Sidang selesai dengan revisi terbuka.');
        $title = $this->upsertTitle(
            $project,
            1,
            'Adaptasi Fanfiction terhadap Kanon Sastra Populer',
            'Fanfiction Adaptation of Popular Literary Canons',
            'Penelitian mengenai bagaimana fanfiction menegosiasikan otoritas teks dan pembacaan ulang terhadap kanon sastra populer.',
            'approved',
            $student,
            $submittedAt,
            $admin,
            $approvedAt,
        );

        $this->upsertThesisDocument($project, $title, $student, 'proposal', 1, 'Proposal Tesis', 'showcase/s2-sasing/proposals/mentari-proposal.pdf', $submittedAt);

        $sempro = $this->upsertDefense(
            $project,
            $title,
            'sempro',
            1,
            'completed',
            'pass',
            $semproAt,
            'Ruang Seminar 6',
            'offline',
            $admin,
            $admin,
            $semproAt->addHours(2),
            'Sempro disetujui untuk lanjut riset.',
        );
        $this->upsertDefenseExaminer($sempro, $primarySupervisor, 'examiner', 1, 'pass', 84, 'Proposal layak dilanjutkan.', null, $admin, $semproAt->addMinutes(80));
        $this->upsertDefenseExaminer($sempro, $externalExaminer, 'examiner', 2, 'pass', 83, 'Objek penelitian kuat.', null, $admin, $semproAt->addMinutes(86));
        $this->createSemproThread($sempro, $student, [$primarySupervisor, $externalExaminer], $semproAt);

        $this->upsertSupervisorAssignment($project, $primarySupervisor, AdvisorType::Primary->value, 'active', $admin, $assignedAt, null, 'Pembimbing utama aktif.');
        $this->upsertSupervisorAssignment($project, $secondarySupervisor, AdvisorType::Secondary->value, 'active', $admin, $assignedAt->addMinutes(5), null, 'Pembimbing kedua aktif.');

        $sidang = $this->upsertDefense(
            $project,
            $title,
            'sidang',
            1,
            'completed',
            'pass_with_revision',
            $sidangAt,
            'Ruang Sidang C',
            'offline',
            $admin,
            $admin,
            $sidangAt->addHours(2),
            'Sidang dinyatakan lulus dengan revisi pada pembahasan dan daftar pustaka.',
        );
        $this->upsertDefenseExaminer($sidang, $primarySupervisor, 'primary_supervisor', 1, 'pass', 85, 'Secara umum sudah baik.', null, $admin, $sidangAt->addMinutes(94));
        $this->upsertDefenseExaminer($sidang, $secondarySupervisor, 'secondary_supervisor', 2, 'pass_with_revision', 81, 'Perlu merapikan bagian pembahasan dan daftar pustaka.', 'Perbaiki pembahasan akhir dan rapikan daftar pustaka sesuai gaya sitasi.', $admin, $sidangAt->addMinutes(99));
        $this->upsertDefenseExaminer($sidang, $externalExaminer, 'examiner', 3, 'pass', 84, 'Kontribusi penelitian sudah tampak.', null, $admin, $sidangAt->addMinutes(103));
        $this->createSidangThread($sidang, $student, [$primarySupervisor, $secondarySupervisor, $externalExaminer], $sidangAt);

        $revision = $this->upsertRevision(
            $project,
            $sidang,
            $secondarySupervisor,
            'open',
            'Rapikan pembahasan akhir dan daftar pustaka sebelum pengesahan.',
            $revisionDueAt,
        );

        $this->upsertThesisDocument(
            project: $project,
            title: $title,
            uploadedBy: $student,
            kind: 'final_manuscript',
            versionNo: 1,
            titleLabel: 'Naskah Akhir',
            storagePath: 'showcase/s2-sasing/final/mentari-naskah-akhir.pdf',
            uploadedAt: $this->at(-15, 16),
            defense: $sidang,
            revision: $revision,
        );

        $revisionDocument = $this->upsertMentorshipDocument(
            student: $student,
            lecturer: $secondarySupervisor,
            title: 'Perbaikan Naskah Pasca Sidang',
            category: 'revisi-sidang',
            version: 1,
            storagePath: 'showcase/s2-sasing/mentorship/mentari-revisi-sidang-v1.pdf',
            status: 'submitted',
            uploadedAt: $this->at(-2, 10),
            revisionNotes: null,
        );

        $this->seedMentorshipWorkspace(
            student: $student,
            lecturer: $secondarySupervisor,
            document: $revisionDocument,
            pendingRequestedAt: $this->at(2, 9),
            approvedScheduledAt: $this->at(4, 14),
        );

        $this->recordEvent($project, $student, 'project_created', 'Proyek tesis dimulai', 'Mahasiswa memulai pengajuan tesis.', $startedAt);
        $this->recordEvent($project, $admin, 'sempro_completed', 'Sempro selesai', 'Mahasiswa melanjutkan penelitian.', $semproAt->addHours(2));
        $this->recordEvent($project, $admin, 'supervisor_assigned', 'Pembimbing diperbarui', 'Dua pembimbing aktif telah ditetapkan.', $assignedAt->addMinutes(10));
        $this->recordEvent($project, $admin, 'sidang_completed', 'Sidang selesai dengan revisi', 'Mahasiswa perlu menyelesaikan revisi sebelum pengesahan.', $sidangAt->addHours(2));
        $this->recordEvent($project, $admin, 'revision_opened', 'Revisi sidang dibuka', $revision->notes, $sidangAt->addHours(2));
    }

    private function seedCompletedProject(User $admin, ProgramStudi $programStudi, User $student, User $primarySupervisor, User $secondarySupervisor, User $externalExaminer): void
    {
        $startedAt = $this->at(-210, 9);
        $submittedAt = $this->at(-210, 10);
        $approvedAt = $this->at(-201, 11);
        $semproAt = $this->at(-170, 9);
        $assignedAt = $this->at(-168, 10);
        $sidangAt = $this->at(-6, 9);
        $completedAt = $this->at(-5, 12);

        $project = $this->upsertProject(
            student: $student,
            programStudi: $programStudi,
            startedAt: $startedAt,
            phase: 'completed',
            state: 'completed',
            completedAt: $completedAt,
            closedBy: $admin,
            notes: 'Proyek tesis sudah selesai dan siap tampil pada topik yang telah lulus.',
        );
        $title = $this->upsertTitle(
            $project,
            1,
            'Representasi Perawatan dan Duka dalam Novel Inggris Kontemporer',
            'Representations of Care and Grief in Contemporary English Novels',
            'Kajian sastra mengenai relasi antara narasi perawatan, kehilangan, dan etika afeksi dalam novel Inggris kontemporer.',
            'approved',
            $student,
            $submittedAt,
            $admin,
            $approvedAt,
        );

        $this->upsertThesisDocument($project, $title, $student, 'proposal', 1, 'Proposal Tesis', 'showcase/s2-sasing/proposals/nabila-proposal.pdf', $submittedAt);

        $sempro = $this->upsertDefense(
            $project,
            $title,
            'sempro',
            1,
            'completed',
            'pass',
            $semproAt,
            'Ruang Seminar 1',
            'offline',
            $admin,
            $admin,
            $semproAt->addHours(2),
            'Sempro disetujui.',
        );
        $this->upsertDefenseExaminer($sempro, $primarySupervisor, 'examiner', 1, 'pass', 86, 'Proposal sudah kuat.', null, $admin, $semproAt->addMinutes(80));
        $this->upsertDefenseExaminer($sempro, $externalExaminer, 'examiner', 2, 'pass', 85, 'Layak dilanjutkan.', null, $admin, $semproAt->addMinutes(88));
        $this->createSemproThread($sempro, $student, [$primarySupervisor, $externalExaminer], $semproAt);

        $this->upsertSupervisorAssignment($project, $primarySupervisor, AdvisorType::Primary->value, 'ended', $admin, $assignedAt, $completedAt, 'Pembimbing utama proyek selesai.');
        $this->upsertSupervisorAssignment($project, $secondarySupervisor, AdvisorType::Secondary->value, 'ended', $admin, $assignedAt->addMinutes(5), $completedAt, 'Pembimbing kedua proyek selesai.');

        $sidang = $this->upsertDefense(
            $project,
            $title,
            'sidang',
            1,
            'completed',
            'pass',
            $sidangAt,
            'Ruang Sidang Utama',
            'offline',
            $admin,
            $admin,
            $completedAt,
            'Sidang dinyatakan lulus tanpa revisi.',
        );
        $this->upsertDefenseExaminer($sidang, $primarySupervisor, 'primary_supervisor', 1, 'pass', 88, 'Kualitas tesis sangat baik.', null, $admin, $sidangAt->addMinutes(95));
        $this->upsertDefenseExaminer($sidang, $secondarySupervisor, 'secondary_supervisor', 2, 'pass', 87, 'Analisis konsisten dan matang.', null, $admin, $sidangAt->addMinutes(100));
        $this->upsertDefenseExaminer($sidang, $externalExaminer, 'examiner', 3, 'pass', 89, 'Siap untuk publikasi lebih lanjut.', null, $admin, $sidangAt->addMinutes(105));
        $this->createSidangThread($sidang, $student, [$primarySupervisor, $secondarySupervisor, $externalExaminer], $sidangAt);
        $this->upsertThesisDocument($project, $title, $student, 'final_manuscript', 1, 'Naskah Akhir', 'showcase/s2-sasing/final/nabila-naskah-akhir.pdf', $this->at(-7, 15), $sidang);

        $this->recordEvent($project, $student, 'project_created', 'Proyek tesis dimulai', 'Mahasiswa memulai pengajuan tesis.', $startedAt);
        $this->recordEvent($project, $admin, 'sempro_completed', 'Sempro selesai', 'Mahasiswa melanjutkan penelitian.', $semproAt->addHours(2));
        $this->recordEvent($project, $admin, 'supervisor_assigned', 'Pembimbing diperbarui', 'Dua pembimbing aktif telah ditetapkan.', $assignedAt->addMinutes(10));
        $this->recordEvent($project, $admin, 'sidang_completed', 'Sidang selesai', 'Mahasiswa dinyatakan lulus.', $completedAt);
    }

    private function seedOnHoldProject(User $admin, ProgramStudi $programStudi, User $student, User $primarySupervisor, User $secondarySupervisor, User $externalExaminer): void
    {
        $startedAt = $this->at(-140, 9);
        $submittedAt = $this->at(-140, 10);
        $approvedAt = $this->at(-132, 11);
        $semproAt = $this->at(-104, 9);
        $assignedAt = $this->at(-102, 10);
        $onHoldAt = $this->at(-12, 9);

        $project = $this->upsertProject($student, $programStudi, $startedAt, 'research', 'on_hold', notes: 'Riset ditunda sementara karena pengumpulan data lapangan belum selesai.');
        $title = $this->upsertTitle(
            $project,
            1,
            'Narasi Kelas Pekerja dalam Drama Inggris Abad Ke-21',
            'Working-Class Narratives in Twenty-First Century English Drama',
            'Analisis representasi kelas pekerja, precarity, dan solidaritas dalam drama Inggris abad ke-21.',
            'approved',
            $student,
            $submittedAt,
            $admin,
            $approvedAt,
        );

        $this->upsertThesisDocument($project, $title, $student, 'proposal', 1, 'Proposal Tesis', 'showcase/s2-sasing/proposals/oka-proposal.pdf', $submittedAt);

        $sempro = $this->upsertDefense(
            $project,
            $title,
            'sempro',
            1,
            'completed',
            'pass',
            $semproAt,
            'Ruang Seminar 7',
            'offline',
            $admin,
            $admin,
            $semproAt->addHours(2),
            'Sempro disetujui.',
        );
        $this->upsertDefenseExaminer($sempro, $primarySupervisor, 'examiner', 1, 'pass', 82, 'Proposal memenuhi syarat.', null, $admin, $semproAt->addMinutes(84));
        $this->upsertDefenseExaminer($sempro, $externalExaminer, 'examiner', 2, 'pass', 81, 'Lanjutkan penelitian.', null, $admin, $semproAt->addMinutes(89));
        $this->createSemproThread($sempro, $student, [$primarySupervisor, $externalExaminer], $semproAt);

        $this->upsertSupervisorAssignment($project, $primarySupervisor, AdvisorType::Primary->value, 'active', $admin, $assignedAt, null, 'Pembimbing utama aktif.');
        $this->upsertSupervisorAssignment($project, $secondarySupervisor, AdvisorType::Secondary->value, 'active', $admin, $assignedAt->addMinutes(5), null, 'Pembimbing kedua aktif.');

        $workspaceDocument = $this->upsertMentorshipDocument(
            student: $student,
            lecturer: $primarySupervisor,
            title: 'Catatan Progres Riset',
            category: 'draft-tugas-akhir',
            version: 1,
            storagePath: 'showcase/s2-sasing/mentorship/oka-progres-riset.pdf',
            status: 'approved',
            uploadedAt: $this->at(-20, 11),
            revisionNotes: null,
        );

        $this->seedMentorshipWorkspace(
            student: $student,
            lecturer: $primarySupervisor,
            document: $workspaceDocument,
            pendingRequestedAt: $this->at(-10, 9),
            approvedScheduledAt: $this->at(-6, 14),
        );

        $this->recordEvent($project, $student, 'project_created', 'Proyek tesis dimulai', 'Mahasiswa memulai pengajuan tesis.', $startedAt);
        $this->recordEvent($project, $admin, 'sempro_completed', 'Sempro selesai', 'Mahasiswa melanjutkan penelitian.', $semproAt->addHours(2));
        $this->recordEvent($project, $admin, 'supervisor_assigned', 'Pembimbing diperbarui', 'Dua pembimbing aktif telah ditetapkan.', $assignedAt->addMinutes(10));
        $this->recordEvent($project, $admin, 'project_on_hold', 'Proyek ditunda', 'Proyek sementara ditunda sambil menunggu kelengkapan data lapangan.', $onHoldAt);
    }

    private function seedRestartedProject(User $admin, ProgramStudi $programStudi, User $student, User $historicalSupervisor, User $historicalExaminer, User $newExaminer): void
    {
        $historicalStartedAt = $this->at(-240, 9);
        $historicalSubmittedAt = $this->at(-240, 10);
        $historicalApprovedAt = $this->at(-232, 11);
        $historicalSemproAt = $this->at(-205, 9);
        $cancelledAt = $this->at(-45, 15);
        $newStartedAt = $this->at(-8, 9);
        $newSubmittedAt = $this->at(-8, 10);

        $historicalProject = $this->upsertProject(
            student: $student,
            programStudi: $programStudi,
            startedAt: $historicalStartedAt,
            phase: 'cancelled',
            state: 'cancelled',
            cancelledAt: $cancelledAt,
            closedBy: $admin,
            notes: 'Topik lama dihentikan karena mahasiswa mengganti arah penelitian secara penuh.',
        );
        $historicalTitle = $this->upsertTitle(
            $historicalProject,
            1,
            'Representasi Kota dalam Fiksi Distopia Inggris',
            'Urban Representation in English Dystopian Fiction',
            'Topik lama yang akhirnya dibatalkan karena mahasiswa memutuskan mengganti fokus penelitian.',
            'approved',
            $student,
            $historicalSubmittedAt,
            $admin,
            $historicalApprovedAt,
        );

        $this->upsertThesisDocument($historicalProject, $historicalTitle, $student, 'proposal', 1, 'Proposal Tesis', 'showcase/s2-sasing/proposals/putri-historical.pdf', $historicalSubmittedAt);

        $historicalSempro = $this->upsertDefense(
            $historicalProject,
            $historicalTitle,
            'sempro',
            1,
            'completed',
            'pass',
            $historicalSemproAt,
            'Ruang Seminar 8',
            'offline',
            $admin,
            $admin,
            $historicalSemproAt->addHours(2),
            'Sempro lama sempat dinyatakan lulus.',
        );
        $this->upsertDefenseExaminer($historicalSempro, $historicalSupervisor, 'examiner', 1, 'pass', 80, 'Topik lama sempat dilanjutkan.', null, $admin, $historicalSemproAt->addMinutes(82));
        $this->upsertDefenseExaminer($historicalSempro, $historicalExaminer, 'examiner', 2, 'pass', 79, 'Secara metodologis cukup.', null, $admin, $historicalSemproAt->addMinutes(87));
        $this->createSemproThread($historicalSempro, $student, [$historicalSupervisor, $historicalExaminer], $historicalSemproAt);

        $this->upsertSupervisorAssignment($historicalProject, $historicalSupervisor, AdvisorType::Primary->value, 'ended', $admin, $this->at(-202, 9), $cancelledAt, 'Pembimbing pada topik lama.');

        $newProject = $this->upsertProject(
            student: $student,
            programStudi: $programStudi,
            startedAt: $newStartedAt,
            phase: 'title_review',
            state: 'active',
            notes: 'Mahasiswa memulai ulang proyek dengan topik baru setelah pembatalan topik lama.',
        );
        $newTitle = $this->upsertTitle(
            $newProject,
            1,
            'AI Writing Assistant dan Persepsi Otoritas dalam Penulisan Akademik',
            'AI Writing Assistants and Perceived Authority in Academic Writing',
            'Topik baru untuk menelaah bagaimana AI writing assistant memengaruhi persepsi otoritas dan kepengarangan akademik.',
            'submitted',
            $student,
            $newSubmittedAt,
        );
        $this->upsertThesisDocument($newProject, $newTitle, $student, 'proposal', 1, 'Proposal Tesis', 'showcase/s2-sasing/proposals/putri-restart.pdf', $newSubmittedAt);

        $this->recordEvent($historicalProject, $student, 'project_created', 'Proyek tesis dimulai', 'Topik lama sempat diajukan dan berjalan.', $historicalStartedAt);
        $this->recordEvent($historicalProject, $admin, 'project_cancelled', 'Proyek dibatalkan', 'Topik lama dihentikan untuk memberi ruang pada topik baru.', $cancelledAt);
        $this->recordEvent($newProject, $student, 'project_created', 'Proyek tesis dimulai', 'Mahasiswa memulai ulang tesis dengan topik baru.', $newStartedAt);
        $this->recordEvent($newProject, $student, 'title_submitted', 'Judul diajukan', $newTitle->title_id, $newSubmittedAt);

        $newExaminer->refresh();
    }

    private function seedAnnouncement(User $admin, ProgramStudi $programStudi): void
    {
        SystemAnnouncement::query()->updateOrCreate(
            [
                'program_studi_id' => $programStudi->id,
                'title' => 'Agenda sempro, sidang, dan panduan revisi semester ini sudah tersedia',
            ],
            [
                'body' => 'Gunakan menu agenda untuk memeriksa jadwal sempro dan sidang terbaru. Mahasiswa yang sedang revisi diminta segera berkoordinasi dengan pembimbing atau penguji melalui workspace bimbingan.',
                'target_roles' => [
                    AppRole::Mahasiswa->value,
                    AppRole::Dosen->value,
                    AppRole::Admin->value,
                ],
                'status' => SystemAnnouncement::STATUS_PUBLISHED,
                'published_at' => $this->at(-1, 8),
                'action_url' => '/jadwal',
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );
    }

    private function upsertUser(string $name, string $email, string $lastActiveRole): User
    {
        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'phone_number' => $this->generatedPhoneNumber($email),
                'password' => Hash::make(self::SHARED_PASSWORD),
                'last_active_role' => $lastActiveRole,
            ],
        );
    }

    private function at(int $daysOffset, int $hour, int $minute = 0): CarbonImmutable
    {
        return $this->anchor->addDays($daysOffset)->setTime($hour, $minute);
    }

    private function generatedPhoneNumber(string $key): string
    {
        $hash = sprintf('%u', crc32($key));

        return '08'.str_pad(substr($hash, -10), 10, '0', STR_PAD_LEFT);
    }

    private function upsertProject(
        User $student,
        ProgramStudi $programStudi,
        CarbonImmutable $startedAt,
        string $phase,
        string $state,
        ?CarbonImmutable $completedAt = null,
        ?User $closedBy = null,
        ?CarbonImmutable $cancelledAt = null,
        ?string $notes = null,
    ): ThesisProject {
        return ThesisProject::query()->updateOrCreate(
            [
                'student_user_id' => $student->id,
                'started_at' => $startedAt,
            ],
            [
                'program_studi_id' => $programStudi->id,
                'phase' => $phase,
                'state' => $state,
                'completed_at' => $completedAt,
                'cancelled_at' => $cancelledAt,
                'created_by' => $student->id,
                'closed_by' => $closedBy?->id,
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
        ?string $revisionNotes,
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
                'revision_notes' => $revisionNotes,
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
        Storage::disk('public')->put($storagePath, 'showcase thesis document');

        return ThesisDocument::query()->updateOrCreate(
            [
                'project_id' => $project->id,
                'kind' => $kind,
                'version_no' => $versionNo,
                'storage_path' => $storagePath,
            ],
            [
                'title_version_id' => $title->id,
                'defense_id' => $defense?->id,
                'revision_id' => $revision?->id,
                'uploaded_by_user_id' => $uploadedBy->id,
                'status' => 'active',
                'title' => $titleLabel,
                'storage_disk' => 'public',
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
        Storage::disk('public')->put($storagePath, 'showcase mentorship document');

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
                'uploaded_by_role' => AppRole::Mahasiswa->value,
                'created_at' => $uploadedAt,
                'updated_at' => $uploadedAt,
            ],
        );
    }

    private function seedMentorshipWorkspace(
        User $student,
        User $lecturer,
        MentorshipDocument $document,
        CarbonImmutable $pendingRequestedAt,
        CarbonImmutable $approvedScheduledAt,
    ): void {
        MentorshipSchedule::query()->updateOrCreate(
            [
                'student_user_id' => $student->id,
                'lecturer_user_id' => $lecturer->id,
                'topic' => 'Diskusi tindak lanjut progres tesis',
                'status' => 'pending',
            ],
            [
                'mentorship_assignment_id' => null,
                'requested_for' => $pendingRequestedAt,
                'scheduled_for' => null,
                'location' => null,
                'student_note' => 'Mohon waktu untuk membahas progres terbaru dan langkah selanjutnya.',
                'lecturer_note' => null,
                'created_by_user_id' => $student->id,
            ],
        );

        MentorshipSchedule::query()->updateOrCreate(
            [
                'student_user_id' => $student->id,
                'lecturer_user_id' => $lecturer->id,
                'topic' => 'Sesi bimbingan terjadwal',
                'status' => 'approved',
            ],
            [
                'mentorship_assignment_id' => null,
                'requested_for' => $approvedScheduledAt,
                'scheduled_for' => $approvedScheduledAt,
                'location' => 'Google Meet',
                'student_note' => 'Siap membahas revisi dan progres terbaru.',
                'lecturer_note' => 'Fokus pada kelengkapan analisis dan alur argumen.',
                'created_by_user_id' => $student->id,
            ],
        );

        $thread = MentorshipChatThread::query()->updateOrCreate(
            [
                'student_user_id' => $student->id,
                'type' => 'pembimbing',
                'context_id' => null,
            ],
            [
                'label' => 'Bimbingan',
                'is_escalated' => false,
                'escalated_at' => null,
            ],
        );

        MentorshipChatMessage::query()->updateOrCreate(
            [
                'mentorship_chat_thread_id' => $thread->id,
                'message_type' => 'document_event',
                'related_document_id' => $document->id,
            ],
            [
                'sender_user_id' => null,
                'message' => 'Mahasiswa mengunggah dokumen untuk direview pada workspace bimbingan.',
                'attachment_disk' => null,
                'attachment_path' => null,
                'attachment_name' => $document->file_name,
                'attachment_mime' => 'application/pdf',
                'attachment_size_kb' => $document->file_size_kb,
                'sent_at' => $document->created_at,
            ],
        );

        MentorshipChatMessage::query()->updateOrCreate(
            [
                'mentorship_chat_thread_id' => $thread->id,
                'sender_user_id' => $student->id,
                'message' => 'Dokumen terbaru sudah saya unggah. Mohon masukan sebelum sesi bimbingan berikutnya.',
            ],
            [
                'related_document_id' => $document->id,
                'message_type' => 'text',
                'sent_at' => $document->created_at->addHour(),
            ],
        );

        MentorshipChatMessage::query()->updateOrCreate(
            [
                'mentorship_chat_thread_id' => $thread->id,
                'sender_user_id' => $lecturer->id,
                'message' => 'Baik, saya review terlebih dulu. Kita bahas detailnya saat bimbingan.',
            ],
            [
                'related_document_id' => null,
                'message_type' => 'text',
                'sent_at' => $document->created_at->addHours(2),
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
                'is_escalated' => false,
                'escalated_at' => null,
            ],
        );

        $this->syncDefenseThreadParticipants($thread, $student, $examiners);

        if ($thread->messages()->count() === 0) {
            $thread->messages()->create([
                'sender_user_id' => null,
                'message_type' => 'text',
                'message' => 'Thread Seminar Proposal telah dibuat. Gunakan thread ini untuk koordinasi sempro.',
                'sent_at' => $createdAt,
            ]);
        }
    }

    /**
     * @param  array<int, User>  $panel
     */
    private function createSidangThread(ThesisDefense $defense, User $student, array $panel, CarbonImmutable $createdAt): void
    {
        $thread = MentorshipChatThread::query()->updateOrCreate(
            [
                'student_user_id' => $student->id,
                'type' => 'sidang',
                'context_id' => $defense->id,
            ],
            [
                'label' => 'Sidang',
                'is_escalated' => false,
                'escalated_at' => null,
            ],
        );

        $this->syncDefenseThreadParticipants($thread, $student, $panel);

        if ($thread->messages()->count() === 0) {
            $thread->messages()->create([
                'sender_user_id' => null,
                'message_type' => 'text',
                'message' => 'Thread Sidang telah dibuat. Gunakan thread ini untuk koordinasi sidang.',
                'sent_at' => $createdAt,
            ]);
        }
    }

    /**
     * @param  array<int, User>  $lecturers
     */
    private function syncDefenseThreadParticipants(MentorshipChatThread $thread, User $student, array $lecturers): void
    {
        MentorshipChatThreadParticipant::query()->updateOrCreate(
            [
                'thread_id' => $thread->id,
                'user_id' => $student->id,
            ],
            [
                'role' => 'student',
            ],
        );

        $lecturerIds = collect($lecturers)
            ->map(fn(User $lecturer): int => $lecturer->id)
            ->values();

        MentorshipChatThreadParticipant::query()
            ->where('thread_id', $thread->id)
            ->where('role', 'examiner')
            ->when(
                $lecturerIds->isNotEmpty(),
                fn($query) => $query->whereNotIn('user_id', $lecturerIds->all()),
                fn($query) => $query,
            )
            ->delete();

        foreach ($lecturers as $lecturer) {
            MentorshipChatThreadParticipant::query()->updateOrCreate(
                [
                    'thread_id' => $thread->id,
                    'user_id' => $lecturer->id,
                ],
                [
                    'role' => 'examiner',
                ],
            );
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
