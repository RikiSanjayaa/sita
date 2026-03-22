<?php

use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use Illuminate\Support\Facades\Notification;

test('reset password link screen can be rendered', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->get(route('password.request'));

    $response->assertOk();
});

test('reset password link can be requested', function () {
    /** @var \Tests\TestCase $this */
    Notification::fake();
    config()->set('mail.default', 'log');
    config()->set('services.resend.key', 'test-resend-key');

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
        expect($notification->toMail($user)->mailer)->toBe('resend');

        return true;
    });

    $this->assertDatabaseHas('system_audit_logs', [
        'event_type' => 'password_reset_requested',
        'email' => $user->email,
    ]);
});

test('reset password screen can be rendered', function () {
    /** @var \Tests\TestCase $this */
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) {
        /** @var \Tests\TestCase $this */
        $response = $this->get(route('password.reset', $notification->token));

        $response->assertOk();

        return true;
    });
});

test('password can be reset with valid token', function () {
    /** @var \Tests\TestCase $this */
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
        /** @var \Tests\TestCase $this */
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('system_audit_logs', [
            'event_type' => 'password_reset_success',
            'email' => $user->email,
            'user_id' => $user->id,
        ]);

        return true;
    });
});

test('password cannot be reset with invalid token', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertSessionHasErrors('email');
});
