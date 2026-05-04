<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('password update page is displayed', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('user-password.edit'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('settings/password'));
});

test('password can be updated', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('user-password.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('user-password.edit'));

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();

    $this->assertDatabaseHas('system_audit_logs', [
        'event_type' => 'password_changed_by_user',
        'user_id' => $user->id,
        'email' => $user->email,
    ]);
});

test('correct password must be provided to update password', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('user-password.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(route('user-password.edit'));
});
