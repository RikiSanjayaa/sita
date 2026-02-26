<?php

use App\Models\User;
use App\Notifications\RealtimeNotification;

test('user can update notification settings', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('settings.notifications.update'), [
            'browserNotifications' => true,
            'pesanBaru' => false,
            'statusTugasAkhir' => true,
            'jadwalBimbingan' => false,
            'feedbackDokumen' => true,
            'reminderDeadline' => false,
            'pengumumanSistem' => true,
            'konfirmasiBimbingan' => false,
        ]);

    $response->assertSessionHasNoErrors()->assertRedirect();

    $user->refresh();

    expect($user->browser_notifications_enabled)->toBeTrue();
    expect($user->notification_preferences)->toMatchArray([
        'pesanBaru' => false,
        'statusTugasAkhir' => true,
        'jadwalBimbingan' => false,
        'feedbackDokumen' => true,
        'reminderDeadline' => false,
        'pengumumanSistem' => true,
        'konfirmasiBimbingan' => false,
    ]);
});

test('user can mark notifications as read', function () {
    $user = User::factory()->create();

    $user->notify(new RealtimeNotification([
        'title' => 'Tes',
        'description' => 'Notifikasi tes',
        'icon' => 'bell',
        'url' => '/dashboard',
    ]));

    $notificationId = $user->unreadNotifications()->firstOrFail()->id;

    $this->actingAs($user)
        ->post(route('settings.notifications.read', ['notificationId' => $notificationId]))
        ->assertOk();

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});

test('user can mark all notifications as read', function () {
    $user = User::factory()->create();

    $user->notify(new RealtimeNotification([
        'title' => 'Tes 1',
        'description' => 'Notifikasi tes 1',
        'icon' => 'bell',
        'url' => '/dashboard',
    ]));

    $user->notify(new RealtimeNotification([
        'title' => 'Tes 2',
        'description' => 'Notifikasi tes 2',
        'icon' => 'bell',
        'url' => '/dashboard',
    ]));

    $this->actingAs($user)
        ->post(route('settings.notifications.read-all'))
        ->assertOk();

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});
