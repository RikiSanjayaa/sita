<?php

use App\Models\User;
use App\Services\ChatUnreadService;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
});

it('shares success and error messages on public Inertia pages', function (string $key, string $message): void {
    $this->withSession([$key => $message])
        ->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('auth/login')
            ->where('flash.id', fn ($id): bool => is_string($id) && Str::isUuid($id))
            ->where('flash.'.$key, $message)
            ->where('flash.'.($key === 'success' ? 'error' : 'success'), null)
            ->missing('flashMessage')
            ->missing('errorMessage'));
})->with([
    'success' => ['success', 'Perubahan berhasil disimpan.'],
    'error' => ['error', 'Perubahan tidak dapat disimpan.'],
]);

it('shares an empty flash payload when there is no action feedback', function (): void {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('flash', ['id' => null, 'success' => null, 'error' => null]));
});

it('keeps authentication statuses separate from action feedback', function (string $status): void {
    $this->withSession(['status' => $status])
        ->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('status', $status)
            ->where('flash', ['id' => null, 'success' => null, 'error' => null]));
})->with(['verification-link-sent', 'passwords.sent', 'passwords.reset']);

it('gives repeated identical actions different flash ids and expires their feedback', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $ids = [];

    for ($occurrence = 0; $occurrence < 2; $occurrence++) {
        $this->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => '',
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $response = $this->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('flash.id', fn ($id): bool => is_string($id) && Str::isUuid($id))
                ->where('flash.success', 'Profil berhasil diperbarui.')
                ->where('flash.error', null));

        $ids[] = $response->inertiaProps('flash.id');

        $this->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('flash', ['id' => null, 'success' => null, 'error' => null]));
    }

    expect($ids[0])->not->toBe($ids[1]);
});

it('preserves saved feedback across intervening background requests and consumes it once', function (array $requests): void {
    $user = User::factory()->asMahasiswa()->create();
    $this->actingAs($user)->withCredentials();
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);
    app(BroadcastManager::class)->forgetDrivers();
    require base_path('routes/channels.php');

    $this->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => $user->email,
    ])->assertRedirect(route('profile.edit'));
    $this->withCookie(config('session.cookie'), app('session.store')->getId());

    foreach ($requests as $request) {
        if ($request === 'unread') {
            $this->getJson(route('chat.unread'))->assertOk();
        } else {
            $this->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => 'private-App.Models.User.'.$user->id,
            ])->assertOk();
        }
    }

    $this->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('flash.success', 'Profil berhasil diperbarui.'));

    $this->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('flash', ['id' => null, 'success' => null, 'error' => null]));
})->with([
    'unread between save and redirect' => [['unread']],
    'auth between save and redirect' => [['auth']],
    'unread then auth' => [['unread', 'auth']],
    'auth then unread' => [['auth', 'unread']],
    'repeated unread requests' => [['unread', 'unread', 'unread']],
]);

it('does not let a late background snapshot overwrite newer session feedback', function (string $background): void {
    Storage::fake('local');
    $directory = Storage::disk('local')->path('race-sessions');
    File::ensureDirectoryExists($directory);
    config([
        'session.driver' => 'file',
        'session.files' => $directory,
        'session.encrypt' => false,
        'session.lottery' => [0, 100],
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);
    Session::forgetDrivers();
    app()->forgetInstance('session.store');
    app(BroadcastManager::class)->forgetDrivers();
    require base_path('routes/channels.php');
    $session = Session::driver();
    $session->start();
    $session->put('active_role', 'mahasiswa');
    $session->save();
    $id = $session->getId();
    $handler = $session->getHandler();
    $written = false;
    $saveWhileBackgroundIsRunning = function () use ($id, $handler, &$written): void {
        if ($written) {
            return;
        }
        $written = true;
        // A separate request writes after the background request loaded its snapshot.
        $foreground = new Store('foreground', $handler, $id);
        $foreground->start();
        $foreground->flash('success', 'Profil berhasil diperbarui.');
        $foreground->save();
    };

    $user = User::factory()->asMahasiswa()->create();
    $this->actingAs($user)->withCredentials()->withCookie(config('session.cookie'), $id);

    if ($background === 'unread') {
        app()->afterResolving(ChatUnreadService::class, $saveWhileBackgroundIsRunning);
        $this->getJson(route('chat.unread'))->assertOk();
    } else {
        Broadcast::channel('session-race.{id}', function (User $actor, string $id) use ($saveWhileBackgroundIsRunning): bool {
            $saveWhileBackgroundIsRunning();

            return (string) $actor->id === $id;
        });
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-session-race.'.$user->id,
        ])->assertOk();
    }

    $this->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('flash.success', 'Profil berhasil diperbarui.'));

    $this->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('flash', ['id' => null, 'success' => null, 'error' => null]));
})->with(['unread', 'auth']);

it('persists authentication restored by a background request from a remember cookie', function (string $background): void {
    Storage::fake('local');
    $directory = Storage::disk('local')->path('remember-sessions');
    File::ensureDirectoryExists($directory);
    config([
        'session.driver' => 'file',
        'session.files' => $directory,
        'session.lottery' => [0, 100],
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);
    Session::forgetDrivers();
    app()->forgetInstance('session.store');
    Auth::forgetGuards();
    app(BroadcastManager::class)->forgetDrivers();
    require base_path('routes/channels.php');

    $user = User::factory()->asMahasiswa()->create();
    $recaller = Auth::guard('web')->getRecallerName();
    $expiredId = Str::random(40);
    $this->withCredentials()
        ->withCookie(config('session.cookie'), $expiredId)
        ->withCookie($recaller, $user->id.'|'.$user->getRememberToken().'|'.$user->getAuthPassword());

    if ($background === 'unread') {
        $this->getJson(route('chat.unread'))->assertOk();
    } else {
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-App.Models.User.'.$user->id,
        ])->assertOk();
    }

    $restoredId = app('session.store')->getId();
    expect($restoredId)->not->toBe($expiredId);
    $this->withCookie(config('session.cookie'), $restoredId)->withCookie($recaller, '');
    Session::forgetDrivers();
    app()->forgetInstance('session.store');
    Auth::forgetGuards();

    $this->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('auth.user.id', $user->id));
})->with(['unread', 'auth']);
