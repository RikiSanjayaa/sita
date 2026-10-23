<?php

use App\Enums\AppRole;
use App\Filament\Resources\ThesisProjects\Pages\ListThesisProjects;
use App\Models\AdminProfile;
use App\Models\DosenProfile;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\ThesisProjectEvent;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Notifications\RealtimeNotification;
use App\Services\ThesisProjectImportService;
use App\Support\SimpleXlsxWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

const THESIS_IMPORT_HEADER = ['nim', 'judul', 'pembimbing_1_nik', 'pembimbing_2_nik', 'tahap', 'tanggal_mulai_tiket'];

/**
 * @param  array<int, array<int, string>>  $rows
 */
function thesisImportWorkbook(array $rows, array $header = THESIS_IMPORT_HEADER): UploadedFile
{
    $binary = (new SimpleXlsxWriter)->build([
        ['name' => 'Template Import', 'rows' => [$header, ...$rows], 'headerRows' => [1]],
    ]);
    $path = tempnam(sys_get_temp_dir(), 'thesis-import-test-');
    file_put_contents($path, $binary);

    return new UploadedFile($path, 'thesis-import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

function thesisImportStudent(ProgramStudi $prodi, string $nim, string $degreeLevel = 's1'): User
{
    $student = User::factory()->asMahasiswa()->create(['name' => 'Mahasiswa '.$nim]);
    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'nim' => $nim,
        'program_studi_id' => $prodi->id,
        'degree_level' => $degreeLevel,
    ]);

    return $student;
}

function thesisImportLecturer(ProgramStudi $prodi, string $nik, int $quota = 14): User
{
    $lecturer = createUserWithRole(AppRole::Dosen->value);
    $lecturer->dosenProfile()->delete();
    DosenProfile::factory()->create([
        'user_id' => $lecturer->id,
        'nik' => $nik,
        'program_studi_id' => $prodi->id,
        'supervision_quota' => $quota,
    ]);

    return $lecturer;
}

function thesisImportActor(?ProgramStudi $restrictedTo = null): User
{
    $actor = User::factory()->asAdmin()->create();
    $actor->roles()->syncWithoutDetaching([Role::query()->firstOrCreate(['name' => AppRole::Admin->value])->id]);

    if ($restrictedTo !== null) {
        AdminProfile::query()->create(['user_id' => $actor->id, 'program_studi_id' => $restrictedTo->id]);
    }

    return $actor;
}

beforeEach(function (): void {
    $this->prodi = ProgramStudi::factory()->create();
    $this->actor = thesisImportActor();
    $this->primary = thesisImportLecturer($this->prodi, '7301000000000001');
    $this->secondary = thesisImportLecturer($this->prodi, '7301000000000002');
});

dataset('thesis import stages', [
    'label Review Judul' => ['Review Judul', 'title_review', 'active', 'submitted', []],
    'label Tahap Proposal' => ['Tahap Proposal', 'sempro', 'active', 'approved', []],
    'label Riset' => ['Riset', 'research', 'active', 'approved', ['sempro']],
    'label Tahap Sidang' => ['Tahap Sidang', 'sidang', 'active', 'approved', ['sempro']],
    'label Selesai' => ['Selesai', 'completed', 'completed', 'approved', ['sempro', 'sidang']],
    'kode title_review' => ['title_review', 'title_review', 'active', 'submitted', []],
    'kode sempro' => ['sempro', 'sempro', 'active', 'approved', []],
    'kode research' => ['research', 'research', 'active', 'approved', ['sempro']],
    'kode sidang' => ['sidang', 'sidang', 'active', 'approved', ['sempro']],
    'kode completed' => ['completed', 'completed', 'completed', 'approved', ['sempro', 'sidang']],
    'label huruf besar' => ['  RISET ', 'research', 'active', 'approved', ['sempro']],
]);

