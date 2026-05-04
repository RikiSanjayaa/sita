<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends BaseResetPassword
{
    use BuildsPasswordResetMailMessage;

    public function toMail($notifiable): MailMessage
    {
        return $this->buildPasswordResetMailMessage(
            url: $this->resetUrl($notifiable),
            subject: 'Reset password akun SiTA',
            intro: 'Kami menerima permintaan reset password untuk akun SiTA Anda.',
        );
    }
}
