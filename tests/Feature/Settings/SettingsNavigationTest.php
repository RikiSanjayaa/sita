<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('settings route redirects to profile page', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/settings')
        ->assertRedirect('/settings/profile');
});

test('notification settings page is displayed', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/settings/notifications')
        ->assertInertia(fn (Assert $page) => $page->component('settings/notifications'));
});
