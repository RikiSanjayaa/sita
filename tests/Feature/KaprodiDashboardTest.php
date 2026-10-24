<?php

use App\Models\DosenProfile;
use App\Models\KaprodiAssignment;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\ThesisRevision;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function dashboardKaprodi(ProgramStudi $prodi, array $capabilities = []): User
{
    $kaprodi = User::factory()->asKaprodi()->create();

    KaprodiAssignment::factory()->create([
        'program_studi_id' => $prodi->id,
        'user_id' => $kaprodi->id,
        'capabilities' => [...KaprodiAssignment::defaultCapabilities(), ...$capabilities],
    ]);

    return $kaprodi->fresh();
}

function dashboardStudent(ProgramStudi $prodi, string $name, string $phase = 'research', array $project = [], bool $active = true): ThesisProject
{
    $student = User::factory()->asMahasiswa()->create(['name' => $name]);
    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'is_active' => $active,
    ]);

    return ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'phase' => $phase,
        'state' => 'active',
        'started_at' => now()->subMonth(),
        ...$project,
    ]);
}

function dashboardLecturer(ProgramStudi $prodi, string $name = 'Dosen', int $quota = 14): User
{
    $lecturer = User::factory()->asDosen()->create(['name' => $name]);
    DosenProfile::factory()->create(['user_id' => $lecturer->id, 'program_studi_id' => $prodi->id, 'supervision_quota' => $quota]);

    return $lecturer;
}

function dashboardSupervise(ThesisProject $project, User $lecturer, string $role = 'primary'): void
{
    ThesisSupervisorAssignment::query()->create([
        'project_id' => $project->id,
        'lecturer_user_id' => $lecturer->id,
        'role' => $role,
        'status' => 'active',
        'assigned_by' => $lecturer->id,
        'started_at' => now()->subWeek(),
    ]);
}

/**
 * @return array<string, array<string, mixed>> action items keyed by id
 */
function dashboardItems(User $kaprodi): array
{
    $page = test()->actingAs($kaprodi)->get('/kaprodi/dashboard')->assertOk()->viewData('page');

    return collect(data_get($page, 'props.actionItems'))->keyBy('id')->all();
}

it('lists what needs action, most urgent first, with counts, names and filtered links', function (): void {
    $prodi = ProgramStudi::factory()->create();
    $kaprodi = dashboardKaprodi($prodi);
    $lecturer = dashboardLecturer($prodi, 'Dosen Pembimbing');

    $lapsed = dashboardStudent($prodi, 'Mahasiswa Lapsed', 'research', ['mentorship_valid_until' => now()->subDay()->toDateString()]);
    dashboardSupervise($lapsed, $lecturer);
    dashboardSupervise(dashboardStudent($prodi, 'Mahasiswa Sidang Siap', 'sidang'), $lecturer);
    dashboardStudent($prodi, 'Mahasiswa Tanpa Pembimbing', 'research');
    dashboardStudent($prodi, 'Mahasiswa Sempro Siap', 'sempro');
    $finalizing = dashboardStudent($prodi, 'Mahasiswa Finalisasi', 'sempro');
    ThesisDefense::query()->create([
        'project_id' => $finalizing->id, 'type' => 'sempro', 'attempt_no' => 1,
        'status' => 'awaiting_finalization', 'scheduled_for' => now()->subDay(), 'location' => 'R1',
    ]);

    $items = dashboardItems($kaprodi);

    expect(array_keys($items))->toBe([
        'awaiting-finalization',
        'mentorship-lapsed',
        'without-supervisor',
        'schedule-sidang',
        'schedule-sempro',
        'mentorship-minimum',
    ])
        ->and($items['awaiting-finalization'])->toMatchArray(['value' => 1, 'names' => ['Mahasiswa Finalisasi']])
        ->and($items['awaiting-finalization']['href'])->toBe(route('kaprodi.sempro-sidang', ['status' => 'awaiting_finalization']))
        ->and($items['mentorship-lapsed'])->toMatchArray(['value' => 1, 'names' => [], 'tone' => 'danger'])
        ->and($items['mentorship-lapsed']['href'])->toBe(route('kaprodi.mahasiswa.index', ['activeFocus' => 'masa_habis']))
        ->and($items['without-supervisor'])->toMatchArray(['value' => 1, 'names' => []])
        ->and($items['schedule-sidang'])->toMatchArray(['value' => 1, 'names' => []])
        ->and($items['schedule-sidang']['href'])->toBe(route('kaprodi.sempro-sidang', ['jadwalkan' => 'sidang']))
        // The student awaiting finalization already has a Sempro, so only the one without a schedule is counted.
        ->and($items['schedule-sempro'])->toMatchArray(['value' => 1, 'names' => []])
        ->and($items['mentorship-minimum'])->toMatchArray(['value' => 1, 'names' => []])
        ->and($items['mentorship-minimum']['href'])->toBe(route('kaprodi.mahasiswa.index', ['activeFocus' => 'bimbingan_kurang']));
});

