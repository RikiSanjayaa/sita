<?php

namespace App\Filament\Imports;

use App\Enums\AppRole;
use App\Models\ProgramStudi;
use App\Models\User;
use App\Services\UserProvisioningService;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;

class UserImporter extends Importer
{
    protected static ?string $model = User::class;

    public static function getOptionsFormComponents(): array
    {
        return [
            Select::make('import_type')
                ->label('Import Type')
                ->options(fn() => collect([
                    AppRole::Mahasiswa->value => 'Mahasiswa',
                    AppRole::Dosen->value => 'Dosen',
                    AppRole::Admin->value => 'Admin',
                ])->when(
                    ! self::isSuperAdminUser(),
                    fn($options) => $options->except(AppRole::Admin->value)
                )->toArray())
                ->required()
                ->native(false),
            Select::make('program_studi_id')
                ->label('Program Studi')
                ->options(ProgramStudi::all()->pluck('name', 'id'))
                ->required()
                ->searchable()
                ->default(fn() => self::currentUserAdminProgramStudiId())
                ->disabled(fn() => ! self::isSuperAdminUser())
                ->dehydrated() // Ensure it's sent even if disabled
                ->native(false),
        ];
    }

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->exampleHeader('nama')
                ->examples(['Muhammad Akbar'])
                ->guess(['name', 'nama', 'nama_lengkap'])
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('email')
                ->exampleHeader('email')
                ->examples(['mahasiswa@sita.test'])
                ->guess(['email', 'email_utama'])
                ->requiredMapping()
                ->rules(['required', 'email', 'max:255']),
            ImportColumn::make('phone_number')
                ->exampleHeader('no_hp')
                ->examples(['081234567890'])
                ->guess(['phone_number', 'phone', 'no_hp', 'nomor_hp', 'whatsapp', 'telepon'])
                ->fillRecordUsing(fn(): null => null)
                ->rules(['nullable', 'string', 'max:30']),
            ImportColumn::make('role')
                ->exampleHeader('role')
                ->examples(['mahasiswa'])
                ->guess(['role'])
                ->fillRecordUsing(fn(): null => null)
                ->rules(['nullable', 'in:mahasiswa,dosen,admin']),
            ImportColumn::make('password')
                ->exampleHeader('password')
                ->examples([''])
                ->guess(['password', 'initial_password'])
                ->sensitive()
                ->rules(['required', 'string', 'min:8'])
                ->fillRecordUsing(function (User $record, ?string $state): void {
                    if (filled($state)) {
                        data_set($record, 'password', $state);
                    }
                }),
            ImportColumn::make('nim')
                ->exampleHeader('nim')
                ->examples(['2210510001'])
                ->guess(['nim'])
                ->fillRecordUsing(fn(): null => null)
                ->rules(['nullable', 'required_if:role,mahasiswa', 'string', 'max:255']),
            ImportColumn::make('angkatan')
                ->exampleHeader('angkatan')
                ->examples(['2022'])
                ->guess(['angkatan'])
                ->integer()
                ->fillRecordUsing(fn(): null => null)
                ->rules(['nullable', 'required_if:role,mahasiswa', 'integer', 'between:1990,2100']),
            ImportColumn::make('degree_level')
                ->label('Jenjang')
                ->exampleHeader('jenjang')
                ->examples(['s1'])
                ->guess(['degree_level', 'jenjang'])
                ->fillRecordUsing(fn(): null => null)
                ->rules(['nullable', 'in:d3,s1,s2']),
            ImportColumn::make('concentration')
                ->exampleHeader('konsentrasi')
                ->examples(['Jaringan'])
                ->guess(['concentration', 'konsentrasi'])
                ->fillRecordUsing(fn(): null => null)
                ->rules(['nullable', 'required_if:role,mahasiswa', 'string', 'max:255']),
            ImportColumn::make('academic_assignments')
                ->exampleHeader('penempatan_dosen')
                ->examples(['Ilmu Komputer:Jaringan|Teknologi Informasi:Data'])
                ->guess(['academic_assignments', 'penempatan_dosen', 'penempatan'])
                ->fillRecordUsing(fn(): null => null)
                ->rules(['nullable', 'string', 'max:1000']),

