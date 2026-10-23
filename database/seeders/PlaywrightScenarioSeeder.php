<?php

namespace Database\Seeders;

use App\Enums\AppRole;
use App\Enums\DegreeLevel;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisDocument;
use App\Models\ThesisProject;
use App\Models\ThesisProjectTitle;
use App\Models\User;
use App\Services\ThesisProjectAdminService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Dedicated Playwright students, one per cross-role scenario. Only the
 * Playwright setup and E2E specs call this seeder; it is not part of
 * DatabaseSeeder. Every run deletes the thesis projects of these students and
 * rebuilds them, so a spec can call it in beforeAll to get a known state no
 * matter which specs ran before. Keep the emails in sync with `thesisScenarios`
 * in tests/e2e/support/thesis-fixtures.ts.
 */
class PlaywrightScenarioSeeder extends Seeder
{
    public function run(): void
    {
        $prodi = ProgramStudi::query()->where('slug', 'ilkom')->first();
        $admin = User::query()->where('email', 'admin@sita.test')->first();
        $dosen = collect(['dosen', 'dosen2', 'dosen3', 'dosen4'])
            ->map(fn (string $handle): ?User => User::query()->where('email', $handle.'@sita.test')->first());

        if (! $prodi instanceof ProgramStudi || ! $admin instanceof User || $dosen->contains(null)) {
            return;
        }

        [$dosen1, $dosen2, $dosen3, $dosen4] = $dosen->all();
        $service = app(ThesisProjectAdminService::class);
        $role = Role::query()->firstOrCreate(['name' => AppRole::Mahasiswa->value]);

        // Two supervisors (dosen1 + dosen2), research phase, no exam scheduled.
        $student = $this->student($role, $prodi, 'e2e-pembimbing@sita.test', 'Kirana Dua Pembimbing', '2210511001');
        $project = $this->project($student, $prodi, 'research', 'Sistem Peringatan Dini Banjir Berbasis Sensor IoT');
        $this->completedSempro($project, $admin, $dosen3, $dosen4);
        $service->assignSupervisors($project, $admin->id, $dosen1->id, $dosen2->id, 'Pembimbing skenario Playwright.');

        // Expired mentorship ticket (issue #21): bimbingan entry points are blocked, reading still works.
        $student = $this->student($role, $prodi, 'e2e-tiket-habis@sita.test', 'Tiara Tiket Habis', '2210511010');
        $project = $this->project($student, $prodi, 'research', 'Pengaruh Pembelajaran Adaptif Terhadap Motivasi Belajar');
        $this->completedSempro($project, $admin, $dosen3, $dosen4);
        $service->assignSupervisors($project, $admin->id, $dosen1->id, null, 'Pembimbing skenario Playwright.');
        $project->forceFill(['mentorship_valid_until' => CarbonImmutable::now()->subDay()->toDateString()])->save();

        // Sempro scheduled with two examiners (dosen3 + dosen4).
        $student = $this->student($role, $prodi, 'e2e-sempro@sita.test', 'Dimas Penguji Sempro', '2210511002');
        $project = $this->project($student, $prodi, 'sempro', 'Klasifikasi Citra Daun Padi Menggunakan Jaringan Konvolusi');
        $service->scheduleSempro(
            $project,
            $admin->id,
            CarbonImmutable::now()->addDays(10)->setTime(9, 0)->toDateTimeString(),
            'Ruang Seminar E2E',
            'offline',
            [$dosen3->id, $dosen4->id],
        );

        // OBE student: Ujian Sinopsis scheduled with two examiners (dosen3 + dosen4); score and comment required.
        $student = $this->student($role, $prodi, 'e2e-sinopsis@sita.test', 'Wulan Ujian Sinopsis', '2210511006', isObeCurriculum: true);
        $project = $this->project($student, $prodi, 'sempro', 'Deteksi Hoaks Berita Daring Dengan Model Bahasa');
        $service->scheduleSempro(
            $project,
            $admin->id,
            CarbonImmutable::now()->addDays(11)->setTime(9, 0)->toDateTimeString(),
            'Ruang Sinopsis E2E',
            'offline',
            [$dosen3->id, $dosen4->id],
        );

        // Approved title and proposal, no sempro yet: the kaprodi schedules it.
        $student = $this->student($role, $prodi, 'e2e-jadwalsempro@sita.test', 'Laras Siap Sempro', '2210511003');
        $this->project($student, $prodi, 'sempro', 'Aplikasi Pemantauan Kualitas Udara Dalam Ruangan');

        // Sempro passed, no supervisors yet: the kaprodi assigns them, then schedules sidang.
        $student = $this->student($role, $prodi, 'e2e-pembimbingbaru@sita.test', 'Raka Belum Pembimbing', '2210511004');
        $project = $this->project($student, $prodi, 'sempro', 'Optimasi Rute Pengiriman Logistik Dengan Algoritma Genetika');
        $this->completedSempro($project, $admin, $dosen3, $dosen4);

        // Sidang scheduled: supervisors dosen1 + dosen2 plus examiner dosen3.
        $student = $this->student($role, $prodi, 'e2e-sidang@sita.test', 'Sinta Sidang Terjadwal', '2210511005');
        $project = $this->project($student, $prodi, 'research', 'Prediksi Kelulusan Tepat Waktu Dengan Pohon Keputusan');
        $this->completedSempro($project, $admin, $dosen3, $dosen4);
        $service->assignSupervisors($project, $admin->id, $dosen1->id, $dosen2->id, 'Pembimbing skenario Playwright.');
        $service->scheduleSidang(
            $project->fresh(),
            $admin->id,
            CarbonImmutable::now()->addDays(14)->setTime(10, 0)->toDateTimeString(),
            'Ruang Sidang E2E',
            'offline',
            [$dosen1->id, $dosen2->id, $dosen3->id],
        );

        // Mahasiswa without a phone number: the pop-up must block until one is saved.
        $this->student($role, $prodi, 'e2e-popup@sita.test', 'Yudha Nomor HP Kosong', '2210511007', withPhoneNumber: false);
    }