it('maps the stage to project, title, supervisor and defense structure', function (
    string $stageInput,
    string $phase,
    string $state,
    string $titleStatus,
    array $defenseTypes,
): void {
    $student = thesisImportStudent($this->prodi, '2210510001');

    $result = app(ThesisProjectImportService::class)->import(
        thesisImportWorkbook([['2210510001', 'Sistem Rekomendasi Buku', '7301000000000001', '7301000000000002', $stageInput, '']]),
        [],
        $this->actor,
    );

    expect($result)->toMatchArray(['processed' => 1, 'imported' => 1, 'failed' => 0, 'failures' => []]);

    $project = ThesisProject::query()->where('student_user_id', $student->id)->sole();

    expect($project->phase)->toBe($phase)
        ->and($project->state)->toBe($state)
        ->and($project->program_studi_id)->toBe($this->prodi->id)
        ->and($project->titles)->toHaveCount(1)
        ->and($project->titles->first()->version_no)->toBe(1)
        ->and($project->titles->first()->title_id)->toBe('Sistem Rekomendasi Buku')
        ->and($project->titles->first()->status)->toBe($titleStatus)
        ->and($project->defenses()->pluck('type')->sort()->values()->all())->toBe($defenseTypes);

    foreach ($project->defenses as $defense) {
        expect($defense->status)->toBe('completed')
            ->and($defense->result)->toBe('pass')
            ->and($defense->notes)->toBe(ThesisProjectImportService::IMPORT_NOTE)
            ->and($defense->title_version_id)->toBe($project->titles->first()->id);
    }

    $assignments = ThesisSupervisorAssignment::query()->where('project_id', $project->id)->get();

    expect($assignments)->toHaveCount(2)
        ->and($assignments->pluck('lecturer_user_id')->sort()->values()->all())
        ->toBe(collect([$this->primary->id, $this->secondary->id])->sort()->values()->all())
        ->and($assignments->pluck('status')->unique()->all())->toBe([$phase === 'completed' ? 'ended' : 'active']);

    expect(ThesisProjectEvent::query()->where('project_id', $project->id)->where('event_type', 'project_imported')->exists())->toBeTrue();
})->with('thesis import stages');

it('imports a Review Judul row without supervisors and without a ticket', function (): void {
    $student = thesisImportStudent($this->prodi, '2210510002');

    $result = app(ThesisProjectImportService::class)->import(
        thesisImportWorkbook([['2210510002', 'Judul Baru', '', '', 'Review Judul', '']]),
        [],
        $this->actor,
    );

    $project = ThesisProject::query()->where('student_user_id', $student->id)->sole();

    expect($result['imported'])->toBe(1)
        ->and($project->phase)->toBe('title_review')
        ->and($project->activeSupervisorAssignments)->toHaveCount(0)
        ->and($project->mentorship_valid_from)->toBeNull()
        ->and($project->mentorship_valid_until)->toBeNull();
});

it('uses the project of a completed import without leaving a supervisor ticket behind', function (): void {
    $student = thesisImportStudent($this->prodi, '2210510003');

    app(ThesisProjectImportService::class)->import(
        thesisImportWorkbook([['2210510003', 'Skripsi Lama', '7301000000000001', '', 'Selesai', '']]),
        [],
        $this->actor,
    );

    $project = ThesisProject::query()->where('student_user_id', $student->id)->sole();

    expect($project->state)->toBe('completed')
        ->and($project->completed_at)->not->toBeNull()
        ->and($project->closed_by)->toBe($this->actor->id)
        ->and($project->mentorship_valid_until)->toBeNull()
        ->and($project->isMentorshipSuspended())->toBeFalse();
});

it('fills the mentorship ticket from tanggal_mulai_tiket in ISO and Excel serial form', function (string $input, string $from, string $until): void {
    $student = thesisImportStudent($this->prodi, '2210510004');
    config(['sita.thesis_ticket_months' => 12]);

    app(ThesisProjectImportService::class)->import(
        thesisImportWorkbook([['2210510004', 'Judul Tiket', '7301000000000001', '', 'Riset', $input]]),
        [],
        $this->actor,
    );

    $project = ThesisProject::query()->where('student_user_id', $student->id)->sole();

    expect($project->mentorship_valid_from->toDateString())->toBe($from)
        ->and($project->mentorship_valid_until->toDateString())->toBe($until);
})->with([
    'iso date' => ['2025-03-31', '2025-03-31', '2026-03-31'],
    'excel serial' => ['45748', '2025-04-01', '2026-04-01'],
]);

