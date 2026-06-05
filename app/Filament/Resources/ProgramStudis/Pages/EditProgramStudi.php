<?php

namespace App\Filament\Resources\ProgramStudis\Pages;

use App\Enums\AppRole;
use App\Filament\Resources\ProgramStudis\ProgramStudiResource;
use App\Models\ProgramStudi;
use App\Models\User;
use App\Services\KaprodiAssignmentService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\Rules\Password;

class EditProgramStudi extends EditRecord
{
    protected static string $resource = ProgramStudiResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
