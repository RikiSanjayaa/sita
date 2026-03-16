<?php

use App\Enums\AppRole;
use App\Http\Responses\LoginResponse;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

function createUserWithRoles(array $roles, ?string $activeRole = null): User
{
    $user = User::factory()->create([
        'last_active_role' => $activeRole ?? $roles[0] ?? AppRole::Mahasiswa->value,
    ]);

    $roleIds = collect($roles)
        ->map(fn(string $name): int => Role::query()->firstOrCreate(['name' => $name])->id)
        ->all();

    $user->roles()->sync($roleIds);

    return $user;
}

test('mahasiswa can only access mahasiswa area', function () {
    $user = createUserWithRoles([AppRole::Mahasiswa->value], AppRole::Mahasiswa->value);

    $this->actingAs($user);

    $this->get('/mahasiswa/dashboard')->assertOk();
    $this->get('/dosen/dashboard')->assertForbidden();
    $this->get('/admin')->assertForbidden();
});

test('dosen can only access dosen area', function () {
    $user = createUserWithRoles([AppRole::Dosen->value], AppRole::Dosen->value);

    $this->actingAs($user);

    $this->get('/dosen/dashboard')->assertOk();
    $this->get('/mahasiswa/dashboard')->assertForbidden();
    $this->get('/admin')->assertForbidden();
});

test('admin can only access admin area', function () {
    $user = createUserWithRoles([AppRole::Admin->value], AppRole::Admin->value);

    $this->actingAs($user);

    $this->followingRedirects()->get('/admin')->assertOk();
    $this->get('/mahasiswa/dashboard')->assertForbidden();
    $this->get('/dosen/dashboard')->assertForbidden();
});

test('super admin can access admin area', function () {
    $user = createUserWithRoles([AppRole::SuperAdmin->value], AppRole::SuperAdmin->value);

    $this->actingAs($user);

    $this->followingRedirects()->get('/admin')->assertOk();
    $this->get('/mahasiswa/dashboard')->assertForbidden();
    $this->get('/dosen/dashboard')->assertForbidden();
});

test('dashboard resolver sends authenticated user to active role dashboard', function () {
    $mahasiswa = createUserWithRoles([AppRole::Mahasiswa->value], AppRole::Mahasiswa->value);
    $dosen = createUserWithRoles([AppRole::Dosen->value], AppRole::Dosen->value);
    $admin = createUserWithRoles([AppRole::Admin->value], AppRole::Admin->value);
    $superAdmin = createUserWithRoles([AppRole::SuperAdmin->value], AppRole::SuperAdmin->value);

    $this->actingAs($mahasiswa)
        ->get('/dashboard')
        ->assertRedirect('/mahasiswa/dashboard');

    $this->actingAs($dosen)
        ->get('/dashboard')
        ->assertRedirect('/dosen/dashboard');

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertRedirect('/admin');

    $this->actingAs($superAdmin)
        ->get('/dashboard')
        ->assertRedirect('/admin');
});

test('unauthenticated admin panel access redirects to admin login', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

test('authenticated admin opening default login gets redirected to panel', function () {
    $admin = createUserWithRoles([AppRole::Admin->value], AppRole::Admin->value);

    $this->actingAs($admin)
        ->get('/login')
        ->assertRedirect('/admin');
});

test('panel-specific admin login page is enabled', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('Masuk ke panel admin')
        ->assertSee('SiTA Universitas Bumigora');
});

test('admin-only user cannot login via regular /login', function () {
    $admin = createUserWithRoles([AppRole::Admin->value], AppRole::Admin->value);
    $session = app('session.store');
    $session->start();

    $request = Request::create('/login', 'POST');
    $request->setLaravelSession($session);
    $request->setUserResolver(fn (): User => $admin);

    Auth::guard('web')->login($admin);

    try {
        app(LoginResponse::class)->toResponse($request);

        $this->fail('Expected admin-only login through /login to be rejected.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe([
            Fortify::username() => ['Admin harus login melalui halaman /admin/login.'],
        ]);
    }

    $this->assertGuest();
});