it('keeps archived, deactivated and other program studi students out of every queue', function (): void {
    $prodi = ProgramStudi::factory()->create();
    $otherProdi = ProgramStudi::factory()->create();
    $kaprodi = dashboardKaprodi($prodi);

    dashboardStudent($prodi, 'Mahasiswa Arsip', 'sidang', [
        'state' => 'completed',
        'completed_at' => now()->subMonths(3),
        'archived_at' => now()->subMonth(),
        'mentorship_valid_until' => now()->subYear()->toDateString(),
    ]);
    dashboardStudent($prodi, 'Mahasiswa Arsip Aktif', 'sidang', [
        'archived_at' => now()->subMonth(),
        'mentorship_valid_until' => now()->subYear()->toDateString(),
    ]);
    dashboardStudent($prodi, 'Mahasiswa Nonaktif', 'research', ['mentorship_valid_until' => now()->subYear()->toDateString()], active: false);
    dashboardStudent($otherProdi, 'Mahasiswa Prodi Lain', 'research', ['mentorship_valid_until' => now()->subYear()->toDateString()]);
    $finalizingElsewhere = dashboardStudent($otherProdi, 'Mahasiswa Finalisasi Lain', 'sempro');
    ThesisDefense::query()->create([
        'project_id' => $finalizingElsewhere->id, 'type' => 'sempro', 'attempt_no' => 1,
        'status' => 'awaiting_finalization', 'scheduled_for' => now()->subDay(), 'location' => 'R1',
    ]);

    expect(dashboardItems($kaprodi))->toBe([]);

    $this->actingAs($kaprodi)->get('/kaprodi/dashboard')->assertInertia(fn (Assert $page): Assert => $page
        ->where('attentionStudents.total', 0));
});

it('hides scheduling, supervisor and quota items from a kaprodi without that capability', function (): void {
    $prodi = ProgramStudi::factory()->create();
    $lecturer = dashboardLecturer($prodi, 'Dosen Penuh', quota: 1);
    dashboardSupervise(dashboardStudent($prodi, 'Mahasiswa Satu', 'research'), $lecturer);
    dashboardSupervise(dashboardStudent($prodi, 'Mahasiswa Dua', 'research'), $lecturer);
    dashboardStudent($prodi, 'Mahasiswa Tanpa Pembimbing', 'research');
    dashboardStudent($prodi, 'Mahasiswa Sempro', 'sempro');
    dashboardStudent($prodi, 'Mahasiswa Sidang', 'sidang');

    $kaprodi = dashboardKaprodi($prodi);
    $full = dashboardItems($kaprodi);

    expect($full)->toHaveKeys(['over-quota', 'without-supervisor', 'schedule-sempro', 'schedule-sidang'])
        ->and($full['over-quota'])->toMatchArray(['value' => 1, 'names' => [], 'href' => route('kaprodi.dosen-prodi')]);

    $kaprodi->kaprodiAssignment->update(['capabilities' => [
        ...KaprodiAssignment::defaultCapabilities(),
        KaprodiAssignment::CAPABILITY_SCHEDULE_SEMPRO => false,
        KaprodiAssignment::CAPABILITY_SCHEDULE_SIDANG => false,
        KaprodiAssignment::CAPABILITY_MANAGE_SUPERVISORS => false,
        KaprodiAssignment::CAPABILITY_MANAGE_LECTURER_QUOTA => false,
    ]]);

    expect(dashboardItems($kaprodi->fresh()))->toBe([]);
});

