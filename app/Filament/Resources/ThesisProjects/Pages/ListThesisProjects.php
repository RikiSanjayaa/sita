<?php

namespace App\Filament\Resources\ThesisProjects\Pages;

use App\Filament\Resources\ThesisProjects\Actions\ManageSidangReportPeriod;
use App\Filament\Resources\ThesisProjects\ThesisProjectResource;
use App\Models\SidangReportPeriod;
use App\Services\SidangReportPeriodService;
use App\Services\ThesisProjectImportService;
use App\Support\AcademicTerminology;
use App\Support\Filament\SidangReportDatePicker;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ListThesisProjects extends ListRecords
{
    protected static string $resource = ThesisProjectResource::class;

    public const SIDANG_PAPER_TAB = 'berkas-sidang';

    public const SIDANG_PAPER_STATUSES = ['scheduled', 'awaiting_finalization', 'completed'];

    private const MAX_SHOWN_IMPORT_FAILURES = 25;

    protected function getHeaderActions(): array
    {
        $finalWork = AcademicTerminology::neutral()['finalWork'];
        $actor = Auth::user();
        $programId = $actor?->adminProgramStudiId();
        $superAdmin = $actor?->hasRole('super_admin') ?? false;
        $periodService = app(SidangReportPeriodService::class);

        return [
            ...($actor !== null && ($superAdmin || $programId !== null)
                ? [ManageSidangReportPeriod::make($actor)->visible(fn (): bool => $this->activeTab === self::SIDANG_PAPER_TAB)] : []),
            Action::make('exportSidangReport')
                ->label('Ekspor Rekap Nilai')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('gray')
                ->modalHeading('Rekap Nilai Sidang')
                ->modalDescription('Memuat sidang lulus yang sudah final dengan tiga nilai lengkap, sesuai filter tabel dan tanggal sidang WITA.')
                ->modalSubmitActionLabel('Unduh Excel')
                ->modalCancelActionLabel('Batal')
                ->modalContentFooter(fn () => view('filament.thesis-projects.sidang-report-download'))
                ->schema([
                    Select::make('period_id')->label('Periode rekap')->placeholder('Rentang tanggal manual')
                        ->options(fn (): array => $periodService->periods($programId)->mapWithKeys(fn (SidangReportPeriod $period): array => [
                            $period->id => $period->label().' ('.$period->starts_on->format('d/m/Y').' - '.$period->ends_on->format('d/m/Y').')'
                                .($superAdmin ? ' - '.$period->programStudi->name : ''),
                        ])->all())
                        ->default(fn (): ?int => $programId === null ? null : $periodService->activeId($periodService->periods($programId)))
                        ->live(),
                    SidangReportDatePicker::make('from')->label('Dari tanggal')->visible(fn (Get $get): bool => blank($get('period_id')))->required()->live(),
                    SidangReportDatePicker::make('until')->label('Sampai tanggal')->visible(fn (Get $get): bool => blank($get('period_id')))
                        ->defaultFocusedDate(fn (Get $get): ?string => $get('from'))->required()->afterOrEqual('from'),
                    TextInput::make('period')->label('Nama periode')->visible(fn (Get $get): bool => blank($get('period_id')))
                        ->maxLength(80)->helperText('Contoh: Ganjil 2026/2027'),
                ])
                ->action(function (array $data, Action $action): void {
                    $query = $this->getTableQueryForExport()->reorder()->select('thesis_projects.id')->toBase();
                    $token = (string) Str::uuid();
                    Cache::store('database')->put('sidang-export:'.$token, [
                        'user' => Auth::id(), 'sql' => $query->toSql(), 'bindings' => $query->getBindings(),
                    ], now()->addMinutes(5));
                    $this->dispatch('sidang-report-download', url: route('sidang.report', [...$data, 'selection' => $token]));
                    $action->halt();
                }),
            Action::make('downloadThesisImportTemplate')
                ->label('Download Template')
                ->icon('heroicon-m-arrow-down-tray')
                ->url(route('admin.thesis-projects.import-template')),
            Action::make('importThesisProjects')
                ->label('Import '.$finalWork)
                ->icon('heroicon-m-arrow-up-tray')
                ->color('success')
                ->modalHeading('Import '.$finalWork.' yang sudah berjalan')
                ->modalDescription('Untuk mahasiswa yang '.mb_strtolower($finalWork).'nya sudah berjalan sebelum memakai SITA. Mahasiswa dan dosen harus sudah diimpor sebagai user. Jika ada satu baris yang salah, tidak ada data yang disimpan.')
                ->schema([
                    FileUpload::make('file')
                        ->label('File Excel')
                        ->placeholder('Upload file Excel (.xlsx)')
                        ->helperText('Kolom: nim, judul, pembimbing_1_nik, pembimbing_2_nik, tahap, tanggal_mulai_masa_aktif (opsional). Gunakan template resmi.')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->rules(['required', 'extensions:xlsx'])
                        ->storeFiles(false)
                        ->required(),
                    Checkbox::make('notify')
                        ->label('Kirim notifikasi ke mahasiswa dan dosen pembimbing')
                        ->default(false),
                ])
                ->action(function (array $data, ThesisProjectImportService $importService): void {
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

                    if ($result['failed'] > 0) {
                        $shown = collect($result['failures'])->take(self::MAX_SHOWN_IMPORT_FAILURES);
                        $hidden = $result['failed'] - $shown->count();
                        $lines = $shown->map(fn(string $failure): string => e($failure));

                        if ($hidden > 0) {
                            $lines->push(e("Dan {$hidden} kesalahan lain."));
                        }

                        Notification::make()
                            ->title('Import Excel dibatalkan, tidak ada data yang disimpan')
                            ->body(new HtmlString(
                                e(sprintf('Diproses %d baris, bermasalah %d.', $result['processed'], $result['failed'])).'<br>'.$lines->implode('<br>'),
                            ))
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Import Excel berhasil')
                        ->body(sprintf('Diproses %d baris, berhasil %d.', $result['processed'], $result['imported']))
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua '.AcademicTerminology::neutral()['finalWork']),
            self::SIDANG_PAPER_TAB => Tab::make('Berkas '.AcademicTerminology::neutral()['finalExamShort'])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('state', '!=', 'cancelled')
                    ->whereHas('latestSidangDefense', fn (Builder $defense): Builder => $defense
                        ->whereIn('status', self::SIDANG_PAPER_STATUSES)
                        ->whereNotNull('scheduled_for'))),
            'perlu-sempro' => Tab::make('Perlu '.AcademicTerminology::neutral()['proposalExamShort'])
                ->modifyQueryUsing(fn(Builder $query): Builder => $query
                    ->where('state', 'active')
                    ->whereIn('phase', ['title_review', 'sempro'])
                    ->whereDoesntHave('semproDefenses', fn(Builder $defenseQuery): Builder => $defenseQuery
                        ->whereIn('status', ['scheduled', 'completed']))),
            'perlu-pembimbing' => Tab::make('Perlu Pembimbing')
                ->modifyQueryUsing(fn(Builder $query): Builder => $query
                    ->where('state', 'active')
                    ->whereIn('phase', ['research', 'sidang'])
                    ->has('activeSupervisorAssignments', '<', 2)),
            'perlu-sidang' => Tab::make('Perlu '.AcademicTerminology::neutral()['finalExamShort'])
                ->modifyQueryUsing(fn(Builder $query): Builder => $query
                    ->where('state', 'active')
                    ->whereIn('phase', ['research', 'sidang'])
                    ->whereDoesntHave('sidangDefenses', fn(Builder $defenseQuery): Builder => $defenseQuery
                        ->whereIn('status', ['scheduled', 'completed']))),
            'revisi-terbuka' => Tab::make('Revisi Terbuka')
                ->modifyQueryUsing(fn(Builder $query): Builder => $query
                    ->whereHas('revisions', fn(Builder $revisionQuery): Builder => $revisionQuery
                        ->whereIn('status', ['open', 'submitted']))),
        ];
    }
}
