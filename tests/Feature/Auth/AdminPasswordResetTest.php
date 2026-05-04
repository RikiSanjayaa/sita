<?php

use App\Filament\Pages\Auth\RequestPasswordReset;
use App\Filament\Pages\Auth\ResetPassword;
use App\Models\User;
use App\Notifications\Auth\AdminResetPasswordNotification;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('admin login page exposes forgot password link', function () {
    /** @var \Tests\TestCase $this */
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('/admin/password-reset/request', false);
});

test('admin can request password reset link', function () {
    /** @var \Tests\TestCase $this */
    Notification::fake();
    config()->set('mail.default', 'log');
    config()->set('services.resend.key', 'test-resend-key');

    $admin = User::factory()->asAdmin()->create();

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(RequestPasswordReset::class)
        ->fillForm([
            'email' => $admin->email,
        ])
        ->call('request');

    Notification::assertSentTo($admin, AdminResetPasswordNotification::class, function ($notification) use ($admin) {
        expect($notification->toMail($admin)->mailer)->toBe('resend');

        return true;
    });

    $this->assertDatabaseHas('system_audit_logs', [
        'event_type' => 'password_reset_requested',
        'email' => $admin->email,
    ]);
});

test('admin password can be reset from admin flow', function () {
    /** @var \Tests\TestCase $this */
    Notification::fake();

    $admin = User::factory()->asAdmin()->create();

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(RequestPasswordReset::class)
        ->fillForm([
            'email' => $admin->email,
        ])
        ->call('request');

    Notification::assertSentTo($admin, AdminResetPasswordNotification::class, function ($notification) use ($admin) {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ResetPassword::class, [
            'email' => $admin->email,
            'token' => $notification->token,
        ])
            ->fillForm([
                'password' => 'new-password-123',
                'passwordConfirmation' => 'new-password-123',
            ])
            ->call('resetPassword');

        return true;
    });

    expect(Hash::check('new-password-123', $admin->fresh()->password))->toBeTrue();

    $this->assertDatabaseHas('system_audit_logs', [
        'event_type' => 'password_reset_success',
        'email' => $admin->email,
        'user_id' => $admin->id,
    ]);
});
