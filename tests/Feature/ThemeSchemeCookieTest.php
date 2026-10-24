<?php

use App\Support\ThemeSchemes;

beforeEach(function (): void {
    $this->withoutVite();
});

it('renders data-theme from the plain scheme cookie', function (string $scheme): void {
    $this->withUnencryptedCookie(ThemeSchemes::COOKIE, $scheme)
        ->get(route('login'))
        ->assertOk()
        ->assertSee('data-theme="'.$scheme.'"', escape: false);
})->with(ThemeSchemes::IDS);

it('falls back to the default scheme for a missing or unknown cookie', function (?string $value): void {
    $request = $value === null ? $this : $this->withUnencryptedCookie(ThemeSchemes::COOKIE, $value);

    $request->get(route('login'))
        ->assertOk()
        ->assertSee('data-theme="'.ThemeSchemes::DEFAULT.'"', escape: false);
})->with([
    'missing' => [null],
    'retired preset' => ['emerald'],
    'markup' => ['"><script>alert(1)</script>'],
]);
