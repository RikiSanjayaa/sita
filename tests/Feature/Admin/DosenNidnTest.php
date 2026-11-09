<?php

use App\Enums\AppRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\DosenProfile;
use App\Models\User;
use App\Services\UserProvisioningService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('normalizes NIDN without losing leading zeroes and allows multiple empty profiles', function (): void {
    foreach ([null, '', '   ', "\t"] as $value) {
        expect(DosenProfile::factory()->create(['sidang_nidn' => $value])->fresh()->sidang_nidn)->toBeNull();
    }
    expect(DosenProfile::factory()->create(['sidang_nidn' => ' 0012345678 '])->fresh()->sidang_nidn)->toBe('0012345678');
});

it('rejects duplicate NIDN in the database including direct writers', function (): void {
    DosenProfile::factory()->create(['sidang_nidn' => '0012345678']);
    expect(fn () => DosenProfile::factory()->create(['sidang_nidn' => ' 0012345678 ']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('rejects duplicate provisioning before changing account data', function (): void {
    DosenProfile::factory()->create(['sidang_nidn' => '0012345678']);
    $user = User::factory()->asMahasiswa()->create(['phone_number' => '0811111111']);
    expect(fn () => app(UserProvisioningService::class)->syncRoleAndProfiles($user, [
        'role' => AppRole::Dosen->value, 'sidang_nidn' => ' 0012345678 ', 'phone_number' => '0822222222',
    ]))->toThrow(ValidationException::class, 'NIDN sudah digunakan dosen lain');
    expect($user->fresh()->phone_number)->toBe('0811111111')
        ->and($user->fresh()->hasRole(AppRole::Mahasiswa))->toBeTrue()
        ->and($user->dosenProfile()->exists())->toBeFalse();
});

it('shows duplicate NIDN on create without creating a partial account', function (): void {
    $profile = DosenProfile::factory()->create(['sidang_nidn' => '0012345678']);
    $this->actingAs(User::factory()->asSuperAdmin()->create());
    $count = User::query()->count();
    Livewire::test(CreateUser::class)->fillForm([
        'role' => AppRole::Dosen->value, 'name' => 'Dosen Baru', 'email' => 'nidn-baru@sita.test',
        'password' => 'password123', 'nik' => 'NIK-BARU', 'sidang_nidn' => ' 0012345678 ',
        'academic_assignments' => [[
            'program_studi_id' => $profile->program_studi_id, 'concentration' => $profile->concentration,
            'is_primary' => true, 'is_active' => true,
        ]],
        'supervision_quota' => 14, 'is_active' => true,
    ])->call('create')->assertHasFormErrors(['sidang_nidn' => 'unique']);
    expect(User::query()->count())->toBe($count);
});

it('rolls back provisioning and reports the field if a concurrent writer wins after validation', function (): void {
    DosenProfile::factory()->create(['sidang_nidn' => '0012345678']);
    $profile = DosenProfile::factory()->create();
    $profile->user->update(['phone_number' => '0811111111']);
    // Simulate validation having passed before the other writer committed.
    $validator = Validator::make([], []);
    Validator::shouldReceive('make')->twice()->andReturn($validator);

    expect(fn () => app(UserProvisioningService::class)->syncRoleAndProfiles($profile->user, [
        'role' => AppRole::Dosen->value, 'sidang_nidn' => '0012345678',
        'prodi' => $profile->program_studi_id, 'concentration' => $profile->concentration,
        'nik' => $profile->nik, 'phone_number' => '0822222222',
    ]))->toThrow(ValidationException::class, 'NIDN sudah digunakan dosen lain');
    expect($profile->fresh()->sidang_nidn)->toBeNull()
        ->and($profile->user->fresh()->phone_number)->toBe('0811111111');
});

it('keeps a lecturers own NIDN on edit and rejects another lecturers NIDN', function (): void {
    $owned = DosenProfile::factory()->create(['sidang_nidn' => '0012345678']);
    $other = DosenProfile::factory()->create(['sidang_nidn' => '0098765432']);
    $this->actingAs(User::factory()->asSuperAdmin()->create());
    $page = Livewire::test(EditUser::class, ['record' => $owned->user_id]);
    $page->fillForm(['sidang_nidn' => ' 0012345678 '])->call('save')->assertHasNoFormErrors();
    $page->fillForm(['sidang_nidn' => $other->sidang_nidn, 'name' => 'Tidak Boleh Tersimpan'])
        ->call('save')->assertHasFormErrors(['sidang_nidn' => 'unique']);
    expect($owned->fresh()->sidang_nidn)->toBe('0012345678')
        ->and($owned->user->fresh()->name)->not->toBe('Tidak Boleh Tersimpan');
    $page->fillForm(['sidang_nidn' => '', 'name' => 'Dosen Tanpa NIDN'])->call('save')->assertHasNoFormErrors();
    expect($owned->fresh()->sidang_nidn)->toBeNull();
});

it('preserves NIDN when an existing provisioning caller omits the field', function (): void {
    $profile = DosenProfile::factory()->create(['sidang_nidn' => '0012345678']);
    app(UserProvisioningService::class)->syncRoleAndProfiles($profile->user, [
        'role' => AppRole::Dosen->value, 'nik' => $profile->nik,
        'prodi' => $profile->program_studi_id, 'concentration' => $profile->concentration,
    ]);
    expect($profile->fresh()->sidang_nidn)->toBe('0012345678');
});

it('rolls back the whole user form and shows a field error when provisioning fails after validation', function (bool $editing): void {
    $profile = DosenProfile::factory()->create(['sidang_nidn' => '0012345678']);
    $this->actingAs(User::factory()->asSuperAdmin()->create());
    $count = User::query()->count();
    $name = $profile->user->name;
    $exception = ValidationException::withMessages(['sidang_nidn' => 'NIDN sudah digunakan dosen lain. Periksa kembali NIDN.']);
    $this->mock(UserProvisioningService::class, fn ($mock) => $mock->shouldReceive('syncRoleAndProfiles')->once()->andThrow($exception));
    if ($editing) {
        Livewire::test(EditUser::class, ['record' => $profile->user_id])
            ->fillForm(['name' => 'Nama Tidak Tersimpan', 'sidang_nidn' => '0099999999'])
            ->call('save')->assertHasFormErrors(['sidang_nidn']);
        expect($profile->user->fresh()->name)->toBe($name)
            ->and($profile->fresh()->sidang_nidn)->toBe('0012345678');
    } else {
        Livewire::test(CreateUser::class)->fillForm([
            'role' => AppRole::Dosen->value, 'name' => 'Nama Tidak Tersimpan', 'email' => 'rollback-nidn@sita.test',
            'password' => 'password123', 'nik' => 'NIK-ROLLBACK', 'sidang_nidn' => '0099999999',
            'academic_assignments' => [[
                'program_studi_id' => $profile->program_studi_id, 'concentration' => $profile->concentration,
                'is_primary' => true, 'is_active' => true,
            ]], 'supervision_quota' => 14, 'is_active' => true,
        ])->call('create')->assertHasFormErrors(['sidang_nidn']);
    }
    expect(User::query()->count())->toBe($count);
})->with(['create' => false, 'edit' => true]);

it('stops migration on normalized legacy duplicates without changing either value', function (): void {
    Schema::table('dosen_profiles', fn (Blueprint $table) => $table->dropUnique(['sidang_nidn']));
    $first = DosenProfile::factory()->create();
    $second = DosenProfile::factory()->create();
    DB::table('dosen_profiles')->where('id', $first->id)->update(['sidang_nidn' => '0012345678']);
    DB::table('dosen_profiles')->where('id', $second->id)->update(['sidang_nidn' => ' 0012345678 ']);
    $migration = require database_path('migrations/2026_11_08_150849_add_unique_sidang_nidn_to_dosen_profiles.php');
    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'NIDN ganda');
    expect($first->fresh()->sidang_nidn)->toBe('0012345678')
        ->and($second->fresh()->sidang_nidn)->toBe(' 0012345678 ');
});

it('normalizes safe legacy values before adding the unique constraint', function (): void {
    Schema::table('dosen_profiles', fn (Blueprint $table) => $table->dropUnique(['sidang_nidn']));
    $first = DosenProfile::factory()->create();
    $second = DosenProfile::factory()->create();
    DB::table('dosen_profiles')->where('id', $first->id)->update(['sidang_nidn' => ' 0012345678 ']);
    DB::table('dosen_profiles')->where('id', $second->id)->update(['sidang_nidn' => '   ']);
    $migration = require database_path('migrations/2026_11_08_150849_add_unique_sidang_nidn_to_dosen_profiles.php');
    $migration->up();
    expect($first->fresh()->sidang_nidn)->toBe('0012345678')
        ->and($second->fresh()->sidang_nidn)->toBeNull();
    expect(fn () => DosenProfile::factory()->create(['sidang_nidn' => '0012345678']))
        ->toThrow(UniqueConstraintViolationException::class);
});
