<?php

namespace App\Notifications\Auth;

use Filament\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class AdminResetPasswordNotification extends BaseResetPassword
{
    use BuildsPasswordResetMailMessage;

    public function toMail($notifiable): MailMessage
    {
        return $this->buildPasswordResetMailMessage(
            url: $this->resetUrl($notifiable),
            subject: 'Reset password akun admin SiTA',
            intro: 'Kami menerima permintaan reset password untuk akun admin SiTA Anda.',
            actionLabel: 'Reset password admin',
        );
    }
}
