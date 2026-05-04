<?php

use App\Filament\Resources\CsatResponses\CsatResponseResource;
use App\Filament\Resources\CsatResponses\Pages\ListCsatResponses;
use App\Models\AdminProfile;
use App\Models\CsatResponse;
use App\Models\ProgramStudi;
use App\Models\User;
use Livewire\Livewire;

test('admin only sees csat responses from their own program studi', function (): void {
    $programStudiA = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);
    $programStudiB = ProgramStudi::factory()->create(['name' => 'Sistem Informasi']);

    $admin = User::factory()->asAdmin()->create();
    AdminProfile::query()->create([
        'user_id' => $admin->id,
        'program_studi_id' => $programStudiA->id,
    ]);

    $studentA = User::factory()->asMahasiswa()->create(['name' => 'Mahasiswa A']);
    $studentB = User::factory()->asMahasiswa()->create(['name' => 'Mahasiswa B']);

    $ownResponse = CsatResponse::query()->create([
        'user_id' => $studentA->id,
        'program_studi_id' => $programStudiA->id,
        'respondent_role' => 'mahasiswa',
        'score' => 4,
        'kritik' => 'Perlu perbaikan kecil.',
        'saran' => 'Tambah info status.',
    ]);

    $otherResponse = CsatResponse::query()->create([
        'user_id' => $studentB->id,
        'program_studi_id' => $programStudiB->id,
        'respondent_role' => 'mahasiswa',
        'score' => 2,
        'kritik' => 'Proses membingungkan.',
        'saran' => 'Beri panduan lebih jelas.',
    ]);

    /** @var \Tests\TestCase $this */
    $this->actingAs($admin);

    Livewire::test(ListCsatResponses::class)
        ->assertCanSeeTableRecords([$ownResponse])
        ->assertCanNotSeeTableRecords([$otherResponse]);

    $this->get(CsatResponseResource::getUrl('index'))
        ->assertOk()
        ->assertSee('CSAT & Umpan Balik')
        ->assertSee('Skor CSAT')
        ->assertSee('Jumlah Respons per Skor')
        ->assertSee('Distribusi Nilai CSAT')
        ->assertSee('Periode: Bulan ini')
        ->assertSee('Bulan lalu')
        ->assertSee('Tahun ini')
        ->assertSee('Tahun lalu');
});

test('super admin can filter csat responses by score', function (): void {
    $programStudi = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);
    $superAdmin = User::factory()->asSuperAdmin()->create();
    $studentOne = User::factory()->asMahasiswa()->create();
    $studentTwo = User::factory()->asMahasiswa()->create();

    $lowScore = CsatResponse::query()->create([
        'user_id' => $studentOne->id,
        'program_studi_id' => $programStudi->id,
        'respondent_role' => 'mahasiswa',
        'score' => 1,
        'kritik' => 'Terlalu rumit.',
        'saran' => 'Sederhanakan langkah.',
    ]);

    $highScore = CsatResponse::query()->create([
        'user_id' => $studentTwo->id,
        'program_studi_id' => $programStudi->id,
        'respondent_role' => 'dosen',
        'score' => 5,
        'kritik' => null,
        'saran' => 'Pertahankan performa.',
    ]);

    /** @var \Tests\TestCase $this */
    $this->actingAs($superAdmin);

    Livewire::test(ListCsatResponses::class)
        ->assertCanSeeTableRecords([$lowScore, $highScore])
        ->filterTable('score', '1')
        ->assertCanSeeTableRecords([$lowScore])
        ->assertCanNotSeeTableRecords([$highScore]);
});
