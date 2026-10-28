<?php

use App\Enums\AppRole;
use App\Models\DosenProgramStudiAssignment;
use App\Models\ExpertiseField;
use App\Models\ProgramStudi;
use App\Models\User;
use App\Services\UserExcelImportService;
use App\Support\SimpleXlsxReader;
use App\Support\SimpleXlsxWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

function roleImportFile(string $contents): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'role-import-');
    file_put_contents($path, $contents);

    return new UploadedFile($path, 'users.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

beforeEach(function (): void {
    $this->actor = User::factory()->asSuperAdmin()->create();
    $this->actingAs($this->actor);
    $this->prodi = ProgramStudi::factory()->create([
        'name' => 'Ilmu Komputer', 'slug' => 'ilkom',
        'degree_levels' => ['d3', 's1', 's2'],
        'concentrations' => ['Jaringan', 'Sistem Cerdas'],
    ]);
    $this->otherProdi = ProgramStudi::factory()->create([
        'name' => 'Teknologi Informasi', 'slug' => 'ti', 'concentrations' => ['Umum'],
    ]);
    ProgramStudi::factory()->create([
        'name' => 'Ilmu Komputer', 'slug' => 's2-ilmu-komputer',
        'degree_levels' => ['s2'], 'concentrations' => ['Umum'],
    ]);
    ExpertiseField::factory()->create(['name' => 'Jaringan Komputer', 'slug' => 'jaringan-komputer']);
    ExpertiseField::factory()->create(['name' => 'Keamanan Siber', 'slug' => 'keamanan-siber']);
});

it('round trips the downloaded role workbook to its correct profile', function (string $kind): void {
    $response = $this->get(route('admin.users.import-template', ['kind' => $kind]))->assertOk();
    $file = roleImportFile($response->getContent());
    try {
        $result = app(UserExcelImportService::class)->import($file, [
            // The row role wins over the fallback, including mixed imports.
            'import_type' => $kind === 'mahasiswa' ? 'dosen' : 'mahasiswa',
            'program_studi_id' => $this->prodi->id,
        ], $this->actor);
    } finally {
        unlink($file->getRealPath());
    }
    expect($result)->toBe(['processed' => 1, 'imported' => 1, 'failed' => 0, 'failures' => []]);
    $user = User::query()->where('email', "contoh-{$kind}@sita.test")->firstOrFail();
    expect($user->hasRole($kind))->toBeTrue()
        ->and(Hash::check('Rahasia123!', $user->password))->toBeTrue()
        ->and($user->phone_number)->toBe('081234567890');

    if ($kind === 'mahasiswa') {
        expect($user->mahasiswaProfile->program_studi_id)->toBe($this->prodi->id)
            ->and($user->mahasiswaProfile->nim)->toBe('2210519901')
            ->and($user->mahasiswaProfile->angkatan)->toBe(2022)
            ->and($user->mahasiswaProfile->degree_level)->toBe('s1')
            ->and($user->mahasiswaProfile->concentration)->toBe('Jaringan')
            ->and($user->mahasiswaProfile->is_obe_curriculum)->toBeTrue()
            ->and($user->dosenProfile)->toBeNull();
    } elseif ($kind === 'dosen') {
        expect($user->dosenProfile->nik)->toBe('7301010101990001')
            ->and($user->dosenProfile->supervision_quota)->toBe(12)
            ->and($user->dosenProfile->program_studi_id)->toBe($this->prodi->id)
            ->and($user->teachesInProgramStudi($this->prodi->id, 'Jaringan'))->toBeTrue()
            ->and($user->teachesInProgramStudi($this->otherProdi->id, 'Umum'))->toBeTrue()
            ->and(DosenProgramStudiAssignment::query()->where('user_id', $user->id)->where('is_primary', true)->sole()->program_studi_id)->toBe($this->prodi->id)
            ->and($user->expertiseFields()->pluck('name')->all())->toEqualCanonicalizing(['Jaringan Komputer', 'Keamanan Siber'])
            ->and($user->mahasiswaProfile)->toBeNull();
    } else {
        expect($user->adminProfile->program_studi_id)->toBe($this->prodi->id)
            ->and($user->mahasiswaProfile)->toBeNull()
            ->and($user->dosenProfile)->toBeNull();
    }
})->with(['mahasiswa', 'dosen', 'admin']);

it('still accepts valid legacy mixed-role workbooks', function (): void {
    $rows = [
        ['nama', 'email', 'role', 'password', 'nim', 'angkatan', 'jenjang', 'konsentrasi', 'nik', 'penempatan_dosen', 'kurikulum_obe'],
        ['Mixed Student', 'mixed-student@sita.test', 'mahasiswa', 'Rahasia123!', '2210519902', '2023', 'd3', 'Jaringan', '', '', 'ya'],
        ['Mixed Lecturer', 'mixed-lecturer@sita.test', 'dosen', 'Rahasia123!', '', '', '', 'Umum', '0012345', 'Teknologi Informasi:Umum', ''],
        ['Mixed Admin', 'mixed-admin@sita.test', 'admin', 'Rahasia123!', '', '', '', '', '', '', ''],
    ];
    $file = roleImportFile((new SimpleXlsxWriter)->build([['name' => 'Users', 'rows' => $rows, 'headerRows' => [1]]]));
    try {
        $result = app(UserExcelImportService::class)->import($file, ['import_type' => 'mahasiswa', 'program_studi_id' => $this->prodi->id], $this->actor);
    } finally {
        unlink($file->getRealPath());
    }
    expect($result)->toBe(['processed' => 3, 'imported' => 3, 'failed' => 0, 'failures' => []]);
    $student = User::query()->where('email', 'mixed-student@sita.test')->sole();
    $lecturer = User::query()->where('email', 'mixed-lecturer@sita.test')->sole();
    $admin = User::query()->where('email', 'mixed-admin@sita.test')->sole();
    expect($student->hasRole(AppRole::Mahasiswa))->toBeTrue()
        ->and($student->mahasiswaProfile->degree_level)->toBe('d3')
        ->and($student->mahasiswaProfile->is_obe_curriculum)->toBeTrue()
        ->and($lecturer->hasRole(AppRole::Dosen))->toBeTrue()
        ->and($lecturer->dosenProfile->program_studi_id)->toBe($this->otherProdi->id)
        ->and($lecturer->dosenProfile->nik)->toBe('0012345')
        ->and($admin->hasRole(AppRole::Admin))->toBeTrue()
        ->and($admin->adminProfile->program_studi_id)->toBe($this->prodi->id);
});

it('imports each available student degree and rejects a degree outside the selected prodi', function (string $degree, bool $available): void {
    $this->prodi->update(['degree_levels' => $available ? [$degree] : ['s1']]);
    $response = $this->get(route('admin.users.import-template', ['kind' => 'mahasiswa']))->assertOk();
    $download = roleImportFile($response->getContent());
    try {
        $rows = app(SimpleXlsxReader::class)->rows($download->getRealPath());
    } finally {
        unlink($download->getRealPath());
    }
    $rows[1][array_search('jenjang', $rows[0], true)] = $degree;
    $file = roleImportFile((new SimpleXlsxWriter)->build([['name' => 'Users', 'rows' => $rows, 'headerRows' => [1]]]));
    try {
        $result = app(UserExcelImportService::class)->import($file, ['import_type' => 'mahasiswa', 'program_studi_id' => $this->prodi->id], $this->actor);
    } finally {
        unlink($file->getRealPath());
    }
    if ($available) {
        $profile = User::query()->where('email', 'contoh-mahasiswa@sita.test')->sole()->mahasiswaProfile;
        expect($result['failed'])->toBe(0)
            ->and($profile->degree_level)->toBe($degree)
            ->and($profile->program_studi_id)->toBe($this->prodi->id)
            ->and($profile->is_obe_curriculum)->toBeTrue();
    } else {
        expect($result['imported'])->toBe(0)
            ->and($result['failures'][0])->toContain('Jenjang wajib dipilih')
            ->and(User::query()->where('email', 'contoh-mahasiswa@sita.test')->exists())->toBeFalse();
    }
})->with([['d3', true], ['s1', true], ['s2', true], ['s2', false]]);

it('validates edited role template rows without persisting invalid accounts', function (string $kind, array $changes, string $message, bool $ordinaryAdmin): void {
    $response = $this->get(route('admin.users.import-template', ['kind' => $kind]))->assertOk();
    $download = roleImportFile($response->getContent());
    try {
        $rows = app(SimpleXlsxReader::class)->rows($download->getRealPath());
    } finally {
        unlink($download->getRealPath());
    }
    $row = array_replace(array_combine($rows[0], $rows[1]), $changes);
    $file = roleImportFile((new SimpleXlsxWriter)->build([['name' => 'Users', 'rows' => [$rows[0], array_values($row)], 'headerRows' => [1]]]));
    if ($ordinaryAdmin) {
        $this->actor = User::factory()->asAdmin()->create();
        $this->actingAs($this->actor);
    }
    try {
        $result = app(UserExcelImportService::class)->import($file, ['import_type' => $kind, 'program_studi_id' => $this->prodi->id], $this->actor);
    } finally {
        unlink($file->getRealPath());
    }
    expect($result['imported'])->toBe(0)
        ->and($result['failed'])->toBe(1)
        ->and($result['failures'][0])->toContain('Baris 2:', $message)
        ->and(User::query()->where('email', $row['email'])->exists())->toBeFalse();
})->with([
    'student NIM' => ['mahasiswa', ['nim' => ''], 'NIM wajib', false],
    'student degree required for multi-degree prodi' => ['mahasiswa', ['jenjang' => ''], 'Jenjang wajib dipilih', false],
    'student unsupported degree' => ['mahasiswa', ['jenjang' => 's3'], 'degree level', false],
    'student unavailable concentration' => ['mahasiswa', ['konsentrasi' => 'Tidak tersedia'], 'Konsentrasi tidak tersedia', false],
    'student invalid OBE' => ['mahasiswa', ['kurikulum_obe' => 'mungkin'], 'kurikulum_obe', false],
    'lecturer NIK' => ['dosen', ['nik' => ''], 'NIK wajib', false],
    'lecturer unknown placement' => ['dosen', ['penempatan_dosen' => 'Tidak Ada:Jaringan'], 'tidak ditemukan', false],
    'lecturer invalid placement concentration' => ['dosen', ['penempatan_dosen' => 'Ilmu Komputer:Tidak Ada'], 'Konsentrasi tidak tersedia', false],
    'admin password' => ['admin', ['password' => 'short'], 'password', false],
    'admin role forbidden for ordinary admin' => ['admin', [], 'Hanya super admin', true],
]);
