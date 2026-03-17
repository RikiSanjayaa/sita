<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\AppRole;
use App\Filament\Resources\Users\UserResource;
use App\Models\ProgramStudi;
use App\Models\User;
use App\Services\UserExcelImportService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadExcelTemplate')
                ->label('Download Excel Template')
                ->icon('heroicon-m-arrow-down-tray')
                ->url(route('admin.users.import-template', ['format' => 'xlsx'])),
            Action::make('importExcel')
                ->label('Import Excel')
                ->icon('heroicon-m-arrow-up-tray')
                ->color('success')
                ->modalHeading('Import user dari Excel')
                ->modalDescription('Unggah file Excel dari template yang disediakan. Program Studi dipilih dari dropdown import, jadi tidak perlu ditulis di file.')
                ->schema([
                    FileUpload::make('file')
                        ->label('File Excel')
                        ->placeholder('Upload file Excel (.xlsx)')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->rules(['required', 'extensions:xlsx'])
                        ->storeFiles(false)
                        ->required(),
                    ...UserImportOptions::components(),
                ])
                ->action(function (array $data, UserExcelImportService $importService): void {
                    $file = $data['file'] ?? null;

                    if (is_array($file)) {
                        $file = $file[0] ?? null;
                    }

                    if (! $file instanceof TemporaryUploadedFile) {
                        Notification::make()
                            ->title('File Excel belum dipilih')
                            ->body('Silakan pilih file Excel `.xlsx` sebelum memulai import.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $user = Auth::user();

                    if ($user === null) {
                        Notification::make()
                            ->title('Sesi login tidak ditemukan')
                            ->danger()
                            ->send();

                        return;
                    }

                    try {
                        $result = $importService->import($file, $data, $user);
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title('Import Excel gagal')
                            ->body(collect($exception->errors())->flatten()->implode(' '))
                            ->danger()
                            ->send();

                        return;
                    }

                    $body = sprintf(
                        'Diproses %d baris, berhasil %d, gagal %d.',
                        $result['processed'],
                        $result['imported'],
                        $result['failed'],
                    );

                    if ($result['failed'] > 0) {
                        $preview = collect($result['failures'])->take(3)->implode(' ');

                        Notification::make()
                            ->title('Import Excel selesai dengan catatan')
                            ->body(trim($body.' '.$preview))
                            ->warning()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Import Excel berhasil')
                        ->body($body)
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'mahasiswa' => Tab::make('Mahasiswa')
                ->modifyQueryUsing(fn(Builder $query): Builder => $query->whereHas('roles', static fn(Builder $roleQuery): Builder => $roleQuery->where('name', 'mahasiswa'))),
            'dosen' => Tab::make('Dosen')
                ->modifyQueryUsing(fn(Builder $query): Builder => $query->whereHas('roles', static fn(Builder $roleQuery): Builder => $roleQuery->where('name', 'dosen'))),
            'admin' => Tab::make('Admin')
                ->modifyQueryUsing(fn(Builder $query): Builder => $query->whereHas('roles', static fn(Builder $roleQuery): Builder => $roleQuery->where('name', 'admin'))),
        ];
    }
}

class UserImportOptions
{
    public static function components(): array
    {
        return [
            Select::make('import_type')
                ->label('Tipe Import')
                ->options(function (): array {
                    $options = [
                        'mahasiswa' => 'Mahasiswa',
                        'dosen' => 'Dosen',
                        'admin' => 'Admin',
                    ];

                    /** @var User|null $user */
                    $user = Auth::user();

                    if (! $user?->hasRole(AppRole::SuperAdmin)) {
                        unset($options[AppRole::Admin->value]);
                    }

                    return $options;
                })
                ->required()
                ->native(false),
            Select::make('program_studi_id')
                ->label('Program Studi')
                ->options(fn(): array => ProgramStudi::query()->orderBy('name')->pluck('name', 'id')->all())
                ->required()
                ->searchable()
                ->default(function (): ?int {
                    /** @var User|null $user */
                    $user = Auth::user();

                    return $user?->adminProgramStudiId();
                })
                ->disabled(function (): bool {
                    /** @var User|null $user */
                    $user = Auth::user();

                    return ! $user?->hasRole(AppRole::SuperAdmin);
                })
                ->dehydrated()
                ->native(false),
        ];
    }
}
