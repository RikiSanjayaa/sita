<?php

use App\Models\User;

test('guests cannot confirm a phone number', function () {
    $this
        ->patch(route('settings.phone-confirmation.update'), ['phone_number' => '081234567890'])
        ->assertRedirect(route('login'));
});

test('a mahasiswa saves a canonical phone number and confirms it', function () {
    $user = User::factory()->asMahasiswa()->create([
        'phone_number' => null,
        'phone_number_confirmed_at' => null,
    ]);

    $this
        ->actingAs($user)
        ->patch(route('settings.phone-confirmation.update'), ['phone_number' => '+62 812-3456-7890'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $user->refresh();

    expect($user->phone_number)->toBe('081234567890');
    expect($user->phone_number_confirmed_at)->not->toBeNull();
});

test('an imported phone number is confirmed without changing it', function () {
    $user = User::factory()->asMahasiswa()->create([
        'phone_number' => '081234567890',
        'phone_number_confirmed_at' => null,
    ]);

    $this
        ->actingAs($user)
        ->patch(route('settings.phone-confirmation.update'), ['phone_number' => '081234567890'])
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->phone_number)->toBe('081234567890');
    expect($user->phone_number_confirmed_at)->not->toBeNull();
});

test('invalid phone numbers are rejected and nothing is confirmed', function (string $input) {
    $user = User::factory()->asMahasiswa()->create([
        'phone_number' => null,
        'phone_number_confirmed_at' => null,
    ]);

    $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('settings.phone-confirmation.update'), ['phone_number' => $input])
        ->assertSessionHasErrors('phone_number');

    $user->refresh();

    expect($user->phone_number)->toBeNull();
    expect($user->phone_number_confirmed_at)->toBeNull();
})->with([
    'blank' => [''],
    'too short' => ['08123'],
    'landline prefix' => ['0215551234'],
    'contains letters' => ['08abc'],
]);

test('changing the phone number on the profile page marks it as freshly confirmed', function () {
    $user = User::factory()->asMahasiswa()->create([
        'phone_number' => '081234567890',
        'phone_number_confirmed_at' => '2026-01-01 00:00:00',
    ]);

    $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => '0812 9999 0000',
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->phone_number)->toBe('081299990000');
    expect($user->phone_number_confirmed_at)->not->toBeNull()
        ->and($user->phone_number_confirmed_at->toDateTimeString())->not->toBe('2026-01-01 00:00:00');
});

test('saving the profile without changing the phone number keeps its confirmation', function () {
    $user = User::factory()->asMahasiswa()->create([
        'phone_number' => '081234567890',
        'phone_number_confirmed_at' => '2026-01-01 00:00:00',
    ]);

    $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Nama Baru',
            'email' => $user->email,
            'phone_number' => '081234567890',
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->phone_number_confirmed_at->toDateTimeString())->toBe('2026-01-01 00:00:00');
});

test('clearing the phone number on the profile page removes its confirmation', function () {
    $user = User::factory()->asMahasiswa()->create([
        'phone_number' => '081234567890',
        'phone_number_confirmed_at' => '2026-01-01 00:00:00',
    ]);

    $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => '',
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->phone_number)->toBeNull();
    expect($user->phone_number_confirmed_at)->toBeNull();
});