it('lists students without recent activity as inactive', function (): void {
    $prodi = ProgramStudi::factory()->create();
    $kaprodi = dashboardKaprodi($prodi);
    $lecturer = dashboardLecturer($prodi);
    $idle = dashboardStudent($prodi, 'Mahasiswa Diam', 'research');
    dashboardSupervise($idle, $lecturer);
    $idle->newQuery()->whereKey($idle->id)->toBase()->update(['updated_at' => now()->subDays(100), 'started_at' => now()->subDays(120)]);

    $items = dashboardItems($kaprodi);

    expect($items)->toHaveKey('inactive')
        ->and($items['inactive'])->toMatchArray(['value' => 1, 'names' => ['Mahasiswa Diam'], 'tone' => 'danger'])
        ->and($items['inactive']['href'])->toBe(route('kaprodi.mahasiswa.index', ['activeFocus' => 'tidak_aktif']));
});

it('offers a new Sempro again after a failed or cancelled attempt but not while one is planned', function (): void {
    $prodi = ProgramStudi::factory()->create();
    $kaprodi = dashboardKaprodi($prodi);
    $failed = dashboardStudent($prodi, 'Mahasiswa Gagal', 'sempro');
    $planned = dashboardStudent($prodi, 'Mahasiswa Terjadwal', 'sempro');
    ThesisDefense::query()->create([
        'project_id' => $failed->id, 'type' => 'sempro', 'attempt_no' => 1,
        'status' => 'completed', 'result' => 'fail', 'scheduled_for' => now()->subWeek(), 'location' => 'R1',
    ]);
    ThesisDefense::query()->create([
        'project_id' => $planned->id, 'type' => 'sempro', 'attempt_no' => 1,
        'status' => 'scheduled', 'scheduled_for' => now()->addWeek(), 'location' => 'R1',
    ]);

    expect(dashboardItems($kaprodi)['schedule-sempro']['names'])->toBe(['Mahasiswa Gagal']);
});

it('shows only exams within today and the next seven days in the agenda, with Indonesian dates', function (): void {
    $prodi = ProgramStudi::factory()->create();
    $kaprodi = dashboardKaprodi($prodi);
    $soon = dashboardStudent($prodi, 'Mahasiswa Minggu Ini', 'sempro');
    $later = dashboardStudent($prodi, 'Mahasiswa Bulan Depan', 'sempro');
    $scheduledFor = now()->addDays(3)->setTime(9, 0);
    ThesisDefense::query()->create([
        'project_id' => $soon->id, 'type' => 'sempro', 'attempt_no' => 1,
        'status' => 'scheduled', 'scheduled_for' => $scheduledFor, 'location' => 'R1',
    ]);
    ThesisDefense::query()->create([
        'project_id' => $later->id, 'type' => 'sempro', 'attempt_no' => 1,
        'status' => 'scheduled', 'scheduled_for' => now()->addDays(20), 'location' => 'R1',
    ]);

    $this->actingAs($kaprodi)->get('/kaprodi/dashboard')->assertInertia(fn (Assert $page): Assert => $page
        ->has('upcomingAgenda', 1)
        ->where('upcomingAgenda.0.title', 'Mahasiswa Minggu Ini')
        ->where('upcomingAgenda.0.date', fn (string $date): bool => str_contains($date, $scheduledFor->locale('id')->translatedFormat('M Y'))
            && str_ends_with($date, 'WITA')));
});

it('ranks students who need attention with the lapsed masa aktif bimbingan first', function (): void {
    $prodi = ProgramStudi::factory()->create();
    $kaprodi = dashboardKaprodi($prodi);
    $lecturer = dashboardLecturer($prodi);
    $lapsed = dashboardStudent($prodi, 'Zahra Lapsed', 'research', ['mentorship_valid_until' => now()->subDay()->toDateString()]);
    $orphan = dashboardStudent($prodi, 'Adi Tanpa Pembimbing', 'research');
    dashboardSupervise($lapsed, $lecturer);

    $this->actingAs($kaprodi)->get('/kaprodi/dashboard')->assertInertia(fn (Assert $page): Assert => $page
        ->where('attentionStudents.total', 2)
        ->where('attentionStudents.students.0.name', 'Zahra Lapsed')
        ->where('attentionStudents.students.0.problems.0', 'Masa aktif bimbingan habis')
        ->where('attentionStudents.students.0.progress.detail.ticket.suspended', true)
        ->where('attentionStudents.students.1.name', 'Adi Tanpa Pembimbing')
        ->where('attentionStudents.students.1.progress.phase', $orphan->phase));
});

