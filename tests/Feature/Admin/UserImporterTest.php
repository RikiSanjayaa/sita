<?php

use App\Enums\AppRole;
use App\Filament\Imports\UserImporter;
use App\Models\Role;
use App\Models\User;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Hash;

function makeUserImporter(User $admin): UserImporter
{
    $import = Import::query()->create([
        'completed_at' => null,
        'file_name' => 'users.csv',
        'file_path' => 'imports/users.csv',
        'importer' => UserImporter::class,
        'processed_rows' => 0,
        'total_rows' => 1,
        'successful_rows' => 0,
        'user_id' => $admin->id,
    ]);

    return new UserImporter(
        import: $import,
        columnMap: [
            'name' => 'name',
            'email' => 'email',
            'role' => 'role',
            'password' => 'password',
            'nim' => 'nim',
            'program_studi' => 'program_studi',
            'angkatan' => 'angkatan',
            'status_akademik' => 'status_akademik',
            'nidn' => 'nidn',
            'homebase' => 'homebase',
            'is_active' => 'is_active',
            'browser_notifications_enabled' => 'browser_notifications_enabled',
        ],
        options: [],
    );
}

test('importer creates mahasiswa and core profile fields with default password from nim', function () {
    $adminRole = Role::query()->firstOrCreate(['name' => AppRole::Admin->value]);
    $admin = User::factory()->asAdmin()->create();
    $admin->roles()->syncWithoutDetaching([$adminRole->id]);

    $importer = makeUserImporter($admin);

    $importer([
        'name' => 'Mahasiswa Import',
        'email' => 'mahasiswa-import@sita.test',
        'role' => AppRole::Mahasiswa->value,
        'password' => '',
        'nim' => '2210517777',
        'program_studi' => 'Informatika',
        'angkatan' => '2022',
        'status_akademik' => 'aktif',
        'nidn' => '',
        'homebase' => '',
        'is_active' => '',
        'browser_notifications_enabled' => '1',
    ]);

    $user = User::query()->where('email', 'mahasiswa-import@sita.test')->firstOrFail();

    expect($user->last_active_role)->toBe(AppRole::Mahasiswa->value);
    expect($user->hasRole(AppRole::Mahasiswa))->toBeTrue();
    expect(Hash::check('2210517777', $user->password))->toBeTrue();
    expect($user->mahasiswaProfile)->not->toBeNull();
    expect($user->mahasiswaProfile?->nim)->toBe('2210517777');
    expect($user->mahasiswaProfile?->program_studi)->toBe('Informatika');
    expect($user->mahasiswaProfile?->angkatan)->toBe(2022);
});

test('importer updates existing dosen without overwriting password when blank', function () {
    $adminRole = Role::query()->firstOrCreate(['name' => AppRole::Admin->value]);
    $admin = User::factory()->asAdmin()->create();
    $admin->roles()->syncWithoutDetaching([$adminRole->id]);

    $existing = User::factory()->create([
        'email' => 'dosen-import@sita.test',
        'password' => 'keep-this-password',
        'last_active_role' => AppRole::Mahasiswa->value,
    ]);

    $importer = makeUserImporter($admin);

    $importer([
        'name' => 'Dosen Import Updated',
        'email' => 'dosen-import@sita.test',
        'role' => AppRole::Dosen->value,
        'password' => '',
        'nim' => '',
        'program_studi' => 'Informatika',
        'angkatan' => '',
        'status_akademik' => '',
        'nidn' => '1999001122',
        'homebase' => 'Teknik Informatika',
        'is_active' => '0',
        'browser_notifications_enabled' => '0',
    ]);

    $existing->refresh();

    expect($existing->name)->toBe('Dosen Import Updated');
    expect($existing->last_active_role)->toBe(AppRole::Dosen->value);
    expect($existing->hasRole(AppRole::Dosen))->toBeTrue();
    expect(Hash::check('keep-this-password', $existing->password))->toBeTrue();
    expect($existing->dosenProfile)->not->toBeNull();
    expect($existing->dosenProfile?->nidn)->toBe('1999001122');
    expect($existing->dosenProfile?->homebase)->toBe('Teknik Informatika');
    expect($existing->dosenProfile?->is_active)->toBeFalse();
});
