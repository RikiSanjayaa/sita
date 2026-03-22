<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\PasswordReset\ResetPassword as BaseResetPassword;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

class ResetPassword extends BaseResetPassword
{
    public function getTitle(): string|Htmlable
    {
        return 'Reset password admin';
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Buat password admin baru';
    }

    public function hasLogo(): bool
    {
        return false;
    }

    public function getMaxWidth(): Width|string|null
    {
        return Width::Large;
    }
}
