<?php

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('admin profile page can be rendered', function (): void {
    /** @var \Tests\TestCase $this */
    $admin = User::factory()->asAdmin()->create();

    $this->actingAs($admin)
        ->get('/admin/profile')
        ->assertOk()
        ->assertSeeText('Konfirmasi password baru')
        ->assertSee('/admin/password-reset/request', false);
});

test('admin cannot edit their own email from filament profile page', function (): void {
    /** @var \Tests\TestCase $this */
    $admin = User::factory()->asAdmin()->create([
        'email' => 'admin-awal@example.test',
    ]);

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(EditProfile::class)
        ->assertFormFieldDisabled('email')
        ->set('data.email', 'admin-baru@example.test')
        ->set('data.name', 'Nama Tetap Aman')
        ->set('data.currentPassword', 'password')
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect($admin->fresh()->email)->toBe('admin-awal@example.test');
    expect($admin->fresh()->name)->toBe('Nama Tetap Aman');
});

test('admin can update their own profile and password from filament profile page', function (): void {
    /** @var \Tests\TestCase $this */
    $admin = User::factory()->asAdmin()->create([
        'name' => 'Admin Lama',
        'email' => 'admin-lama@example.test',
    ]);

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(EditProfile::class)
        ->fillForm([
            'name' => 'Admin Baru',
            'password' => 'new-admin-password-123!',
            'passwordConfirmation' => 'new-admin-password-123!',
            'currentPassword' => 'password',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect($admin->fresh()->name)->toBe('Admin Baru');
    expect($admin->fresh()->email)->toBe('admin-lama@example.test');
    expect(Hash::check('new-admin-password-123!', $admin->fresh()->password))->toBeTrue();

    $this->assertDatabaseHas('system_audit_logs', [
        'event_type' => 'profile_updated_by_user',
        'user_id' => $admin->id,
        'email' => 'admin-lama@example.test',
    ]);

    $this->assertDatabaseHas('system_audit_logs', [
        'event_type' => 'password_changed_by_user',
        'user_id' => $admin->id,
        'email' => 'admin-lama@example.test',
    ]);
});

test('admin must provide current password to update password on profile page', function (): void {
    /** @var \Tests\TestCase $this */
    $admin = User::factory()->asAdmin()->create();

    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(EditProfile::class)
        ->fillForm([
            'name' => $admin->name,
            'password' => 'new-admin-password-123!',
            'passwordConfirmation' => 'new-admin-password-123!',
            'currentPassword' => 'wrong-password',
        ])
        ->call('save')
        ->assertHasFormErrors(['currentPassword']);
});