it('flags students on the Mahasiswa Prodi rows with the same rule as the dashboard', function (): void {
    $prodi = ProgramStudi::factory()->create();
    $kaprodi = dashboardKaprodi($prodi);
    $lecturer = dashboardLecturer($prodi);
    $lapsed = dashboardStudent($prodi, 'Mahasiswa Lapsed', 'research', ['mentorship_valid_until' => now()->subDay()->toDateString()]);
    dashboardSupervise($lapsed, $lecturer);
    dashboardStudent($prodi, 'Mahasiswa Tanpa Pembimbing', 'research');
    dashboardStudent($prodi, 'Mahasiswa Arsip', 'sidang', ['state' => 'completed', 'completed_at' => now()->subYear(), 'archived_at' => now()->subMonth()]);

    $rows = collect(data_get($this->actingAs($kaprodi)->get('/kaprodi/mahasiswa')->assertOk()->viewData('page'), 'props.students'))->keyBy('name');

    expect($rows['Mahasiswa Lapsed']['needsAction'])->toContain('masa_habis')
        ->and($rows['Mahasiswa Tanpa Pembimbing']['needsAction'])->toContain('tanpa_pembimbing')
        ->and($rows['Mahasiswa Arsip']['needsAction'])->toBe([]);
});

it('shows Semua beres data when nothing needs action', function (): void {
    $prodi = ProgramStudi::factory()->create();

    $this->actingAs(dashboardKaprodi($prodi))->get('/kaprodi/dashboard')->assertInertia(fn (Assert $page): Assert => $page
        ->component('kaprodi/dashboard')
        ->where('actionItems', [])
        ->where('attentionStudents.students', [])
        ->where('upcomingAgenda', [])
        ->missing('workSummary'));
});

it('asks for supervisors once the Sempro is passed even before the phase moves on', function (): void {
    $prodi = ProgramStudi::factory()->create();
    $kaprodi = dashboardKaprodi($prodi);
    $passed = dashboardStudent($prodi, 'Mahasiswa Lulus Sempro', 'sempro');
    $notYet = dashboardStudent($prodi, 'Mahasiswa Belum Sempro', 'sempro');
    ThesisDefense::query()->create([
        'project_id' => $passed->id, 'type' => 'sempro', 'attempt_no' => 1,
        'status' => 'completed', 'result' => 'pass_with_revision', 'scheduled_for' => now()->subWeek(), 'location' => 'R1',
    ]);

    $items = dashboardItems($kaprodi);

    expect($items['without-supervisor'])->toMatchArray(['value' => 1, 'names' => ['Mahasiswa Lulus Sempro']])
        ->and($items['schedule-sempro'])->toMatchArray(['value' => 1, 'names' => []])
        ->and($notYet->phase)->toBe('sempro');
});

it('runs the same number of queries however many students the program studi has', function (): void {
    $prodi = ProgramStudi::factory()->create();
    $kaprodi = dashboardKaprodi($prodi);
    $lecturer = dashboardLecturer($prodi);
    $countQueries = function () use ($kaprodi): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($kaprodi)->get('/kaprodi/dashboard')->assertOk();

        return count(DB::getQueryLog());
    };
    $seed = function (int $count) use ($prodi, $lecturer): void {
        foreach (range(1, $count) as $number) {
            $project = dashboardStudent($prodi, "Mahasiswa {$number}", $number % 2 === 0 ? 'sidang' : 'research', ['mentorship_valid_until' => now()->subDay()->toDateString()]);
            dashboardSupervise($project, $lecturer);
        }
    };

    $seed(2);
    $countQueries();
    $few = $countQueries();
    $seed(8);
    $many = $countQueries();

    expect($many)->toBe($few);
});