it('starts the ticket today when no tanggal_mulai_tiket is given for an active stage', function (): void {
    $student = thesisImportStudent($this->prodi, '2210510005');

    app(ThesisProjectImportService::class)->import(
        thesisImportWorkbook([['2210510005', 'Judul Tiket Hari Ini', '7301000000000001', '', 'Riset', '']]),
        [],
        $this->actor,
    );

    $project = ThesisProject::query()->where('student_user_id', $student->id)->sole();

    expect($project->mentorship_valid_from)->not->toBeNull()
        ->and($project->mentorship_valid_until)->not->toBeNull();
});

it('keeps the ticket of a lapsed start date so the project is suspended', function (): void {
    $student = thesisImportStudent($this->prodi, '2210510006');

    app(ThesisProjectImportService::class)->import(
        thesisImportWorkbook([['2210510006', 'Judul Lampau', '7301000000000001', '', 'Riset', '2020-01-15']]),
        [],
        $this->actor,
    );

    expect(ThesisProject::query()->where('student_user_id', $student->id)->sole()->isMentorshipSuspended())->toBeTrue();
});

dataset('thesis import errors', [
    'nim kosong' => [fn() => ['', 'Judul', '7301000000000001', '', 'Riset', ''], 'NIM wajib diisi.'],
    'nim tidak ada' => [fn() => ['9999999999', 'Judul', '7301000000000001', '', 'Riset', ''], 'NIM 9999999999 tidak ditemukan.'],
    'judul kosong' => [fn() => ['2210510010', '', '7301000000000001', '', 'Riset', ''], 'Judul wajib diisi.'],
    'nik tidak ditemukan' => [fn() => ['2210510010', 'Judul', '1111111111111111', '', 'Riset', ''], 'Pembimbing 1: NIK 1111111111111111 tidak ditemukan.'],
    'nik pembimbing 2 tidak ditemukan' => [fn() => ['2210510010', 'Judul', '7301000000000001', '2222222222222222', 'Riset', ''], 'Pembimbing 2: NIK 2222222222222222 tidak ditemukan.'],
    'dosen beda prodi' => [fn() => ['2210510010', 'Judul', '7301000000009999', '', 'Riset', ''], 'tidak mengajar di program studi mahasiswa'],
    'tahap tidak dikenal' => [fn() => ['2210510010', 'Judul', '7301000000000001', '', 'Magang', ''], "Tahap 'Magang' tidak dikenal."],
    'tahap kosong' => [fn() => ['2210510010', 'Judul', '7301000000000001', '', '', ''], 'Tahap wajib diisi.'],
    'tanggal salah format' => [fn() => ['2210510010', 'Judul', '7301000000000001', '', 'Riset', '01/02/2025'], "Tanggal mulai tiket '01/02/2025' tidak valid."],
    'tanggal tidak ada di kalender' => [fn() => ['2210510010', 'Judul', '7301000000000001', '', 'Riset', '2025-02-30'], "Tanggal mulai tiket '2025-02-30' tidak valid."],
    'pembimbing 1 wajib' => [fn() => ['2210510010', 'Judul', '', '', 'Riset', ''], 'Pembimbing 1 wajib diisi kecuali pada tahap Review Judul.'],
    'pembimbing 2 tanpa 1' => [fn() => ['2210510010', 'Judul', '', '7301000000000002', 'Review Judul', ''], 'Pembimbing 2 tidak boleh diisi tanpa Pembimbing 1.'],
    'pembimbing sama' => [fn() => ['2210510010', 'Judul', '7301000000000001', '7301000000000001', 'Riset', ''], 'Pembimbing 1 dan Pembimbing 2 harus berbeda.'],
]);

it('rejects invalid rows with a per-line message and saves nothing', function (Closure $row, string $message): void {
    $otherProdi = ProgramStudi::factory()->create();
    thesisImportLecturer($otherProdi, '7301000000009999');
    thesisImportStudent($this->prodi, '2210510010');
    thesisImportStudent($this->prodi, '2210510011');

    $result = app(ThesisProjectImportService::class)->import(
        thesisImportWorkbook([
            ['2210510011', 'Baris Valid', '7301000000000001', '', 'Riset', ''],
            $row(),
        ]),
        [],
        $this->actor,
    );

    expect($result['processed'])->toBe(2)
        ->and($result['imported'])->toBe(0)
        ->and($result['failed'])->toBe(1)
        ->and($result['failures'][0])->toStartWith('Baris 3: ')
        ->and($result['failures'][0])->toContain($message)
        ->and(ThesisProject::query()->count())->toBe(0)
        ->and(ThesisSupervisorAssignment::query()->count())->toBe(0);
})->with('thesis import errors');

