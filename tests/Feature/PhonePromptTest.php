<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the phone prompt reflects the mahasiswa phone state', function (?string $phone, ?string $confirmedAt, ?array $expected) {
    $user = User::factory()->asMahasiswa()->create([
        'phone_number' => $phone,
        'phone_number_confirmed_at' => $confirmedAt,
    ]);

    $this
        ->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('phonePrompt', $expected));
})->with([
    'no phone number asks for one' => [null, null, ['mode' => 'required', 'phoneNumber' => null]],
    'blank phone number asks for one' => ['', null, ['mode' => 'required', 'phoneNumber' => null]],
    'imported phone number asks for confirmation' => ['081234567890', null, ['mode' => 'confirm', 'phoneNumber' => '081234567890']],
    'confirmed phone number shows nothing' => ['081234567890', '2026-01-01 00:00:00', null],
]);

test('dosen, kaprodi and admin never receive a phone prompt', function (string $role) {
    $user = User::factory()->{'as'.ucfirst($role)}()->create([
        'phone_number' => null,
        'phone_number_confirmed_at' => null,
    ]);

    $this
        ->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('phonePrompt', null));
})->with(['dosen', 'kaprodi', 'admin']);
