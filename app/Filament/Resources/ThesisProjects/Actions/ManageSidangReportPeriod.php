<?php

namespace App\Filament\Resources\ThesisProjects\Actions;

use App\Models\ProgramStudi;
use App\Models\SidangReportPeriod;
use App\Models\User;
use App\Services\SidangReportPeriodService;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Validation\ValidationException;

final class ManageSidangReportPeriod
{
    public static function make(User $actor): Action
    {
        $superAdmin = $actor->hasRole('super_admin');
        $service = app(SidangReportPeriodService::class);
        $programId = fn (Get $get): ?int => $superAdmin ? ((int) $get('program_studi_id') ?: null) : $actor->adminProgramStudiId();
        $reset = static function (Set $set): void {
            foreach (['period_id', 'starts_on', 'ends_on', 'semester', 'start_year'] as $field) {
                $set($field, null);
            }
        };

        return Action::make('manageSidangReportPeriod')
            ->label('Atur Periode Rekap')
            ->icon('heroicon-m-calendar-days')
            ->color('gray')
            ->modalDescription('Simpan rentang tanggal sidang WITA agar dapat dipilih lagi saat ekspor.')
            ->modalSubmitActionLabel('Simpan Periode')
            ->schema([
                Select::make('program_studi_id')->label('Program Studi')
                    ->options(fn (): array => ProgramStudi::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->visible($superAdmin)->required($superAdmin)->live()->afterStateUpdated($reset),
                Select::make('period_id')->label('Periode yang diubah')
                    ->placeholder('Tambah periode baru')
                    ->options(fn (Get $get): array => $programId($get) === null
                        ? []
                        : $service->periods($programId($get))->mapWithKeys(fn (SidangReportPeriod $period): array => [$period->id => $period->label()])->all())
                    ->live()
                    ->afterStateUpdated(function (mixed $state, Get $get, Set $set) use ($programId): void {
                        $period = SidangReportPeriod::query()->where('program_studi_id', $programId($get))->find($state);
                        $set('starts_on', $period?->starts_on->toDateString());
                        $set('ends_on', $period?->ends_on->toDateString());
                        $set('semester', $period?->semester);
                        $set('start_year', $period?->start_year);
                    }),
                DatePicker::make('starts_on')->label('Tanggal awal')->required()->live()
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set) use ($service): void {
                        if ($state === null || ! CarbonImmutable::canBeCreatedFromFormat($state, 'Y-m-d')) {
                            return;
                        }
                        foreach ($service->suggestion($state) as $field => $value) {
                            if (blank($get($field))) {
                                $set($field, $value);
                            }
                        }
                    }),
                DatePicker::make('ends_on')->label('Tanggal akhir')->required()->afterOrEqual('starts_on'),
                Select::make('semester')->label('Semester')->options(SidangReportPeriodService::SEMESTERS)
                    ->helperText('Saran mengikuti bulan tanggal awal dan bisa dikoreksi.')->required(),
                TextInput::make('start_year')->label('Tahun awal akademik')
                    ->helperText('Contoh 2026 untuk tahun akademik 2026/2027.')
                    ->integer()->minValue(1900)->maxValue(9998)->required(),
            ])
            ->action(function (array $data, Action $action) use ($actor, $service): void {
                try {
                    $period = $service->save($actor, $data);
                } catch (ValidationException $exception) {
                    Notification::make()->title('Periode belum disimpan')->body($exception->getMessage())->danger()->send();
                    $action->halt();

                    return;
                }
                Notification::make()->title($period->label().' disimpan')->success()->send();
            });
    }
}
