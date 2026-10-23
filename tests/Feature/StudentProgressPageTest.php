<?php

use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\ThesisProjectNote;
use App\Models\ThesisProjectTitle;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * @param  array{name?: string, prodi?: ProgramStudi, phase?: string, angkatan?: int, state?: string, active?: bool}  $options
 */
function progressPageProject(array $options = []): ThesisProject
{
    $prodi = $options['prodi'] ?? ProgramStudi::factory()->create();
    $student = User::factory()->asMahasiswa()->create(['name' => $options['name'] ?? 'Mahasiswa Uji']);

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'angkatan' => $options['angkatan'] ?? 2022,
        'is_active' => $options['active'] ?? true,
    ]);

    return ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'phase' => $options['phase'] ?? 'research',
        'state' => $options['state'] ?? 'active',
        'started_at' => now()->subDays(60),
        'created_by' => $student->id,
    ]);
}

function progressPageApprovedTitle(ThesisProject $project, string $title): void
{
    ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => $title,
        'status' => 'approved',
        'submitted_by_user_id' => $project->student_user_id,
        'submitted_at' => now()->subDays(90),
    ]);
}

function progressPageDefense(ThesisProject $project, array $attributes): void
{
    $title = ThesisProjectTitle::query()->where('project_id', $project->id)->first()
        ?? ThesisProjectTitle::query()->create([
            'project_id' => $project->id,
            'version_no' => 1,
            'title_id' => 'Judul Agenda',
            'status' => 'approved',
            'submitted_by_user_id' => $project->student_user_id,
            'submitted_at' => now()->subDays(90),
        ]);

    ThesisDefense::query()->create(array_merge([
        'project_id' => $project->id,
        'title_version_id' => $title->id,
        'type' => 'sempro',
        'attempt_no' => 1,
        'status' => 'scheduled',
        'result' => 'pending',
        'scheduled_for' => now()->addDays(3)->setTime(13, 0),
        'location' => 'Ruang Seminar A',
        'mode' => 'offline',
    ], $attributes));
}

it('redirects guests to login', function (): void {
    $this->get('/progres-mahasiswa')->assertRedirect(route('login'));
});

it('opens for every logged-in role', function (string $state): void {
    $viewer = User::factory()->{$state}()->create();

    $this->actingAs($viewer)
        ->get('/progres-mahasiswa')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('progres-mahasiswa'));
})->with(['asMahasiswa', 'asDosen', 'asKaprodi', 'asAdmin', 'asSuperAdmin']);

it('searches the whole filtered set, not only the current page', function (): void {
    foreach (range(1, 12) as $number) {
        progressPageProject(['name' => sprintf('Mahasiswa %02d', $number)]);
    }
    progressPageProject(['name' => 'Zahra Unik']);

    $viewer = User::factory()->asAdmin()->create();

    $this->actingAs($viewer)
        ->get('/progres-mahasiswa?search=Zahra&per_page=10')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rows', 1)
            ->where('rows.0.name', 'Zahra Unik')
            ->where('pagination.total', 1));
});

it('paginates beyond the first page', function (): void {
    foreach (range(1, 12) as $number) {
        progressPageProject(['name' => sprintf('Mahasiswa %02d', $number)]);
    }

    $viewer = User::factory()->asAdmin()->create();

    $this->actingAs($viewer)
        ->get('/progres-mahasiswa?per_page=10&page=2')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rows', 2)
            ->where('pagination.currentPage', 2)
            ->where('pagination.lastPage', 2)
            ->where('pagination.total', 12)
            ->where('pagination.perPage', 10));
});

it('counts tahap pills over the full filtered set while filtering by phase', function (): void {
    progressPageProject(['phase' => 'research']);
    progressPageProject(['phase' => 'research']);
    progressPageProject(['phase' => 'sempro']);

    $viewer = User::factory()->asAdmin()->create();

    $this->actingAs($viewer)
        ->get('/progres-mahasiswa?phase=research')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rows', 2)
            ->where('phases.0.value', 'all')
            ->where('phases.0.count', 3)
            ->where('phases.3.value', 'research')
            ->where('phases.3.count', 2)
            ->where('phases.2.value', 'sempro')
            ->where('phases.2.count', 1));
});

