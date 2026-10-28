<?php

use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\ThesisProjectNote;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Services\KaprodiPortalService;
use Illuminate\Support\Facades\DB;
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

it('paginates beyond the first page', function (int $size, int $lastPage): void {
    foreach (range(1, 12) as $number) {
        progressPageProject(['name' => sprintf('Mahasiswa %02d', $number)]);
    }

    $viewer = User::factory()->asAdmin()->create();

    $this->actingAs($viewer)
        ->get('/progres-mahasiswa?per_page='.$size.'&page='.$lastPage)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rows', 2)
            ->where('pagination.currentPage', $lastPage)
            ->where('pagination.lastPage', $lastPage)
            ->where('pagination.total', 12)
            ->where('pagination.perPage', $size));
})->with([[5, 3], [10, 2]]);

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

/**
 * A mahasiswa profile without any thesis project.
 *
 * @param  array{name?: string, prodi?: ProgramStudi, active?: bool}  $options
 */
function progressPageStudentWithoutThesis(array $options = []): User
{
    $student = User::factory()->asMahasiswa()->create(['name' => $options['name'] ?? 'Mahasiswa Tanpa TA']);
    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => ($options['prodi'] ?? ProgramStudi::factory()->create())->id,
        'is_active' => $options['active'] ?? true,
    ]);

    return $student;
}

function progressPageStamp(string $table, int $id, mixed $at): void
{
    DB::table($table)->where('id', $id)->update(['updated_at' => $at, 'created_at' => $at]);
}

it('lists the same students as the kaprodi Mahasiswa Prodi page', function (): void {
    $prodi = ProgramStudi::factory()->create();

    $researching = progressPageProject(['prodi' => $prodi, 'name' => 'Sedang Riset']);
    progressPageStudentWithoutThesis(['prodi' => $prodi, 'name' => 'Belum Mulai']);
    progressPageStudentWithoutThesis(['prodi' => $prodi, 'name' => 'Nonaktif Tanpa TA', 'active' => false]);
    $completed = progressPageProject(['prodi' => $prodi, 'name' => 'Sudah Lulus', 'phase' => 'completed', 'state' => 'completed', 'active' => false]);
    $completedActive = progressPageProject(['prodi' => $prodi, 'name' => 'Lulus Masih Aktif', 'phase' => 'completed', 'state' => 'completed']);
    $cancelled = progressPageProject(['prodi' => $prodi, 'name' => 'Dibatalkan', 'state' => 'cancelled']);
    progressPageProject(['prodi' => $prodi, 'name' => 'Ada Riwayat']);
    // A newer project supersedes the older one: the student is listed once, with the newer one.
    $history = progressPageProject(['prodi' => $prodi, 'name' => 'Riwayat Panjang', 'phase' => 'sidang']);
    ThesisProject::query()->create([
        'student_user_id' => $history->student_user_id,
        'program_studi_id' => $prodi->id,
        'phase' => 'title_review',
        'state' => 'active',
        'started_at' => now()->subDays(2),
        'created_by' => $history->student_user_id,
    ]);
    progressPageProject(['name' => 'Prodi Lain']);

    $portal = app(KaprodiPortalService::class)->studentIndex($prodi, null);
    $expected = collect($portal['students'])
        ->filter(fn (array $row): bool => $row['status'] === 'Aktif' && ! in_array($row['projectStateKey'], ['completed', 'cancelled'], true))
        ->mapWithKeys(fn (array $row): array => [$row['id'] => $row['phaseKey']])
        ->union(collect($portal['archives'])->mapWithKeys(fn (array $row): array => [$row['studentId'] => 'completed']));

    $viewer = User::factory()->asMahasiswa()->create();

    $this->actingAs($viewer)
        ->get('/progres-mahasiswa?per_page=100&prodi='.$prodi->id)
        ->assertInertia(function (AssertableInertia $page) use ($expected, $cancelled, $completed): void {
            $rows = collect($page->toArray()['props']['rows']);

            expect($rows->pluck('id')->sort()->values()->all())->toBe($expected->keys()->sort()->values()->all())
                ->and($rows->count())->toBe(7)
                ->and($rows->mapWithKeys(fn (array $row): array => [$row['id'] => $row['phaseKey']])->sortKeys()->all())->toBe($expected->sortKeys()->all())
                ->and($rows->firstWhere('id', $cancelled->student_user_id)['phaseKey'])->toBe('none')
                ->and($rows->firstWhere('id', $completed->student_user_id)['phaseKey'])->toBe('completed')
                ->and($rows->firstWhere('name', 'Belum Mulai')['phaseLabel'])->toBe('Belum ada tugas akhir');
        });

    expect($researching->student_user_id)->not->toBeNull()->and($completedActive->state)->toBe('completed');
});