function dashboardExam(ThesisProject $project, string|CarbonImmutable $scheduledFor, string $status = 'scheduled', string $type = 'sempro'): ThesisDefense
{
    return ThesisDefense::query()->create([
        'project_id' => $project->id, 'type' => $type, 'attempt_no' => 1,
        'status' => $status,
        'scheduled_for' => is_string($scheduledFor) ? CarbonImmutable::parse($scheduledFor, 'Asia/Makassar') : $scheduledFor,
        'location' => 'R1',
    ]);
}

it('adds WITA day label, clock time and today flag to agenda entries and bounds the window by WITA days', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-24 22:30', 'Asia/Makassar'));
    $prodi = ProgramStudi::factory()->create();
    $kaprodi = dashboardKaprodi($prodi);
    foreach ([
        'Sebelum Hari Ini' => '2026-10-23 23:59',
        'Awal Hari Ini' => '2026-10-24 00:00',
        'Malam Ini' => '2026-10-24 23:50',
        'Besok Dini Hari' => '2026-10-25 00:10',
        'Batas Akhir' => '2026-10-31 23:59',
        'Lewat Batas' => '2026-11-01 00:00',
    ] as $name => $at) {
        dashboardExam(dashboardStudent($prodi, $name, 'sempro'), $at);
    }

    $page = $this->actingAs($kaprodi)->get('/kaprodi/dashboard')->assertOk()->viewData('page');
    $agenda = collect(data_get($page, 'props.upcomingAgenda'));

    expect($agenda->pluck('title')->all())->toBe(['Awal Hari Ini', 'Malam Ini', 'Besok Dini Hari', 'Batas Akhir'])
        ->and($agenda->map(fn (array $entry): array => [$entry['dayLabel'], $entry['time'], $entry['isToday']])->all())->toBe([
            ['Hari ini, 24 Oktober', '00.00', true],
            ['Hari ini, 24 Oktober', '23.50', true],
            ['Besok, 25 Oktober', '00.10', false],
            ['Sabtu, 31 Oktober', '23.59', false],
        ])
        ->and($agenda[0])->toHaveKeys(['id', 'badge', 'title', 'subtitle', 'date'])
        ->and($agenda[1]['date'])->toEndWith('WITA')
        ->and(data_get($page, 'props.stats.upcomingExams'))->toBe(4);
});

it('counts every scheduled exam in the window for the upcoming exam statistic, not only the truncated agenda', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-24 08:00', 'Asia/Makassar'));
    $prodi = ProgramStudi::factory()->create();
    $kaprodi = dashboardKaprodi($prodi);

    foreach (range(1, 8) as $hour) {
        dashboardExam(dashboardStudent($prodi, "Mahasiswa Terjadwal {$hour}", 'sempro'), CarbonImmutable::parse('2026-10-24 09:00', 'Asia/Makassar')->addHours($hour));
    }
    dashboardExam(dashboardStudent($prodi, 'Mahasiswa Finalisasi', 'sempro'), '2026-10-25 09:00', 'awaiting_finalization');
    dashboardExam(dashboardStudent($prodi, 'Mahasiswa Selesai', 'sempro'), '2026-10-25 10:00', 'completed');
    dashboardExam(dashboardStudent($prodi, 'Mahasiswa Batal', 'sempro'), '2026-10-25 11:00', 'cancelled');
    dashboardExam(dashboardStudent(ProgramStudi::factory()->create(), 'Mahasiswa Prodi Lain', 'sempro'), '2026-10-25 12:00');
    $revisionProject = dashboardStudent($prodi, 'Mahasiswa Revisi', 'research');
    ThesisRevision::query()->create([
        'project_id' => $revisionProject->id, 'requested_by_user_id' => $revisionProject->student_user_id,
        'status' => 'open', 'due_at' => CarbonImmutable::parse('2026-10-26 09:00', 'Asia/Makassar'),
        'notes' => 'Perbaiki pembahasan hasil penelitian.',
    ]);

    $props = data_get($this->actingAs($kaprodi)->get('/kaprodi/dashboard')->assertOk()->viewData('page'), 'props');
    $agenda = collect($props['upcomingAgenda']);

    // The agenda keeps its limits: six exams plus the revision. The statistic counts all nine exams and never the revision.
    expect($agenda->filter(fn (array $entry): bool => str_starts_with($entry['id'], 'defense-'))->count())->toBe(6)
        ->and($agenda->filter(fn (array $entry): bool => str_starts_with($entry['id'], 'revision-'))->count())->toBe(1)
        ->and($props['stats']['upcomingExams'])->toBe(9);
});