it('filters by program studi in SQL', function (): void {
    $kept = ProgramStudi::factory()->create();
    progressPageProject(['prodi' => $kept, 'name' => 'Mahasiswa Prodi Ini']);
    progressPageProject(['name' => 'Mahasiswa Prodi Lain']);

    $viewer = User::factory()->asAdmin()->create();

    $this->actingAs($viewer)
        ->get('/progres-mahasiswa?prodi='.$kept->id)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rows', 1)
            ->where('rows.0.name', 'Mahasiswa Prodi Ini'));
});

it('filters by angkatan in SQL', function (): void {
    progressPageProject(['angkatan' => 2021, 'name' => 'Angkatan Dua Satu']);
    progressPageProject(['angkatan' => 2023, 'name' => 'Angkatan Dua Tiga']);

    $viewer = User::factory()->asAdmin()->create();

    $this->actingAs($viewer)
        ->get('/progres-mahasiswa?angkatan=2023')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rows', 1)
            ->where('rows.0.name', 'Angkatan Dua Tiga')
            ->where('rows.0.angkatan', 2023));
});

it('lists only scheduled sempro or sidang defenses that are still upcoming', function (): void {
    $upcoming = progressPageProject(['name' => 'Agenda Mendatang']);
    progressPageDefense($upcoming, ['type' => 'sempro']);

    $sidang = progressPageProject(['name' => 'Agenda Sidang', 'phase' => 'sidang']);
    progressPageDefense($sidang, ['type' => 'sidang', 'scheduled_for' => now()->addDays(10)->setTime(9, 0)]);

    $past = progressPageProject(['name' => 'Agenda Lampau']);
    progressPageDefense($past, ['scheduled_for' => now()->subDays(3)->setTime(13, 0)]);

    $completed = progressPageProject(['name' => 'Agenda Selesai']);
    progressPageDefense($completed, ['status' => 'completed']);

    $viewer = User::factory()->asAdmin()->create();

    $this->actingAs($viewer)
        ->get('/progres-mahasiswa')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('agenda', 2)
            ->where('agenda.0.name', 'Agenda Mendatang')
            ->where('agenda.0.type', 'Sempro')
            ->where('agenda.1.name', 'Agenda Sidang')
            ->where('agenda.1.type', 'Sidang'));
});

it('leaks neither a non-public title nor lecturer notes', function (): void {
    $project = progressPageProject(['name' => 'Mahasiswa Rahasia']);
    progressPageApprovedTitle($project, 'Judul Rahasia Belum Publik');
    ThesisProjectNote::factory()->create([
        'project_id' => $project->id,
        'body' => 'Catatan Pembimbing Rahasia',
    ]);

    $viewer = User::factory()->asAdmin()->create();

    $response = $this->actingAs($viewer)->get('/progres-mahasiswa');

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('rows', 1)
        ->where('rows.0.title', null));

    expect($response->getContent())
        ->not->toContain('Judul Rahasia Belum Publik')
        ->not->toContain('Catatan Pembimbing Rahasia');
});

it('excludes inactive students and projects that are not active', function (): void {
    progressPageProject(['name' => 'Mahasiswa Aktif']);
    progressPageProject(['name' => 'Mahasiswa Nonaktif Profil', 'active' => false]);
    progressPageProject(['name' => 'Mahasiswa Selesai', 'state' => 'completed']);

    $viewer = User::factory()->asAdmin()->create();

    $this->actingAs($viewer)
        ->get('/progres-mahasiswa')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rows', 1)
            ->where('rows.0.name', 'Mahasiswa Aktif')
            ->where('pagination.total', 1));
});

it('shows the approved public title when the sempro rule is met', function (): void {
    $project = progressPageProject(['name' => 'Mahasiswa Publik']);
    progressPageApprovedTitle($project, 'Judul Publik Terbuka');
    progressPageDefense($project, [
        'status' => 'completed',
        'result' => 'pass',
        'scheduled_for' => now()->subDays(20)->setTime(13, 0),
    ]);

    $viewer = User::factory()->asAdmin()->create();

    $this->actingAs($viewer)
        ->get('/progres-mahasiswa')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.0.title', 'Judul Publik Terbuka'));
});
