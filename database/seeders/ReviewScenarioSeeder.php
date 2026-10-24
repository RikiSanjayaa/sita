<?php

namespace Database\Seeders;

use App\Enums\AppRole;
use App\Enums\DegreeLevel;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipChatThread;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisDocument;
use App\Models\ThesisProject;
use App\Models\ThesisProjectEvent;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisRevision;
use App\Models\User;
use App\Services\ThesisProjectAdminService;
use App\Support\WitaDateTime;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Review data for features the product owner has not tested yet. Safe to run
 * repeatedly on a live database:
 *
 *   php artisan db:seed --class=ReviewScenarioSeeder --force
 *
 * It only touches the demo accounts listed below (matched by email) and, for
 * the Laila correction, the old project of laila@sita.test. Nothing else.
 *
 * - Ticket (#21): three students with an active project and a mentorship
 *   ticket that is valid, expires within 14 days, and already expired.
 * - Import (#24): three students without any project, matching the rows of
 *   docs/samples/contoh-import-tugas-akhir.xlsx.
 */
class ReviewScenarioSeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, email: string, nim: string, title: string, primary: string, secondary: ?string, startedMonthsAgo: float, daysLeft: ?int, monthsLeft: ?int}>
     */
    private const TICKET_STUDENTS = [
        [
            'name' => 'Dewi Anggraini', 'email' => 'dewi.anggraini@sita.test', 'nim' => '2210510101',
            'title' => 'Sistem Rekomendasi Mata Kuliah Pilihan Berbasis Collaborative Filtering',
            'primary' => 'dosen@sita.test', 'secondary' => 'dosen2@sita.test',
            'startedMonthsAgo' => 2, 'daysLeft' => null, 'monthsLeft' => 10,
        ],
        [
            'name' => 'Fajar Ramadhan', 'email' => 'fajar.ramadhan@sita.test', 'nim' => '2210510102',
            'title' => 'Deteksi Anomali Lalu Lintas Jaringan Kampus Menggunakan Isolation Forest',
            'primary' => 'dosen5@sita.test', 'secondary' => null,
            'startedMonthsAgo' => 11.5, 'daysLeft' => 9, 'monthsLeft' => null,
        ],
        [
            'name' => 'Hendra Gunawan', 'email' => 'hendra.gunawan@sita.test', 'nim' => '2210510103',
            'title' => 'Analisis Sentimen Ulasan Layanan Akademik Daring dengan IndoBERT',
            'primary' => 'dosen6@sita.test', 'secondary' => null,
            'startedMonthsAgo' => 13, 'daysLeft' => -1, 'monthsLeft' => null,
        ],
    ];

    /**
     * Students without any project, ready for the sample import file.
     *
     * @var array<int, array{name: string, email: string, nim: string}>
     */
    private const IMPORT_STUDENTS = [
        ['name' => 'Putri Maharani', 'email' => 'putri.maharani@sita.test', 'nim' => '2210510111'],
        ['name' => 'Rizal Firmansyah', 'email' => 'rizal.firmansyah@sita.test', 'nim' => '2210510112'],
        ['name' => 'Siska Amelia', 'email' => 'siska.amelia@sita.test', 'nim' => '2210510113'],
    ];

    public function run(): void
    {
        $prodi = ProgramStudi::query()->where('slug', 'ilkom')->first();
        $admin = User::query()->where('email', 'admin@sita.test')->first();

        if (! $prodi instanceof ProgramStudi || ! $admin instanceof User) {
            return;
        }

        $role = Role::query()->firstOrCreate(['name' => AppRole::Mahasiswa->value]);

        foreach (self::TICKET_STUDENTS as $account) {
            $this->seedTicketStudent($account, $prodi, $admin, $role);
        }

        foreach (self::IMPORT_STUDENTS as $account) {
            $this->student($role, $prodi, $account['email'], $account['name'], $account['nim']);
        }

        $this->correctLailaHistoricalRestart($admin);
    }

    /**
     * @param  array{name: string, email: string, nim: string, title: string, primary: string, secondary: ?string, startedMonthsAgo: float, daysLeft: ?int, monthsLeft: ?int}  $account
     */
    private function seedTicketStudent(array $account, ProgramStudi $prodi, User $admin, Role $role): void
    {
        $primary = User::query()->where('email', $account['primary'])->first();
        $secondary = $account['secondary'] === null ? null : User::query()->where('email', $account['secondary'])->first();
        $examiners = User::query()->whereIn('email', ['dosen3@sita.test', 'dosen4@sita.test'])->orderBy('email')->get();

        if (! $primary instanceof User || $examiners->count() < 2) {
            return;
        }

        $student = $this->student($role, $prodi, $account['email'], $account['name'], $account['nim']);
        $project = ThesisProject::query()->where('student_user_id', $student->id)->orderBy('id')->first();

        if (! $project instanceof ThesisProject) {
            $project = $this->createProject($student, $prodi, $admin, $account, $examiners->all(), $primary, $secondary);
        }

        // Dates are refreshed on every run so "expiring" and "expired" stay true relative to today.
        $today = Carbon::today(WitaDateTime::TIMEZONE);
        $until = $account['monthsLeft'] !== null
            ? $today->copy()->addMonthsNoOverflow($account['monthsLeft'])
            : $today->copy()->addDays((int) $account['daysLeft']);
        $project->forceFill([
            'mentorship_valid_until' => $until->toDateString(),
            'mentorship_valid_from' => $until->copy()->subMonthsNoOverflow((int) config('sita.thesis_ticket_months', 12))->toDateString(),
        ])->save();
    }

    /**
     * @param  array{title: string, startedMonthsAgo: float}  $account
     * @param  array<int, User>  $examiners
     */
    private function createProject(User $student, ProgramStudi $prodi, User $admin, array $account, array $examiners, User $primary, ?User $secondary): ThesisProject
    {
        $startedAt = CarbonImmutable::now()->subDays((int) round($account['startedMonthsAgo'] * 30));

        $project = ThesisProject::query()->create([
            'student_user_id' => $student->id,
            'program_studi_id' => $prodi->id,
            'phase' => 'research',
            'state' => 'active',
            'started_at' => $startedAt,
            'created_by' => $student->id,
        ]);

        $title = ThesisProjectTitle::query()->create([
            'project_id' => $project->id,
            'version_no' => 1,
            'title_id' => $account['title'],
            'title_en' => $account['title'],
            'proposal_summary' => 'Data contoh untuk meninjau masa aktif bimbingan.',
            'status' => 'approved',
            'submitted_by_user_id' => $student->id,
            'submitted_at' => $startedAt,
            'decided_by_user_id' => $admin->id,
            'decided_at' => $startedAt->addDays(3),
        ]);

        $heldAt = $startedAt->addDays(30);
        $defense = ThesisDefense::query()->create([
            'project_id' => $project->id,
            'title_version_id' => $title->id,
            'type' => 'sempro',
            'attempt_no' => 1,
            'status' => 'completed',
            'result' => 'pass',
            'scheduled_for' => $heldAt,
            'location' => 'Ruang Seminar 1',
            'mode' => 'offline',
            'created_by' => $admin->id,
            'decided_by' => $admin->id,
            'decision_at' => $heldAt->addHours(2),
            'notes' => 'Sempro disetujui.',
        ]);

        foreach ($examiners as $index => $examiner) {
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

        return app(ThesisProjectAdminService::class)->assignSupervisors(
            $project,
            $admin->id,
            $primary->id,
            $secondary?->id,
            'Pembimbing data contoh masa aktif.',
            silent: true,
        );
    }

    private function student(Role $role, ProgramStudi $prodi, string $email, string $name, string $nim): User
    {
        $student = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'phone_number' => '08'.str_pad(substr(sprintf('%u', crc32($email)), -10), 10, '0', STR_PAD_LEFT),
                'phone_number_confirmed_at' => now(),
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'last_active_role' => AppRole::Mahasiswa->value,
            ],
        );
        $student->roles()->syncWithoutDetaching([$role->id]);

        MahasiswaProfile::query()->firstOrCreate(
            ['user_id' => $student->id],
            [
                'nim' => $nim,
                'program_studi_id' => $prodi->id,
                'degree_level' => DegreeLevel::S1->value,
                'concentration' => 'Sistem Cerdas',
                'angkatan' => 2022,
                'is_active' => true,
            ],
        );

        return $student;
    }

    /**
     * Older seeds marked Laila's abandoned first attempt as completed (sidang
     * passed, final manuscript), so she looked finished while her current
     * attempt sat in Review Judul. The scenario says she changed direction, so
     * that attempt is a cancelled restart: sempro passed, no sidang, no final
     * manuscript. Only touches the old project of laila@sita.test while it still
     * has that completed shape; a no-op afterwards and on fresh seeds.
     */
    private function correctLailaHistoricalRestart(User $admin): void
    {
        $student = User::query()->where('email', 'laila@sita.test')->first();

        if (! $student instanceof User) {
            return;
        }

        $project = ThesisProject::query()
            ->where('student_user_id', $student->id)
            ->where('state', 'completed')
            ->whereBetween('started_at', ['2025-09-10 00:00:00', '2025-09-10 23:59:59'])
            ->whereExists(fn ($query) => $query->from('thesis_projects as newer')
                ->whereColumn('newer.student_user_id', 'thesis_projects.student_user_id')
                ->where('newer.started_at', '>', DB::raw('thesis_projects.started_at')))
            ->first();

        if (! $project instanceof ThesisProject) {
            return;
        }

        $cancelledAt = CarbonImmutable::parse('2025-12-02 10:00:00');

        DB::transaction(function () use ($project, $admin, $cancelledAt): void {
            $sidangIds = ThesisDefense::query()->where('project_id', $project->id)->where('type', 'sidang')->pluck('id');

            ThesisDocument::query()->where('project_id', $project->id)->where('kind', 'final_manuscript')->delete();
            ThesisRevision::query()->where('project_id', $project->id)->whereIn('defense_id', $sidangIds)->delete();

            MentorshipChatThread::query()->where('type', 'sidang')->whereIn('context_id', $sidangIds)->get()
                ->each(function (MentorshipChatThread $thread): void {
                    $thread->messages()->delete();
                    $thread->participants()->delete();
                    $thread->readMarkers()->delete();
                    $thread->delete();
                });

            ThesisDefenseExaminer::query()->whereIn('defense_id', $sidangIds)->delete();
            ThesisDefense::query()->whereIn('id', $sidangIds)->delete();

            $project->supervisorAssignments()->update(['ended_at' => $cancelledAt, 'status' => 'ended']);
            ThesisProjectEvent::query()->where('project_id', $project->id)->where('event_type', 'project_closed')->delete();
            ThesisProjectEvent::query()->updateOrCreate(
                ['project_id' => $project->id, 'event_type' => 'project_cancelled', 'occurred_at' => $cancelledAt],
                [
                    'actor_user_id' => $admin->id,
                    'label' => 'Skripsi dibatalkan',
                    'description' => 'Mahasiswa mengganti arah penelitian; skripsi lama dibatalkan.',
                    'payload' => null,
                ],
            );

            $project->forceFill([
                'phase' => 'research',
                'state' => 'cancelled',
                'completed_at' => null,
                'cancelled_at' => $cancelledAt,
                'archived_at' => null,
                'archive_summary' => null,
                'closed_by' => $admin->id,
                'notes' => 'Attempt lama dibatalkan karena mahasiswa mengganti arah penelitian.',
            ])->save();
        });
    }
}
