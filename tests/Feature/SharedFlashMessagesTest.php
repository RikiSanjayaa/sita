<?php

use App\Models\User;
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