it('reports the new statistics from active program studi data whatever the kaprodi capabilities', function (): void {
    $prodi = ProgramStudi::factory()->create();
    $lecturer = dashboardLecturer($prodi);
    dashboardStudent($prodi, 'Tanpa Pembimbing A', 'research');
    dashboardStudent($prodi, 'Tanpa Pembimbing B', 'sidang');
    dashboardSupervise(dashboardStudent($prodi, 'Masa Habis', 'research', ['mentorship_valid_until' => now()->subDay()->toDateString()]), $lecturer);
    dashboardStudent($prodi, 'Nonaktif', 'research', ['mentorship_valid_until' => now()->subYear()->toDateString()], active: false);
    dashboardStudent($prodi, 'Arsip', 'sidang', ['state' => 'completed', 'completed_at' => now()->subYear(), 'archived_at' => now()->subMonth()]);
    dashboardStudent(ProgramStudi::factory()->create(), 'Prodi Lain', 'research', ['mentorship_valid_until' => now()->subYear()->toDateString()]);

    $statsFor = fn (User $kaprodi): array => data_get($this->actingAs($kaprodi)->get('/kaprodi/dashboard')->assertOk()->viewData('page'), 'props.stats');
    $full = dashboardKaprodi($prodi);
    $expected = ['students' => 5, 'archives' => 1, 'activeStudents' => 4, 'withoutSupervisors' => 2, 'upcomingExams' => 0, 'expiredMentorship' => 1];

    expect($statsFor($full))->toBe($expected);

    $full->kaprodiAssignment->update(['capabilities' => [
        ...KaprodiAssignment::defaultCapabilities(),
        KaprodiAssignment::CAPABILITY_SCHEDULE_SEMPRO => false,
        KaprodiAssignment::CAPABILITY_SCHEDULE_SIDANG => false,
        KaprodiAssignment::CAPABILITY_MANAGE_SUPERVISORS => false,
        KaprodiAssignment::CAPABILITY_MANAGE_LECTURER_QUOTA => false,
    ]]);

    expect(dashboardItems($full->fresh()))->not->toHaveKey('without-supervisor')
        ->and($statsFor($full->fresh()))->toBe($expected);
});

it('names at most one student and only on the leading permitted action item', function (): void {
    $prodi = ProgramStudi::factory()->create();
    $lecturer = dashboardLecturer($prodi);
    foreach (['Lapsed C', 'Lapsed A', 'Lapsed B'] as $name) {
        dashboardSupervise(dashboardStudent($prodi, $name, 'research', ['mentorship_valid_until' => now()->subDay()->toDateString()]), $lecturer);
    }
    dashboardStudent($prodi, 'Tanpa Pembimbing 1', 'research');
    dashboardStudent($prodi, 'Tanpa Pembimbing 2', 'research');

    $items = dashboardItems(dashboardKaprodi($prodi));

    expect(array_keys($items))->toBe(['mentorship-lapsed', 'without-supervisor'])
        ->and($items['mentorship-lapsed'])->toMatchArray(['value' => 3, 'names' => ['Lapsed A']])
        ->and($items['without-supervisor'])->toMatchArray(['value' => 2, 'names' => []]);

    // Hidden by capability, the supervisor item no longer leads; the next permitted item takes the single name.
    $otherProdi = ProgramStudi::factory()->create();
    dashboardStudent($otherProdi, 'Tanpa Pembimbing X', 'research');
    dashboardStudent($otherProdi, 'Sempro Siap', 'sempro');
    dashboardStudent($otherProdi, 'Sempro Siap Dua', 'sempro');
    $limited = dashboardItems(dashboardKaprodi($otherProdi, [KaprodiAssignment::CAPABILITY_MANAGE_SUPERVISORS => false]));

    expect(array_keys($limited))->toBe(['schedule-sempro'])
        ->and($limited['schedule-sempro'])->toMatchArray(['value' => 2, 'names' => ['Sempro Siap']]);
});
