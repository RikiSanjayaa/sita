<?php

namespace App\Filament\Resources\ProgramStudis\Pages;

use App\Enums\AppRole;
use App\Filament\Resources\ProgramStudis\ProgramStudiResource;
use App\Models\KaprodiAssignment;
use App\Models\ProgramStudi;
use App\Models\User;
use App\Policies\SidangPaperPolicy;
use App\Services\KaprodiAssignmentService;
use App\Support\SidangSettings;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class EditProgramStudi extends EditRecord
{
    protected static string $resource = ProgramStudiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sidangSettings')
                ->visible(fn (): bool => app(SidangPaperPolicy::class)->manage(Auth::user(), (int) $this->record->id))
                ->label('Atur Dokumen dan Nilai Sidang')
                ->icon('heroicon-m-document-text')
                ->fillForm(fn (): array => ['sidang_settings' => SidangSettings::forProgram($this->record)])
                ->schema([
                    TextInput::make('sidang_settings.heading')->label('Nama institusi pada kop')->required()->maxLength(100),
                    TextInput::make('sidang_settings.city')->label('Kota pengesahan')->required()->maxLength(80),
                    TextInput::make('sidang_settings.dean_name')->label('Nama dekan')->maxLength(120),
                    TextInput::make('sidang_settings.dean_nik')->label('NIK dekan')->maxLength(30),
                    TextInput::make('sidang_settings.staff_name')->label('Nama staf prodi')->maxLength(120),
                    TextInput::make('sidang_settings.staff_nik')->label('NIK staf prodi')->maxLength(30),
                    TextInput::make('sidang_settings.examiner_1')->label('Bobot Penguji 1 (%)')->integer()->minValue(1)->maxValue(100)->required(),
                    TextInput::make('sidang_settings.examiner_2')->label('Bobot Penguji 2 (%)')->integer()->minValue(1)->maxValue(100)->required(),
                    TextInput::make('sidang_settings.supervisor')->label('Bobot Pembimbing Penilai (%)')->integer()->minValue(1)->maxValue(100)->required(),
                    ...array_map(
                        fn (string $key, string $grade): TextInput => TextInput::make('sidang_settings.'.$key)->label('Nilai minimum '.$grade)->integer()->minValue(1)->maxValue(100)->required(),
                        SidangSettings::GRADE_KEYS,
                        SidangSettings::GRADES,
                    ),
                    Textarea::make('sidang_settings.examiner_rubric')->label('Komponen nilai penguji')->helperText('Satu baris per komponen dengan format Komponen | Bobot.')->rows(7)->maxLength(1500)->required(),
                    Textarea::make('sidang_settings.supervisor_rubric')->label('Komponen nilai pembimbing')->helperText('Satu baris per komponen dengan format Komponen | Bobot.')->rows(5)->maxLength(1500)->required(),
                ])
                ->columns(2)
                ->action(function (array $data): void {
                    abort_unless(app(SidangPaperPolicy::class)->manage(Auth::user(), (int) $this->record->id), 403);
                    $settings = $data['sidang_settings'];
                    SidangSettings::validate($settings);
                    $this->record->update(['sidang_settings' => $settings]);
                    Notification::make()->title('Pengaturan sidang disimpan')->success()->send();
                }),
            Action::make('manage_kaprodi')
                ->label('Atur Kaprodi')
                ->icon('heroicon-m-user-group')
                ->modalHeading(fn(): string => 'Atur Kaprodi '.$this->record->name)
                ->modalSubmitActionLabel('Simpan Assignment')
                ->fillForm(fn(): array => [
                    'assignments' => $this->record
                        ->kaprodiAssignments()
                        ->orderByDesc('is_primary')
                        ->with('user')
                        ->get()
                        ->map(fn($assignment): array => [
                            'user_id' => $assignment->user_id,
                            'is_primary' => $assignment->is_primary,
                            'capabilities' => collect(KaprodiAssignment::normalizeCapabilities($assignment->capabilities))
                                ->filter()
                                ->keys()
                                ->all(),
                        ])
                        ->all(),
                ])
                ->form([
                    Repeater::make('assignments')
                        ->label('Akun Kaprodi')
                        ->schema([
                            Select::make('user_id')
                                ->label('User')
                                ->options(fn(): array => $this->kaprodiUserOptions($this->record))
                                ->createOptionForm([
                                    TextInput::make('name')
                                        ->label('Nama')
                                        ->required()
                                        ->maxLength(255),
                                    TextInput::make('email')
                                        ->label('Email')
                                        ->email()
                                        ->required()
                                        ->unique(User::class, 'email')
                                        ->maxLength(255),
                                    TextInput::make('phone_number')
                                        ->label('Nomor HP')
                                        ->tel()
                                        ->maxLength(30)
                                        ->placeholder('08xxxxxxxxxx'),
                                    TextInput::make('password')
                                        ->password()
                                        ->revealable()
                                        ->required()
                                        ->rule(Password::default())
                                        ->maxLength(255),
                                ])
                                ->createOptionUsing(function (array $data): int {
                                    $user = User::query()->create([
                                        'name' => $data['name'],
                                        'email' => $data['email'],
                                        'phone_number' => $data['phone_number'] ?? null,
                                        'password' => $data['password'],
                                        'last_active_role' => AppRole::Kaprodi->value,
                                    ]);

                                    return (int) $user->getKey();
                                })
                                ->searchable()
                                ->preload()
                                ->required()
                                ->native(false),
                            Toggle::make('is_primary')
                                ->label('Kaprodi Utama')
                                ->helperText('Harus tepat satu akun utama.'),
                            CheckboxList::make('capabilities')
                                ->label('Kapabilitas')
                                ->options(KaprodiAssignment::capabilityLabels())
                                ->default(array_keys(KaprodiAssignment::defaultCapabilities()))
                                ->columns(2)
                                ->bulkToggleable()
                                ->helperText('Superadmin dapat membatasi aksi operasional kaprodi.')
                                ->columnSpanFull(),
                        ])
                        ->columns(2)
                        ->minItems(1)
                        ->maxItems(3)
                        ->reorderable(false)
                        ->addActionLabel('Tambah Kaprodi')
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    app(KaprodiAssignmentService::class)->syncForProgramStudi(
                        $this->record,
                        $data['assignments'] ?? [],
                    );

                    Notification::make()
                        ->title('Assignment kaprodi berhasil disimpan')
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function kaprodiUserOptions(ProgramStudi $programStudi): array
    {
        return User::query()
            ->whereDoesntHave('roles', static function ($query): void {
                $query->whereIn('name', [AppRole::Admin->value, AppRole::SuperAdmin->value]);
            })
            ->where(function ($query) use ($programStudi): void {
                $query->whereDoesntHave('kaprodiAssignment')
                    ->orWhereHas('kaprodiAssignment', function ($assignmentQuery) use ($programStudi): void {
                        $assignmentQuery->where('program_studi_id', $programStudi->getKey());
                    });
            })
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn(User $user): array => [
                $user->id => sprintf('%s - %s', $user->name, $user->email),
            ])
            ->all();
    }
}
