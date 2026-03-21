<?php

use App\Models\User;
use App\Notifications\RealtimeNotification;
use App\Services\RealtimeNotificationService;
use Illuminate\Support\Facades\Notification;

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

test('user can delete one read notification', function () {
    $user = User::factory()->create();

    $user->notify(new RealtimeNotification([
        'title' => 'Tes dibaca',
        'description' => 'Notifikasi yang sudah dibaca',
        'icon' => 'bell',
        'url' => '/dashboard',
    ]));

    $notification = $user->notifications()->firstOrFail();
    $notification->markAsRead();

    $this->actingAs($user)
        ->delete(route('settings.notifications.delete-one', ['notificationId' => $notification->id]))
        ->assertOk();

    expect($user->fresh()->notifications()->count())->toBe(0);
});

test('user cannot delete unread notification directly', function () {
    $user = User::factory()->create();

    $user->notify(new RealtimeNotification([
        'title' => 'Tes unread',
        'description' => 'Notifikasi unread',
        'icon' => 'bell',
        'url' => '/dashboard',
    ]));

    $notificationId = $user->notifications()->firstOrFail()->id;

    $this->actingAs($user)
        ->delete(route('settings.notifications.delete-one', ['notificationId' => $notificationId]))
        ->assertUnprocessable();

    expect($user->fresh()->notifications()->count())->toBe(1)
        ->and($user->fresh()->unreadNotifications()->count())->toBe(1);
});

test('user can delete all read notifications without touching unread ones', function () {
    $user = User::factory()->create();

    $user->notify(new RealtimeNotification([
        'title' => 'Tes unread',
        'description' => 'Notifikasi unread',
        'icon' => 'bell',
        'url' => '/dashboard',
    ]));

    $user->notify(new RealtimeNotification([
        'title' => 'Tes read',
        'description' => 'Notifikasi read',
        'icon' => 'bell',
        'url' => '/dashboard',
    ]));

    $readNotification = $user->notifications()->latest()->firstOrFail();
    $readNotification->markAsRead();

    $this->actingAs($user)
        ->delete(route('settings.notifications.delete-read'))
        ->assertOk();

    expect($user->fresh()->notifications()->count())->toBe(1)
        ->and($user->fresh()->unreadNotifications()->count())->toBe(1);
});

test('disabling one notification preference only blocks that notification type', function (string $disabledKey) {
    Notification::fake();

    $preferences = collect(User::NOTIFICATION_PREFERENCE_KEYS)
        ->mapWithKeys(fn (string $key): array => [$key => $key !== $disabledKey])
        ->all();

    $user = User::factory()->create([
        'notification_preferences' => $preferences,
    ]);

    $service = app(RealtimeNotificationService::class);

    foreach (User::NOTIFICATION_PREFERENCE_KEYS as $key) {
        $service->notifyUser($user, $key, [
            'title' => "Notif {$key}",
            'description' => "Deskripsi {$key}",
            'icon' => 'bell',
            'url' => '/dashboard',
        ]);
    }

    $sentKeys = Notification::sent($user, RealtimeNotification::class)
        ->map(fn (RealtimeNotification $notification): string => $notification->toArray($user)['preferenceKey'])
        ->values()
        ->all();

    expect($sentKeys)
        ->toHaveCount(count(User::NOTIFICATION_PREFERENCE_KEYS) - 1)
        ->not->toContain($disabledKey)
        ->toEqualCanonicalizing(array_values(array_filter(
            User::NOTIFICATION_PREFERENCE_KEYS,
            fn (string $key): bool => $key !== $disabledKey,
        )));
})->with([
    'pesan baru' => 'pesanBaru',
    'status tugas akhir' => 'statusTugasAkhir',
    'jadwal bimbingan' => 'jadwalBimbingan',
    'feedback dokumen' => 'feedbackDokumen',
    'reminder deadline' => 'reminderDeadline',
    'pengumuman sistem' => 'pengumumanSistem',
    'konfirmasi bimbingan' => 'konfirmasiBimbingan',
]);