it('lists every prodi by default and counts the no-thesis and completed students in the pills', function (): void {
    progressPageProject(['name' => 'Riset Satu']);
    progressPageProject(['name' => 'Riset Dua']);
    progressPageProject(['name' => 'Lulus', 'phase' => 'completed', 'state' => 'completed']);
    progressPageStudentWithoutThesis(['name' => 'Kosong Satu']);
    progressPageStudentWithoutThesis(['name' => 'Kosong Dua']);
    progressPageStudentWithoutThesis(['name' => 'Nonaktif', 'active' => false]);

    $viewer = User::factory()->asAdmin()->create();

    $this->actingAs($viewer)
        ->get('/progres-mahasiswa')
        ->assertInertia(function (AssertableInertia $page): void {
            $pills = collect($page->toArray()['props']['phases'])->pluck('count', 'value');

            expect($pills['all'])->toBe(5)
                ->and($pills['research'])->toBe(2)
                ->and($pills['completed'])->toBe(1)
                ->and($pills['none'])->toBe(2)
                ->and($pills['cancelled'])->toBe(0);
        });
});

it('filters and counts the no-thesis phase over the full set beyond the first page', function (): void {
    foreach (range(1, 12) as $number) {
        progressPageProject(['name' => sprintf('Riset %02d', $number)]);
    }
    foreach (range(1, 7) as $number) {
        progressPageStudentWithoutThesis(['name' => sprintf('Kosong %02d', $number)]);
    }

    $viewer = User::factory()->asAdmin()->create();

    $this->actingAs($viewer)
        ->get('/progres-mahasiswa?phase=none&per_page=10')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rows', 7)
            ->where('pagination.total', 7)
            ->where('phases.0.count', 19)
            ->where('phases.7.value', 'none')
            ->where('phases.7.count', 7));

    $this->actingAs($viewer)
        ->get('/progres-mahasiswa?per_page=10&page=2')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rows', 9)
            ->where('pagination.total', 19)
            ->where('rows.2.phaseKey', 'none')
            ->where('rows.0.phaseKey', 'research'));
});

it('orders by the latest stage progress, then students without a thesis by name', function (): void {
    $old = progressPageProject(['name' => 'Lama Tanpa Kemajuan']);
    $old->forceFill(['started_at' => now()->subDays(60)])->save();

    $supervised = progressPageProject(['name' => 'Pembimbing Baru']);
    $assignment = ThesisSupervisorAssignment::query()->create([
        'project_id' => $supervised->id,
        'lecturer_user_id' => User::factory()->asDosen()->create()->id,
        'role' => 'primary',
        'status' => 'active',
        'started_at' => now()->subDays(5),
    ]);

    $sempro = progressPageProject(['name' => 'Sempro Lulus', 'phase' => 'research']);
    progressPageDefense($sempro, ['status' => 'completed', 'result' => 'pass', 'scheduled_for' => now()->subDays(3), 'decision_at' => now()->subDays(3)]);

    $sidang = progressPageProject(['name' => 'Sidang Dijadwalkan', 'phase' => 'sidang']);
    progressPageDefense($sidang, ['type' => 'sidang', 'scheduled_for' => now()->addDays(20)]);
    $sidangDefense = ThesisDefense::query()->where('project_id', $sidang->id)->first();
    progressPageStamp('thesis_defenses', $sidangDefense->id, now()->subDay());
    progressPageStamp('thesis_supervisor_assignments', $assignment->id, now()->subDays(5));

    progressPageStudentWithoutThesis(['name' => 'Zara Tanpa TA']);
    progressPageStudentWithoutThesis(['name' => 'Anna Tanpa TA']);

    $viewer = User::factory()->asAdmin()->create();

    $this->actingAs($viewer)
        ->get('/progres-mahasiswa')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.0.name', 'Sidang Dijadwalkan')
            ->where('rows.0.moment.kind', 'sidang_scheduled')
            ->where('rows.0.moment.label', 'Sidang dijadwalkan')
            ->where('rows.1.name', 'Sempro Lulus')
            ->where('rows.1.moment.kind', 'sempro_held')
            ->where('rows.2.name', 'Pembimbing Baru')
            ->where('rows.2.moment.kind', 'supervisors')
            ->where('rows.3.name', 'Lama Tanpa Kemajuan')
            ->where('rows.3.moment.kind', 'project_started')
            ->where('rows.4.name', 'Anna Tanpa TA')
            ->where('rows.4.moment', null)
            ->where('rows.5.name', 'Zara Tanpa TA'));
});