            ImportColumn::make('nik')
                ->exampleHeader('nik')
                ->examples(['7301010101010001'])
                ->guess(['nik', 'no_ktp', 'nidn'])
                ->fillRecordUsing(fn(): null => null)
                ->rules(['nullable', 'required_if:role,dosen', 'string', 'max:255']),
            ImportColumn::make('supervision_quota')
                ->exampleHeader('kuota_bimbingan')
                ->examples(['12'])
                ->guess(['supervision_quota', 'quota', 'kuota', 'kuota_bimbingan'])
                ->integer()
                ->fillRecordUsing(fn(): null => null)
                ->rules(['nullable', 'integer', 'min:1']),
            ImportColumn::make('expertise_fields')
                ->label('Bidang Keilmuan')
                ->exampleHeader('bidang_keilmuan')
                ->examples(['Kecerdasan Buatan|Pembelajaran Mesin'])
                ->guess(['expertise_fields', 'bidang_keilmuan', 'keahlian'])
                ->fillRecordUsing(fn(): null => null)
                ->rules(['nullable', 'string', 'max:2000']),

        ];
    }

    public function resolveRecord(): User
    {
        return User::query()->firstOrNew([
            'email' => (string) ($this->data['email'] ?? ''),
        ]);
    }

    protected function beforeValidate(): void
    {
        $importType = (string) ($this->options['import_type'] ?? '');
        $rowRole = (string) ($this->data['role'] ?? '');

        if ($rowRole === '' && in_array($importType, AppRole::uiValues(), true)) {
            $this->data['role'] = $importType;
        }
    }

    protected function beforeSave(): void
    {
        $role = AppRole::tryFrom((string) ($this->data['role'] ?? ''))?->value;

        if ($role !== null) {
            data_set($this->record, 'last_active_role', $role);
        }
    }

    protected function afterSave(): void
    {
        /** @var User $user */
        $user = $this->record;

        $data = $this->data;
        $data['prodi'] = $this->options['program_studi_id'] ?? null;
        $data['expertise_field_ids'] = $data['expertise_fields'] ?? '';

        if (($data['role'] ?? null) === AppRole::Dosen->value) {
            $data['academic_assignments'] = $this->dosenAcademicAssignments(
                rawAssignments: (string) ($data['academic_assignments'] ?? ''),
                fallbackProgramStudiId: (int) ($this->options['program_studi_id'] ?? 0),
                fallbackConcentration: (string) ($data['concentration'] ?? ''),
            );
        }

        app(UserProvisioningService::class)->syncRoleAndProfiles(
            $user,
            $data,
        );
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your user import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }

    private static function isSuperAdminUser(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasRole(AppRole::SuperAdmin) ?? false;
    }

    private static function currentUserAdminProgramStudiId(): ?int
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->adminProgramStudiId();
    }

    /**
     * @return array<int, array{program_studi_id: int, concentration: string, is_primary: bool, is_active: bool}>
     */
    private function dosenAcademicAssignments(string $rawAssignments, int $fallbackProgramStudiId, string $fallbackConcentration): array
    {
        if (trim($rawAssignments) === '') {
            return [
                [
                    'program_studi_id' => $fallbackProgramStudiId,
                    'concentration' => $fallbackConcentration,
                    'is_primary' => true,
                    'is_active' => true,
                ],
            ];
        }

        $programStudiByName = ProgramStudi::query()
            ->get()
            ->flatMap(fn(ProgramStudi $programStudi): array => [
                $this->normalizeLookupKey($programStudi->name) => $programStudi,
                $this->normalizeLookupKey($programStudi->slug) => $programStudi,
            ]);

        return collect(preg_split('/[|;]/', $rawAssignments) ?: [])
            ->map(fn(string $entry): string => trim($entry))
            ->filter()
            ->values()
            ->map(function (string $entry, int $index) use ($programStudiByName): array {
                [$programStudiName, $concentration] = array_pad(array_map('trim', explode(':', $entry, 2)), 2, '');
                $programStudi = $programStudiByName->get($this->normalizeLookupKey($programStudiName));

                if (! $programStudi instanceof ProgramStudi) {
                    throw ValidationException::withMessages([
                        'academic_assignments' => ["Program studi '{$programStudiName}' tidak ditemukan."],
                    ]);
                }

                if ($concentration === '') {
                    throw ValidationException::withMessages([
                        'academic_assignments' => ["Konsentrasi wajib ditulis untuk '{$programStudiName}'."],
                    ]);
                }

                return [
                    'program_studi_id' => (int) $programStudi->id,
                    'concentration' => $concentration,
                    'is_primary' => $index === 0,
                    'is_active' => true,
                ];
            })
            ->all();
    }

    private function normalizeLookupKey(string $value): string
    {
        return str($value)->lower()->squish()->toString();
    }
}
