<?php

use App\Models\User;
use NotificationChannels\WebPush\PushSubscription;

beforeEach(function () {
    $this->startSession();
    $this->withCookie(config('session.cookie'), session()->getId());
    $this->withCredentials();
});

function pushB64(string $raw): string
{
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

/**
 * @return array{endpoint: string, keys: array{p256dh: string, auth: string}, contentEncoding: string}
 */
function pushPayload(string $endpoint = 'https://fcm.googleapis.com/fcm/send/device-one'): array
{
    return [
        'endpoint' => $endpoint,
        'keys' => [
            'p256dh' => pushB64("\x04".random_bytes(64)),
            'auth' => pushB64(random_bytes(16)),
        ],
        'contentEncoding' => 'aes128gcm',
    ];
}

function enablePush(): void
{
    config([
        'webpush.enabled' => true,
        'webpush.vapid.public_key' => 'public-key-for-tests',
        'webpush.vapid.private_key' => 'private-key-for-tests',
        'webpush.vapid.subject' => 'mailto:admin@example.test',
    ]);
}

function currentSessionHash(): string
{
    test()->startSession();

    return hash('sha256', session()->getId());
}

test('guests cannot use the push subscription api', function () {
    $this->getJson(route('settings.push-subscriptions.index'))->assertUnauthorized();
    $this->postJson(route('settings.push-subscriptions.status'), ['endpoint' => 'x'])->assertUnauthorized();
    $this->postJson(route('settings.push-subscriptions.store'), pushPayload())->assertUnauthorized();
    $this->deleteJson(route('settings.push-subscriptions.destroy'), ['endpoint' => 'x'])->assertUnauthorized();
});

test('config exposes only the public key and mutates nothing', function () {
    enablePush();
    $user = User::factory()->asMahasiswa()->create();

    $response = $this->actingAs($user)->getJson(route('settings.push-subscriptions.index'))->assertOk();

    expect($response->json())->toBe(['enabled' => true, 'publicKey' => 'public-key-for-tests']);
    expect($response->getContent())->not->toContain('private-key-for-tests');
    expect(PushSubscription::query()->count())->toBe(0);
});

test('config reports disabled without a key', function () {
    config(['webpush.enabled' => false]);
    $user = User::factory()->asMahasiswa()->create();

    $this->actingAs($user)->getJson(route('settings.push-subscriptions.index'))
        ->assertOk()
        ->assertJson(['enabled' => false, 'publicKey' => null]);
});

test('a valid subscription can be enrolled, checked and removed', function () {
    enablePush();
    $user = User::factory()->asMahasiswa()->create(['browser_notifications_enabled' => false]);
    $hash = currentSessionHash();
    $payload = pushPayload();

    $this->actingAs($user)->postJson(route('settings.push-subscriptions.status'), ['endpoint' => $payload['endpoint']])
        ->assertOk()->assertExactJson(['subscribed' => false]);
    expect(PushSubscription::query()->count())->toBe(0);

    $this->actingAs($user)->postJson(route('settings.push-subscriptions.store'), $payload)
        ->assertOk()->assertExactJson(['subscribed' => true]);

    $row = PushSubscription::query()->sole();
    expect($row->endpoint)->toBe($payload['endpoint']);
    expect($row->public_key)->toBe($payload['keys']['p256dh']);
    expect($row->auth_token)->toBe($payload['keys']['auth']);
    expect($row->session_id)->toBe($hash);
    expect((string) $row->subscribable_id)->toBe((string) $user->getKey());
    expect($user->refresh()->browser_notifications_enabled)->toBeFalse();

    $this->actingAs($user)->postJson(route('settings.push-subscriptions.status'), ['endpoint' => $payload['endpoint']])
        ->assertOk()->assertExactJson(['subscribed' => true]);

    // Re-enrolling refreshes keys instead of duplicating.
    $again = pushPayload();
    $this->actingAs($user)->postJson(route('settings.push-subscriptions.store'), $again)->assertOk();
    expect(PushSubscription::query()->count())->toBe(1);
    expect(PushSubscription::query()->sole()->auth_token)->toBe($again['keys']['auth']);

    $this->actingAs($user)->deleteJson(route('settings.push-subscriptions.destroy'), ['endpoint' => $payload['endpoint']])
        ->assertOk()->assertExactJson(['subscribed' => false]);
    expect(PushSubscription::query()->count())->toBe(0);
});

test('registration is unavailable when push is disabled but removal still works', function () {
    enablePush();
    $user = User::factory()->asMahasiswa()->create();
    $payload = pushPayload();
    $this->actingAs($user)->postJson(route('settings.push-subscriptions.store'), $payload)->assertOk();

    config(['webpush.enabled' => false]);

    $this->actingAs($user)->postJson(route('settings.push-subscriptions.store'), pushPayload('https://fcm.googleapis.com/fcm/send/other'))
        ->assertStatus(503);
    $this->actingAs($user)->postJson(route('settings.push-subscriptions.status'), ['endpoint' => $payload['endpoint']])
        ->assertOk()->assertJson(['subscribed' => true]);
    $this->actingAs($user)->deleteJson(route('settings.push-subscriptions.destroy'), ['endpoint' => $payload['endpoint']])
        ->assertOk();
    expect(PushSubscription::query()->count())->toBe(0);
});

test('hostile or unsupported endpoints are rejected', function (string $endpoint) {
    enablePush();
    $user = User::factory()->asMahasiswa()->create();

    $this->actingAs($user)->postJson(route('settings.push-subscriptions.store'), pushPayload($endpoint))
        ->assertUnprocessable()->assertJsonValidationErrors('endpoint');
    expect(PushSubscription::query()->count())->toBe(0);
})->with([
    'http scheme' => 'http://fcm.googleapis.com/fcm/send/a',
    'unknown host' => 'https://example.com/push/a',
    'localhost' => 'https://localhost/push',
    'ipv4' => 'https://127.0.0.1/push',
    'private ipv4' => 'https://10.0.0.5/push',
    'ipv6' => 'https://[::1]/push',
    'credentials' => 'https://user:pass@fcm.googleapis.com/fcm/send/a',
    'userinfo trick' => 'https://fcm.googleapis.com@evil.test/fcm/send/a',
    'custom port' => 'https://fcm.googleapis.com:8443/fcm/send/a',
    'fragment' => 'https://fcm.googleapis.com/fcm/send/a#frag',
    'suffix lookalike' => 'https://fcm.googleapis.com.evil.test/a',
    'prefix lookalike' => 'https://evilfcm.googleapis.com/a',
    'fcm subdomain' => 'https://evil.fcm.googleapis.com/a',
    'mozilla lookalike' => 'https://xupdates.push.services.mozilla.com/a',
    'apple lookalike' => 'https://notweb.push.apple.com/a',
    'backslash' => 'https://fcm.googleapis.com\\@evil.test/a',
    'whitespace' => 'https://fcm.googleapis.com/a b',
    'uppercase host' => 'https://FCM.GOOGLEAPIS.COM/a',
]);

test('legitimate provider hosts and shards are accepted', function (string $endpoint) {
    enablePush();
    $user = User::factory()->asMahasiswa()->create();

    $this->actingAs($user)->postJson(route('settings.push-subscriptions.store'), pushPayload($endpoint))->assertOk();
    expect(PushSubscription::query()->sole()->endpoint)->toBe($endpoint);
})->with([
    'fcm' => 'https://fcm.googleapis.com/fcm/send/abc',
    'fcm wp' => 'https://fcm.googleapis.com/wp/abc',
    'mozilla' => 'https://updates.push.services.mozilla.com/wpush/v2/abc',
    'apple' => 'https://web.push.apple.com/QAbc',
    'apple shard' => 'https://16.web.push.apple.com/QAbc',
]);

test('overlong endpoints are rejected', function () {
    enablePush();
    $user = User::factory()->asMahasiswa()->create();

    $this->actingAs($user)->postJson(route('settings.push-subscriptions.store'), pushPayload('https://fcm.googleapis.com/'.str_repeat('a', 1100)))
        ->assertUnprocessable();
});

test('invalid keys and encodings are rejected', function (array $override) {
    enablePush();
    $user = User::factory()->asMahasiswa()->create();
    $payload = array_replace_recursive(pushPayload(), $override);

    $this->actingAs($user)->postJson(route('settings.push-subscriptions.store'), $payload)->assertUnprocessable();
    expect(PushSubscription::query()->count())->toBe(0);
})->with([
    'p256dh not base64url' => [['keys' => ['p256dh' => 'not base64!']]],
    'p256dh wrong length' => [['keys' => ['p256dh' => pushB64("\x04".str_repeat('a', 63))]]],
    'p256dh compressed prefix' => [['keys' => ['p256dh' => pushB64("\x02".str_repeat('a', 64))]]],
    'auth wrong length' => [['keys' => ['auth' => pushB64(str_repeat('a', 15))]]],
    'auth padded' => [['keys' => ['auth' => pushB64(str_repeat('a', 16)).'==']]],
    'encoding aesgcm' => [['contentEncoding' => 'aesgcm']],
]);

test('keys are required', function () {
    enablePush();
    $user = User::factory()->asMahasiswa()->create();

    $this->actingAs($user)->postJson(route('settings.push-subscriptions.store'), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/a'])
        ->assertUnprocessable()->assertJsonValidationErrors(['keys']);
});

test('another users endpoint is a conflict and ownership never transfers', function () {
    enablePush();
    $owner = User::factory()->asMahasiswa()->create();
    $other = User::factory()->asMahasiswa()->create();
    $payload = pushPayload();

    $this->actingAs($owner)->postJson(route('settings.push-subscriptions.store'), $payload)->assertOk();
    $original = PushSubscription::query()->sole();

    $this->actingAs($other)->postJson(route('settings.push-subscriptions.store'), pushPayload($payload['endpoint']))
        ->assertStatus(409);

    $after = PushSubscription::query()->sole();
    expect((string) $after->subscribable_id)->toBe((string) $owner->getKey());
    expect($after->public_key)->toBe($original->public_key);

    // The other account sees nothing and cannot delete it.
    $this->actingAs($other)->postJson(route('settings.push-subscriptions.status'), ['endpoint' => $payload['endpoint']])
        ->assertOk()->assertExactJson(['subscribed' => false]);
    $this->actingAs($other)->deleteJson(route('settings.push-subscriptions.destroy'), ['endpoint' => $payload['endpoint']])
        ->assertOk();
    expect(PushSubscription::query()->count())->toBe(1);
});

test('status and removal are scoped to the current session', function () {
    enablePush();
    $user = User::factory()->asMahasiswa()->create();
    $payload = pushPayload();
    $this->actingAs($user)->postJson(route('settings.push-subscriptions.store'), $payload)->assertOk();

    PushSubscription::query()->update(['session_id' => hash('sha256', 'another-session')]);

    $this->actingAs($user)->postJson(route('settings.push-subscriptions.status'), ['endpoint' => $payload['endpoint']])
        ->assertOk()->assertExactJson(['subscribed' => false]);
    $this->actingAs($user)->deleteJson(route('settings.push-subscriptions.destroy'), ['endpoint' => $payload['endpoint']])
        ->assertOk();
    expect(PushSubscription::query()->count())->toBe(1);
});

test('logout removes only the current session subscriptions of the current user', function () {
    enablePush();
    $user = User::factory()->asMahasiswa()->create();
    $other = User::factory()->asMahasiswa()->create();
    $hash = currentSessionHash();

    $this->actingAs($user)->postJson(route('settings.push-subscriptions.store'), pushPayload('https://fcm.googleapis.com/fcm/send/this-device'))->assertOk();

    $user->pushSubscriptions()->make()->forceFill([
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/second-device',
        'public_key' => 'k', 'auth_token' => 'a', 'session_id' => hash('sha256', 'second-session'),
    ])->save();
    $other->pushSubscriptions()->make()->forceFill([
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/other-user',
        'public_key' => 'k', 'auth_token' => 'a', 'session_id' => $hash,
    ])->save();

    $this->actingAs($user)->post('/logout');

    expect(PushSubscription::query()->orderBy('endpoint')->pluck('endpoint')->all())->toBe([
        'https://fcm.googleapis.com/fcm/send/other-user',
        'https://fcm.googleapis.com/fcm/send/second-device',
    ]);
});

test('no response exposes the private key or stored secrets', function () {
    enablePush();
    $user = User::factory()->asMahasiswa()->create();
    $payload = pushPayload();

    foreach ([
        $this->actingAs($user)->postJson(route('settings.push-subscriptions.store'), $payload),
        $this->actingAs($user)->getJson(route('settings.push-subscriptions.index')),
        $this->actingAs($user)->postJson(route('settings.push-subscriptions.status'), ['endpoint' => $payload['endpoint']]),
    ] as $response) {
        expect($response->getContent())->not->toContain('private-key-for-tests')->not->toContain($payload['keys']['auth']);
    }
});