it('takes the latest moment of each kind into account', function (string $kind): void {
    $quiet = progressPageProject(['name' => 'Tidak Bergerak']);
    $quiet->forceFill(['started_at' => now()->subDays(20)])->save();
    $mover = progressPageProject(['name' => 'Bergerak']);
    $mover->forceFill(['started_at' => now()->subDays(60)])->save();
    $recent = now()->subDay();

    match ($kind) {
        'sidang_scheduled' => progressPageDefense($mover, ['type' => 'sidang', 'scheduled_for' => now()->addDays(14), 'updated_at' => $recent]),
        'sidang_held' => progressPageDefense($mover, ['type' => 'sidang', 'status' => 'completed', 'result' => 'pass', 'scheduled_for' => $recent, 'decision_at' => $recent]),
        'sempro_scheduled' => progressPageDefense($mover, ['type' => 'sempro', 'scheduled_for' => now()->addDays(14), 'updated_at' => $recent]),
        'sempro_held' => progressPageDefense($mover, ['type' => 'sempro', 'status' => 'completed', 'result' => 'pass_with_revision', 'scheduled_for' => $recent, 'decision_at' => $recent]),
        'supervisors' => ThesisSupervisorAssignment::query()->create([
            'project_id' => $mover->id,
            'lecturer_user_id' => User::factory()->asDosen()->create()->id,
            'role' => 'primary',
            'status' => 'active',
            'started_at' => $recent,
        ]),
        'title_submitted' => ThesisProjectTitle::query()->create([
            'project_id' => $mover->id,
            'version_no' => 1,
            'title_id' => 'Judul Diajukan',
            'status' => 'submitted',
            'submitted_by_user_id' => $mover->student_user_id,
            'submitted_at' => $recent,
        ]),
        'title_approved' => ThesisProjectTitle::query()->create([
            'project_id' => $mover->id,
            'version_no' => 1,
            'title_id' => 'Judul Disetujui',
            'status' => 'approved',
            'submitted_by_user_id' => $mover->student_user_id,
            'submitted_at' => now()->subDays(40),
            'decided_at' => $recent,
        ]),
    };

    $viewer = User::factory()->asAdmin()->create();

    $this->actingAs($viewer)
        ->get('/progres-mahasiswa')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.0.name', 'Bergerak')
            ->where('rows.0.moment.kind', $kind)
            ->where('rows.1.name', 'Tidak Bergerak'));
})->with(['sidang_scheduled', 'sidang_held', 'sempro_scheduled', 'sempro_held', 'supervisors', 'title_submitted', 'title_approved']);

it('sorts before paginating', function (): void {
    foreach (range(1, 11) as $number) {
        $project = progressPageProject(['name' => sprintf('Mahasiswa %02d', $number)]);
        $project->forceFill(['started_at' => now()->subDays(100 - $number)])->save();
    }

    $viewer = User::factory()->asAdmin()->create();

    // The newest project is Mahasiswa 11, so it leads page 1 and the oldest closes page 2.
    $this->actingAs($viewer)
        ->get('/progres-mahasiswa?per_page=10')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('rows.0.name', 'Mahasiswa 11'));

    $this->actingAs($viewer)
        ->get('/progres-mahasiswa?per_page=10&page=2')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rows', 1)
            ->where('rows.0.name', 'Mahasiswa 01'));
});

it('keeps the number of queries flat as the page grows', function (): void {
    $viewer = User::factory()->asAdmin()->create();

    $queries = function () use ($viewer): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($viewer)->get('/progres-mahasiswa?per_page=100')->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    foreach (range(1, 3) as $number) {
        $project = progressPageProject(['name' => "Awal {$number}"]);
        progressPageApprovedTitle($project, "Judul {$number}");
        progressPageDefense($project, ['type' => 'sidang']);
    }
    progressPageStudentWithoutThesis(['name' => 'Awal Kosong']);
    $queries();
    $few = $queries();

    foreach (range(1, 9) as $number) {
        $project = progressPageProject(['name' => "Tambah {$number}"]);
        progressPageApprovedTitle($project, "Judul Tambah {$number}");
        progressPageDefense($project, ['type' => 'sidang']);
    }
    progressPageStudentWithoutThesis(['name' => 'Tambah Kosong']);

    expect($queries())->toBe($few);
});

it('never shows a non-public title or lecturer notes to another student', function (): void {
    $project = progressPageProject(['name' => 'Mahasiswa Rahasia']);
    progressPageApprovedTitle($project, 'Judul Rahasia Belum Publik');
    ThesisProjectNote::factory()->create(['project_id' => $project->id, 'body' => 'Catatan Pembimbing Rahasia']);
    progressPageStudentWithoutThesis(['name' => 'Tanpa TA']);

    $response = $this->actingAs(User::factory()->asMahasiswa()->create())->get('/progres-mahasiswa');

    expect($response->getContent())
        ->not->toContain('Judul Rahasia Belum Publik')
        ->not->toContain('Catatan Pembimbing Rahasia');
});
