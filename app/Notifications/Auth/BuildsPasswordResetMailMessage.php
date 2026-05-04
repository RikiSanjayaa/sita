<?php

namespace App\Notifications\Auth;

use Illuminate\Notifications\Messages\MailMessage;

trait BuildsPasswordResetMailMessage
{
    protected function buildPasswordResetMailMessage(
        string $url,
        string $subject,
        string $intro,
        string $actionLabel = 'Reset password',
    ): MailMessage {
        $mailMessage = (new MailMessage)
            ->subject($subject)
            ->line($intro)
            ->action($actionLabel, $url)
            ->line(sprintf(
                'Tautan reset password ini akan kedaluwarsa dalam %d menit.',
                (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire')
            ))
            ->line('Jika Anda tidak meminta reset password, abaikan email ini.');

        $mailer = $this->resolvePasswordResetMailer();
        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name');

        if ($mailer !== null) {
            $mailMessage->mailer($mailer);
        }

        if (is_string($fromAddress) && $fromAddress !== '') {
            $mailMessage->from($fromAddress, is_string($fromName) && $fromName !== '' ? $fromName : null);
        }

        return $mailMessage;
    }

    protected function resolvePasswordResetMailer(): ?string
    {
        if (filled(config('services.resend.key'))) {
            return 'resend';
        }

        $defaultMailer = config('mail.default');

        return is_string($defaultMailer) && $defaultMailer !== ''
            ? $defaultMailer
            : null;
    }
}
