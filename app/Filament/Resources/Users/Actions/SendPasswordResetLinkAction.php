<?php

namespace App\Filament\Resources\Users\Actions;

use App\Models\User;
use App\Services\ManualPasswordResetService;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class SendPasswordResetLinkAction
{
    public static function make(?Closure $recordResolver = null): Action
    {
        return Action::make('sendPasswordResetLink')
            ->label('Kirim link reset')
            ->icon('heroicon-m-key')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Kirim link reset password')
            ->modalDescription(function (?User $record) use ($recordResolver): string {
                $target = self::resolveRecord($record, $recordResolver);

                return $target === null
                    ? 'Tautan reset password akan dikirim ke email akun ini.'
                    : "Tautan reset password akan dikirim ke {$target->email}.";
            })
            ->visible(function (?User $record, ManualPasswordResetService $passwordResetService) use ($recordResolver): bool {
                $target = self::resolveRecord($record, $recordResolver);
                $actor = Auth::user();

                return $actor instanceof User
                    && $target instanceof User
                    && $passwordResetService->canSendResetLink($actor, $target);
            })
            ->action(function (?User $record, ManualPasswordResetService $passwordResetService) use ($recordResolver): void {
                $target = self::resolveRecord($record, $recordResolver);
                $actor = Auth::user();

                if (! $actor instanceof User || ! $target instanceof User) {
                    return;
                }

                $passwordResetService->sendResetLink($target, $actor, request());

                Notification::make()
                    ->title('Link reset password terkirim')
                    ->body("Tautan reset password berhasil dikirim ke {$target->email}.")
                    ->success()
                    ->send();
            });
    }

    private static function resolveRecord(?User $record, ?Closure $recordResolver): ?User
    {
        if ($record instanceof User) {
            return $record;
        }

        if ($recordResolver === null) {
            return null;
        }

        $resolved = $recordResolver();

        return $resolved instanceof User ? $resolved : null;
    }
}
