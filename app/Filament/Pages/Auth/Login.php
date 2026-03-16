<?php

namespace App\Filament\Pages\Auth;

use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

class Login extends BaseLogin
{
    protected string $view = 'filament.pages.auth.login';

    public function getTitle(): string|Htmlable
    {
        return 'Login Admin';
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Masuk ke panel admin';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Gunakan akun admin atau super admin untuk mengelola SiTA Universitas Bumigora.';
    }

    public function hasLogo(): bool
    {
        return false;
    }

    public function getMaxWidth(): Width|string|null
    {
        return Width::Large;
    }

    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()
            ->label('Masuk ke dashboard');
    }
}
