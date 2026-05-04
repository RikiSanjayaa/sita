<?php

namespace App\Filament\Resources\SystemAnnouncements\Schemas;

use App\Enums\AppRole;
use App\Models\ProgramStudi;
use App\Models\SystemAnnouncement;
use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class SystemAnnouncementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Konten Pengumuman')
                ->columns(2)
                ->schema([
                    TextInput::make('title')
                        ->label('Judul')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Textarea::make('body')
                        ->label('Isi Pengumuman')
                        ->required()
                        ->rows(5)
                        ->columnSpanFull(),
                    TextInput::make('action_url')
                        ->label('URL Tujuan')
                        ->placeholder('/mahasiswa/dashboard')
                        ->maxLength(255)
                        ->helperText('Opsional. Jika diisi, notifikasi akan membuka halaman ini saat diklik.')
                        ->columnSpanFull(),
                    Select::make('status')
                        ->label('Status')
                        ->options([
                            SystemAnnouncement::STATUS_DRAFT => 'Draft',
                            SystemAnnouncement::STATUS_PUBLISHED => 'Publish sekarang',
                        ])
                        ->required()
                        ->default(SystemAnnouncement::STATUS_DRAFT)
                        ->native(false)
                        ->disabled(fn(?SystemAnnouncement $record): bool => $record?->notified_at !== null)
                        ->helperText('Pengumuman yang sudah pernah dipublish tidak akan dikirim ulang otomatis saat diedit.'),
                    DateTimePicker::make('expires_at')
                        ->label('Berlaku Sampai')
                        ->native(false)
                        ->seconds(false),
                ]),
            Section::make('Target Penerima')
                ->columns(2)
                ->schema([
                    CheckboxList::make('target_roles')
                        ->label('Target Role')
                        ->options(self::roleOptions())
                        ->required()
                        ->columns(2)
                        ->columnSpanFull(),
                    Select::make('program_studi_id')
                        ->label('Program Studi')
                        ->options(fn(): array => ProgramStudi::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->placeholder('Semua Program Studi')
                        ->default(fn(): ?int => self::currentUser()?->adminProgramStudiId())
                        ->disabled(fn(): bool => self::currentUser()?->hasRole(AppRole::Admin) ?? false)
                        ->helperText('Kosongkan untuk pengumuman lintas program studi. Admin prodi otomatis terkunci ke prodinya.'),
                    Placeholder::make('publish_state')
                        ->label('Status Pengiriman')
                        ->content(fn(?SystemAnnouncement $record): string => $record?->notified_at !== null
                            ? 'Sudah dikirim ke penerima yang cocok.'
                            : 'Belum pernah dikirim.'),
                ]),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private static function roleOptions(): array
    {
        $options = [
            AppRole::Mahasiswa->value => 'Mahasiswa',
            AppRole::Dosen->value => 'Dosen',
            AppRole::Admin->value => 'Admin',
            AppRole::SuperAdmin->value => 'Super Admin',
        ];

        if (self::currentUser()?->hasRole(AppRole::Admin) ?? false) {
            unset($options[AppRole::SuperAdmin->value]);
        }

        return $options;
    }

    private static function currentUser(): ?User
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user;
    }
}
