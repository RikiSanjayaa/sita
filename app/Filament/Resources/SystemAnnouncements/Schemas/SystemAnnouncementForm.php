<?php

namespace App\Filament\Resources\SystemAnnouncements\Schemas;

use App\Enums\AppRole;
use App\Models\Faculty;
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
use Filament\Schemas\Components\Utilities\Get;
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
                        ->hint('Tautan eksternal ditulis di sini')
                        ->hintIcon(
                            'heroicon-m-information-circle',
                            'Semua URL http://, https://, dan www. dalam isi pengumuman akan otomatis menjadi tautan yang dapat diklik pada notifikasi.',
                        )
                        ->helperText('Untuk mengarahkan pengguna ke situs lain, tulis URL lengkap langsung di isi pengumuman.')
                        ->columnSpanFull(),
                    TextInput::make('action_url')
                        ->label('URL Tujuan')
                        ->placeholder('/mahasiswa/dashboard')
                        ->hint('Khusus halaman internal SiTA')
                        ->hintIcon(
                            'heroicon-m-question-mark-circle',
                            'Gunakan path internal yang diawali satu garis miring, misalnya /dashboard. Jangan masukkan http://, https://, atau domain situs lain pada field ini.',
                        )
                        ->rules(['nullable', 'regex:/^\/(?!\/)/'])
                        ->maxLength(255)
                        ->helperText('Opsional. Aksi utama notifikasi akan membuka halaman internal ini. Untuk URL eksternal, tulis URL lengkap di Isi Pengumuman.')
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
                    Select::make('target_scope')
                        ->label('Cakupan Akademik')
                        ->options([
                            SystemAnnouncement::TARGET_ALL => 'Semua Fakultas & Program Studi',
                            SystemAnnouncement::TARGET_FACULTIES => 'Fakultas Tertentu',
                            SystemAnnouncement::TARGET_PROGRAMS => 'Program Studi Tertentu',
                        ])
                        ->default(SystemAnnouncement::TARGET_ALL)
                        ->required()
                        ->live()
                        ->native(false)
                        ->hintIcon(
                            'heroicon-m-information-circle',
                            'Pilih salah satu cara penargetan. Fakultas tertentu mencakup seluruh prodi di fakultas terpilih; program studi tertentu hanya mencakup prodi yang dipilih.',
                        )
                        ->visible(fn(): bool => self::isSuperAdmin()),
                    Select::make('target_faculty_ids')
                        ->label('Fakultas Tujuan')
                        ->options(fn(): array => Faculty::query()
                            ->where('is_active', true)
                            ->where('is_placeholder', false)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->multiple()
                        ->required(fn(Get $get): bool => $get('target_scope') === SystemAnnouncement::TARGET_FACULTIES)
                        ->visible(fn(Get $get): bool => self::isSuperAdmin() && $get('target_scope') === SystemAnnouncement::TARGET_FACULTIES)
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->helperText('Seluruh program studi di fakultas yang dipilih akan menerima pengumuman sesuai target role.')
                        ->columnSpanFull(),
                    Select::make('target_program_studi_ids')
                        ->label('Program Studi Tujuan')
                        ->options(fn(): array => self::programStudiOptionsByFaculty())
                        ->multiple()
                        ->required(fn(Get $get): bool => $get('target_scope') === SystemAnnouncement::TARGET_PROGRAMS)
                        ->visible(fn(Get $get): bool => self::isSuperAdmin() && $get('target_scope') === SystemAnnouncement::TARGET_PROGRAMS)
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->helperText('Pilihan dikelompokkan berdasarkan fakultas. Hanya program studi yang dipilih yang menjadi target.')
                        ->columnSpanFull(),
                    Select::make('program_studi_id')
                        ->label('Program Studi Tujuan')
                        ->options(fn(): array => ProgramStudi::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->default(fn(): ?int => self::currentUser()?->adminProgramStudiId())
                        ->disabled()
                        ->dehydrated()
                        ->native(false)
                        ->helperText('Admin program studi hanya dapat mengirim pengumuman ke program studinya sendiri.')
                        ->visible(fn(): bool => self::currentUser()?->hasRole(AppRole::Admin) ?? false)
                        ->columnSpanFull(),
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
            AppRole::Kaprodi->value => 'Kaprodi',
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

    private static function isSuperAdmin(): bool
    {
        return self::currentUser()?->hasRole(AppRole::SuperAdmin) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private static function programStudiOptionsByFaculty(): array
    {
        return Faculty::query()
            ->where('is_active', true)
            ->where('is_placeholder', false)
            ->with(['programStudis' => fn($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn(Faculty $faculty): array => [
                $faculty->name => $faculty->programStudis->pluck('name', 'id')->all(),
            ])
            ->all();
    }
}
