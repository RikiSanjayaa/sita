<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Services\SystemAuditLogService;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class EditProfile extends BaseEditProfile
{
    protected bool $passwordWasChanged = false;

    protected bool $profileWasUpdated = false;

    public static function getLabel(): string
    {
        return 'Edit profil';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Edit profil admin';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profil akun')
                    ->description('Perbarui informasi dasar akun admin yang digunakan untuk masuk ke panel.')
                    ->schema([
                        $this->getNameFormComponent()
                            ->label('Nama lengkap'),
                        $this->getEmailFormComponent()
                            ->label('Email login'),
                    ]),
                Section::make('Keamanan akun')
                    ->description('Isi password baru jika ingin mengganti password akun. Konfirmasi password membantu memastikan input sama persis.')
                    ->schema([
                        $this->getPasswordFormComponent()
                            ->label('Password baru')
                            ->helperText(filament()->hasPasswordReset()
                                ? new HtmlString(sprintf(
                                    'Lupa password akun admin? <a href="%s" class="fi-link text-primary-600 hover:text-primary-500">Kirim ulang tautan reset password</a>.',
                                    filament()->getRequestPasswordResetUrl(),
                                ))
                                : null),
                        $this->getPasswordConfirmationFormComponent()
                            ->label('Konfirmasi password baru'),
                        $this->getCurrentPasswordFormComponent()
                            ->label('Password saat ini'),
                    ]),
            ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var User $user */
        $user = $this->getUser();

        unset($data['email']);

        $this->passwordWasChanged = array_key_exists('password', $data);
        $this->profileWasUpdated = ($data['name'] ?? $user->name) !== $user->name;

        return parent::mutateFormDataBeforeSave($data);
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Profil berhasil diperbarui');
    }

    protected function afterSave(): void
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            return;
        }

        $systemAuditLogService = app(SystemAuditLogService::class);

        if ($this->profileWasUpdated) {
            $systemAuditLogService->record(
                user: $user,
                eventType: 'profile_updated_by_user',
                label: 'Profil akun diperbarui',
                description: 'Pengguna memperbarui profil akun admin melalui halaman edit profil.',
                request: request(),
                payload: [
                    'source' => 'admin_profile',
                ],
            );
        }

        if ($this->passwordWasChanged) {
            $systemAuditLogService->record(
                user: $user,
                eventType: 'password_changed_by_user',
                label: 'Password akun diperbarui',
                description: 'Pengguna mengganti password akun admin melalui halaman edit profil.',
                request: request(),
                payload: [
                    'source' => 'admin_profile',
                ],
            );
        }
    }

    protected function getNameFormComponent(): \Filament\Schemas\Components\Component
    {
        return parent::getNameFormComponent()
            ->placeholder('Masukkan nama lengkap');
    }

    protected function getEmailFormComponent(): \Filament\Schemas\Components\Component
    {
        return parent::getEmailFormComponent()
            ->disabled()
            ->dehydrated(false)
            ->helperText('Email login hanya bisa diubah oleh administrator lain melalui manajemen user.')
            ->placeholder('nama@contoh.ac.id');
    }

    protected function getPasswordFormComponent(): \Filament\Schemas\Components\Component
    {
        return parent::getPasswordFormComponent()
            ->placeholder('Masukkan password baru');
    }

    protected function getPasswordConfirmationFormComponent(): \Filament\Schemas\Components\Component
    {
        return parent::getPasswordConfirmationFormComponent()
            ->visible()
            ->required(fn (Get $get): bool => filled($get('password')))
            ->placeholder('Ulangi password baru yang sama');
    }

    protected function getCurrentPasswordFormComponent(): \Filament\Schemas\Components\Component
    {
        return parent::getCurrentPasswordFormComponent()
            ->placeholder('Masukkan password saat ini untuk konfirmasi');
    }
}