it('rejects a student who already has an active project', function (): void {
    $student = thesisImportStudent($this->prodi, '2210510020');
    ThesisProject::factory()->create(['student_user_id' => $student->id, 'program_studi_id' => $this->prodi->id, 'state' => 'active', 'phase' => 'research']);

    $result = app(ThesisProjectImportService::class)->import(
        thesisImportWorkbook([['2210510020', 'Judul Lain', '7301000000000001', '', 'Riset', '']]),
        [],
        $this->actor,
    );

    expect($result['imported'])->toBe(0)
        ->and($result['failures'][0])->toBe('Baris 2: Mahasiswa sudah memiliki Skripsi aktif.')
        ->and(ThesisProject::query()->count())->toBe(1);
});

it('allows a student whose current project is completed or cancelled', function (string $state): void {
    $student = thesisImportStudent($this->prodi, '2210510021');
    ThesisProject::factory()->create(['student_user_id' => $student->id, 'program_studi_id' => $this->prodi->id, 'state' => $state, 'phase' => 'completed']);

    $result = app(ThesisProjectImportService::class)->import(
        thesisImportWorkbook([['2210510021', 'Judul Baru Setelah Itu', '7301000000000001', '', 'Riset', '']]),
        [],
        $this->actor,
    );

    expect($result['imported'])->toBe(1)
        ->and(ThesisProject::query()->current()->where('student_user_id', $student->id)->sole()->phase)->toBe('research');
})->with(['completed', 'cancelled']);

it('rejects re-importing the same file without creating duplicates', function (string $stage): void {
    thesisImportStudent($this->prodi, '2210510030');
    $rows = [['2210510030', 'Judul Yang Sama', '7301000000000001', '', $stage, '']];
    $service = app(ThesisProjectImportService::class);

    expect($service->import(thesisImportWorkbook($rows), [], $this->actor)['imported'])->toBe(1);

    $second = $service->import(thesisImportWorkbook($rows), [], $this->actor);

    expect($second['imported'])->toBe(0)
        ->and($second['failed'])->toBe(1)
        ->and($second['failures'][0])->toStartWith('Baris 2: Mahasiswa sudah memiliki Skripsi')
        ->and(ThesisProject::query()->count())->toBe(1)
        ->and(ThesisDefense::query()->count())->toBe($stage === 'Selesai' ? 2 : 1);
})->with(['Riset', 'Selesai']);

it('rejects a NIM that appears twice in the file', function (): void {
    thesisImportStudent($this->prodi, '2210510031');

    $result = app(ThesisProjectImportService::class)->import(
        thesisImportWorkbook([
            ['2210510031', 'Judul Satu', '7301000000000001', '', 'Riset', ''],
            ['2210510031', 'Judul Dua', '7301000000000001', '', 'Riset', ''],
        ]),
        [],
        $this->actor,
    );

    expect($result['imported'])->toBe(0)
        ->and($result['failures'])->toHaveCount(1)
        ->and($result['failures'][0])->toContain('Baris 3: NIM 2210510031 muncul lebih dari sekali')
        ->and(ThesisProject::query()->count())->toBe(0);
});

it('checks the supervision quota across the whole file but not for completed rows', function (): void {
    $full = thesisImportLecturer($this->prodi, '7301000000000003', quota: 1);
    thesisImportStudent($this->prodi, '2210510040');
    thesisImportStudent($this->prodi, '2210510041');
    thesisImportStudent($this->prodi, '2210510042');
    $service = app(ThesisProjectImportService::class);

    $rejected = $service->import(
        thesisImportWorkbook([
            ['2210510040', 'Judul A', '7301000000000003', '', 'Riset', ''],
            ['2210510041', 'Judul B', '7301000000000003', '', 'Riset', ''],
        ]),
        [],
        $this->actor,
    );

    expect($rejected['imported'])->toBe(0)
        ->and($rejected['failures'])->toHaveCount(1)
        ->and($rejected['failures'][0])->toContain('Baris 3: ')
        ->and($rejected['failures'][0])->toContain('kuota bimbingan')
        ->and(ThesisProject::query()->count())->toBe(0);

    $accepted = $service->import(
        thesisImportWorkbook([
            ['2210510040', 'Judul A', '7301000000000003', '', 'Riset', ''],
            ['2210510041', 'Judul B', '7301000000000003', '', 'Selesai', ''],
            ['2210510042', 'Judul C', '7301000000000003', '', 'Selesai', ''],
        ]),
        [],
        $this->actor,
    );

    expect($accepted['imported'])->toBe(3)
        ->and($full->fresh()->dosenProfile->supervision_quota)->toBe(1);
});

