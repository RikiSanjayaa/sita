<?php

use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\AdminProfile;
use App\Models\ProgramStudi;
use App\Models\User;
use Livewire\Livewire;

test('admin cannot edit their own email from user management page', function (): void {
    /** @var \Tests\TestCase $this */
    $prodi = ProgramStudi::factory()->create();

    $admin = User::factory()->asSuperAdmin()->create([
        'email' => 'admin-awal@example.test',
        'name' => 'Admin Lama',
    ]);

    AdminProfile::query()->create([
        'user_id' => $admin->id,
        'program_studi_id' => $prodi->id,
    ]);

    $this->actingAs($admin);

    Livewire::test(EditUser::class, ['record' => $admin->getKey()])
        ->assertFormFieldDisabled('email')
        ->set('data.email', 'admin-baru@example.test')
        ->set('data.name', 'Admin Baru')
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect($admin->fresh()->email)->toBe('admin-awal@example.test');
    expect($admin->fresh()->name)->toBe('Admin Baru');
});
