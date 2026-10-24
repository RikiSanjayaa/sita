<?php

use App\Models\DosenProfile;
use App\Models\KaprodiAssignment;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
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
        ->and($items['mentorship-lapsed'])->toMatchArray(['value' => 1, 'names' => ['Mahasiswa Lapsed'], 'tone' => 'danger'])
        ->and($items['mentorship-lapsed']['href'])->toBe(route('kaprodi.mahasiswa.index', ['activeFocus' => 'masa_habis']))
        ->and($items['without-supervisor'])->toMatchArray(['value' => 1, 'names' => ['Mahasiswa Tanpa Pembimbing']])
        ->and($items['schedule-sidang'])->toMatchArray(['value' => 1, 'names' => ['Mahasiswa Sidang Siap']])
        ->and($items['schedule-sidang']['href'])->toBe(route('kaprodi.sempro-sidang', ['jadwalkan' => 'sidang']))
        // The student awaiting finalization already has a Sempro, so only the one without a schedule is listed.
        ->and($items['schedule-sempro'])->toMatchArray(['value' => 1, 'names' => ['Mahasiswa Sempro Siap']])
        ->and($items['mentorship-minimum']['names'])->toBe(['Mahasiswa Sidang Siap'])
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
        ->and($full['over-quota'])->toMatchArray(['value' => 1, 'names' => ['Dosen Penuh'], 'href' => route('kaprodi.dosen-prodi')]);

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

    expect($items['without-supervisor']['names'])->toBe(['Mahasiswa Lulus Sempro'])
        ->and($items['schedule-sempro']['names'])->toBe(['Mahasiswa Belum Sempro'])
        ->and($notYet->phase)->toBe('sempro');
});