it('rejects students outside the program studi of a restricted admin', function (): void {
    $otherProdi = ProgramStudi::factory()->create();
    thesisImportStudent($otherProdi, '2210510050');
    $restricted = thesisImportActor($this->prodi);

    $result = app(ThesisProjectImportService::class)->import(
        thesisImportWorkbook([['2210510050', 'Judul Prodi Lain', '', '', 'Review Judul', '']]),
        [],
        $restricted,
    );

    expect($result['imported'])->toBe(0)
        ->and($result['failures'][0])->toContain('bukan mahasiswa program studi Anda')
        ->and(ThesisProject::query()->count())->toBe(0);
});

it('rejects a workbook without the required columns or data rows', function (array $header, array $rows): void {
    expect(fn() => app(ThesisProjectImportService::class)->import(thesisImportWorkbook($rows, $header), [], $this->actor))
        ->toThrow(ValidationException::class);
})->with([
    'missing tahap column' => [['nim', 'judul'], [['1', 'x']]],
    'header only' => [THESIS_IMPORT_HEADER, []],
]);

it('sends no notification by default and notifies student and supervisors when asked', function (): void {
    Notification::fake();
    $student = thesisImportStudent($this->prodi, '2210510060');
    $service = app(ThesisProjectImportService::class);

    $service->import(
        thesisImportWorkbook([['2210510060', 'Judul Notifikasi', '7301000000000001', '7301000000000002', 'Riset', '']]),
        [],
        $this->actor,
    );

    Notification::assertNothingSent();

    ThesisProject::query()->delete();
    thesisImportStudent($this->prodi, '2210510061');

    $service->import(
        thesisImportWorkbook([['2210510061', 'Judul Notifikasi Dua', '7301000000000001', '7301000000000002', 'Riset', '']]),
        ['notify' => true],
        $this->actor,
    );

    Notification::assertSentTo(User::query()->whereHas('mahasiswaProfile', fn($q) => $q->where('nim', '2210510061'))->first(), RealtimeNotification::class);
    Notification::assertSentTo($this->primary, RealtimeNotification::class);
    Notification::assertSentTo($this->secondary, RealtimeNotification::class);
    Notification::assertNotSentTo($student, RealtimeNotification::class);
    Notification::assertNotSentTo($this->actor, RealtimeNotification::class);
});

it('imports from the Filament list page and reports a rejected file', function (): void {
    thesisImportStudent($this->prodi, '2210510070');
    $this->actingAs($this->actor);

    $valid = thesisImportWorkbook([['2210510070', 'Judul Dari Halaman', '7301000000000001', '', 'Tahap Sidang', '']]);
    Livewire::test(ListThesisProjects::class)
        ->callAction('importThesisProjects', [
            'file' => UploadedFile::fake()->createWithContent('thesis-import.xlsx', file_get_contents($valid->getRealPath())),
            'notify' => false,
        ])
        ->assertNotified('Import Excel berhasil');

    expect(ThesisProject::query()->where('phase', 'sidang')->count())->toBe(1);

    $invalid = thesisImportWorkbook([['0000', 'Tidak Ada', '', '', 'Review Judul', '']]);
    Livewire::test(ListThesisProjects::class)
        ->callAction('importThesisProjects', [
            'file' => UploadedFile::fake()->createWithContent('thesis-import.xlsx', file_get_contents($invalid->getRealPath())),
        ])
        ->assertNotified('Import Excel dibatalkan, tidak ada data yang disimpan');

    expect(ThesisProject::query()->count())->toBe(1);
});
