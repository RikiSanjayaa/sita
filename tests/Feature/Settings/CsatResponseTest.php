<?php

use App\Models\CsatResponse;
use App\Models\DosenProfile;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

test('mahasiswa can view csat page and submit feedback', function (): void {
    $programStudi = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);
    $student = User::factory()->asMahasiswa()->create(['name' => 'Mahasiswa CSAT']);

    MahasiswaProfile::query()->create([
        'user_id' => $student->id,
        'nim' => '2210510801',
        'program_studi_id' => $programStudi->id,
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    $this->actingAs($student)
        ->get(route('settings.csat.show'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('settings/csat')
            ->where('csat.respondentRole', 'mahasiswa')
            ->where('csat.programStudi', 'Ilmu Komputer')
            ->where('csat.canSubmit', true));

    $this->actingAs($student)
        ->post(route('settings.csat.store'), [
            'score' => 4,
            'kritik' => 'Status sidang belum cukup jelas.',
            'saran' => 'Tambahkan penjelasan status pada dashboard.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('settings.csat.show'));

    $response = CsatResponse::query()->first();

    expect($response)->not->toBeNull()
        ->and($response?->user_id)->toBe($student->id)
        ->and($response?->program_studi_id)->toBe($programStudi->id)
        ->and($response?->respondent_role)->toBe('mahasiswa')
        ->and($response?->score)->toBe(4);
});

test('dosen can submit csat feedback', function (): void {
    $programStudi = ProgramStudi::factory()->create(['name' => 'Sistem Informasi']);
    $lecturer = User::factory()->asDosen()->create(['name' => 'Dosen CSAT']);

    DosenProfile::query()->create([
        'user_id' => $lecturer->id,
        'program_studi_id' => $programStudi->id,
        'nik' => '1987654321',
        'concentration' => ProgramStudi::DEFAULT_GENERAL_CONCENTRATION,
    ]);

    $this->actingAs($lecturer)
        ->post(route('settings.csat.store'), [
            'score' => 5,
            'kritik' => '',
            'saran' => 'Workspace dosen sudah nyaman, tinggal tambah filter agenda.',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('settings.csat.show'));

    $response = CsatResponse::query()->first();

    expect($response)->not->toBeNull()
        ->and($response?->respondent_role)->toBe('dosen')
        ->and($response?->program_studi_id)->toBe($programStudi->id)
        ->and($response?->score)->toBe(5)
        ->and($response?->kritik)->toBeNull();
});

test('user must wait 30 days before submitting csat again', function (): void {
    Carbon::setTestNow('2026-03-20 09:00:00');

    try {
        $programStudi = ProgramStudi::factory()->create(['name' => 'Teknik Informatika']);
        $student = User::factory()->asMahasiswa()->create();

        MahasiswaProfile::query()->create([
            'user_id' => $student->id,
            'nim' => '2210510802',
            'program_studi_id' => $programStudi->id,
            'angkatan' => 2022,
            'is_active' => true,
        ]);

        $this->actingAs($student)
            ->post(route('settings.csat.store'), [
                'score' => 3,
                'kritik' => 'Perlu perbaikan kecil.',
                'saran' => 'Tambahkan tooltip status.',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($student)
            ->from(route('settings.csat.show'))
            ->post(route('settings.csat.store'), [
                'score' => 4,
                'kritik' => 'Percobaan kedua.',
                'saran' => 'Seharusnya ditolak cooldown.',
            ])
            ->assertSessionHasErrors('score')
            ->assertRedirect(route('settings.csat.show'));

        Carbon::setTestNow(now()->addDays(30));

        $this->actingAs($student)
            ->post(route('settings.csat.store'), [
                'score' => 4,
                'kritik' => 'Sudah lewat 30 hari.',
                'saran' => 'Sekarang boleh kirim lagi.',
            ])
            ->assertSessionHasNoErrors();

        expect(CsatResponse::query()->count())->toBe(2);
    } finally {
        Carbon::setTestNow();
    }
});

test('score must be between one and five', function (): void {
    $programStudi = ProgramStudi::factory()->create();
    $student = User::factory()->asMahasiswa()->create();

    MahasiswaProfile::query()->create([
        'user_id' => $student->id,
        'nim' => '2210510803',
        'program_studi_id' => $programStudi->id,
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    $this->actingAs($student)
        ->from(route('settings.csat.show'))
        ->post(route('settings.csat.store'), [
            'score' => 6,
            'kritik' => 'Skor invalid.',
            'saran' => 'Tidak boleh lolos.',
        ])
        ->assertSessionHasErrors('score')
        ->assertRedirect(route('settings.csat.show'));
});

test('admin cannot access csat submission page', function (): void {
    $admin = User::factory()->asAdmin()->create();

    $this->actingAs($admin)
        ->get(route('settings.csat.show'))
        ->assertForbidden();
});