    private function student(Role $role, ProgramStudi $prodi, string $email, string $name, string $nim, bool $isObeCurriculum = false, bool $withPhoneNumber = true): User
    {
        $student = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'phone_number' => $withPhoneNumber ? '08'.str_pad(substr(sprintf('%u', crc32($email)), -10), 10, '0', STR_PAD_LEFT) : null,
                'phone_number_confirmed_at' => $withPhoneNumber ? now() : null,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'last_active_role' => AppRole::Mahasiswa->value,
            ],
        );
        $student->roles()->syncWithoutDetaching([$role->id]);

        MahasiswaProfile::query()->updateOrCreate(
            ['user_id' => $student->id],
            [
                'nim' => $nim,
                'program_studi_id' => $prodi->id,
                'degree_level' => DegreeLevel::S1->value,
                'concentration' => 'Sistem Cerdas',
                'angkatan' => 2022,
                'is_active' => true,
                'is_obe_curriculum' => $isObeCurriculum,
            ],
        );

        return $student;
    }

    /**
     * Rebuilds the student's project: approved title plus the proposal document.
     */
    private function project(User $student, ProgramStudi $prodi, string $phase, string $title): ThesisProject
    {
        ThesisProject::query()->where('student_user_id', $student->id)->delete();

        $startedAt = CarbonImmutable::now()->subMonths(2);

        $project = ThesisProject::query()->create([
            'student_user_id' => $student->id,
            'program_studi_id' => $prodi->id,
            'phase' => $phase,
            'state' => 'active',
            'started_at' => $startedAt,
            'created_by' => $student->id,
        ]);

        $titleVersion = ThesisProjectTitle::query()->create([
            'project_id' => $project->id,
            'version_no' => 1,
            'title_id' => $title,
            'title_en' => $title,
            'proposal_summary' => 'Ringkasan proposal skenario Playwright.',
            'status' => 'approved',
            'submitted_by_user_id' => $student->id,
            'submitted_at' => $startedAt,
            'decided_at' => $startedAt->addDays(3),
        ]);

        $storagePath = 'thesis/proposals/e2e-'.$student->id.'-proposal.pdf';
        Storage::disk('public')->put($storagePath, 'demo thesis document');

        ThesisDocument::query()->create([
            'project_id' => $project->id,
            'title_version_id' => $titleVersion->id,
            'uploaded_by_user_id' => $student->id,
            'kind' => 'proposal',
            'version_no' => 1,
            'status' => 'active',
            'title' => 'Proposal Skripsi',
            'storage_disk' => 'public',
            'storage_path' => $storagePath,
            'stored_file_name' => basename($storagePath),
            'file_name' => basename($storagePath),
            'mime_type' => 'application/pdf',
            'file_size_kb' => 1,
            'uploaded_at' => $startedAt,
        ]);

        return $project;
    }

    private function completedSempro(ThesisProject $project, User $admin, User $examinerOne, User $examinerTwo): void
    {
        $heldAt = CarbonImmutable::now()->subMonth();

        $defense = ThesisDefense::query()->create([
            'project_id' => $project->id,
            'title_version_id' => $project->titles()->value('id'),
            'type' => 'sempro',
            'attempt_no' => 1,
            'status' => 'completed',
            'result' => 'pass',
            'scheduled_for' => $heldAt,
            'location' => 'Ruang Seminar E2E',
            'mode' => 'offline',
            'created_by' => $admin->id,
            'decided_by' => $admin->id,
            'decision_at' => $heldAt->addHours(2),
            'notes' => 'Sempro disetujui.',
        ]);

        foreach ([$examinerOne, $examinerTwo] as $index => $examiner) {
            ThesisDefenseExaminer::query()->create([
                'defense_id' => $defense->id,
                'lecturer_user_id' => $examiner->id,
                'role' => 'examiner',
                'order_no' => $index + 1,
                'decision' => 'pass',
                'score' => 80,
                'notes' => 'Layak dilanjutkan.',
                'decided_at' => $heldAt->addHour(),
                'assigned_by' => $admin->id,
            ]);
        }
    }
}
