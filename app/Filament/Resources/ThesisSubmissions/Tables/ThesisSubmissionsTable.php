<?php

namespace App\Filament\Resources\ThesisSubmissions\Tables;

use App\Enums\ThesisSubmissionStatus;
use App\Filament\Resources\Sempros\SemproResource;
use App\Models\ThesisSubmission;
use App\Services\SemproWorkflowService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ThesisSubmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student.name')
                    ->label('Mahasiswa')
                    ->searchable()
                    ->sortable()
                    ->description(fn(?ThesisSubmission $record): string => $record?->student?->mahasiswaProfile?->nim ?? '-'),
                TextColumn::make('program_studi')
                    ->label('Program Studi')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('title_id')
                    ->label('Judul')
                    ->limit(50)
                    ->searchable()
                    ->wrap(),
                BadgeColumn::make('status')
                    ->colors([
                        'gray' => ThesisSubmissionStatus::MenungguPersetujuan->value,
                        'info' => ThesisSubmissionStatus::SemproDijadwalkan->value,
                        'warning' => ThesisSubmissionStatus::RevisiSempro->value,
                        'success' => ThesisSubmissionStatus::SemproSelesai->value,
                        'primary' => ThesisSubmissionStatus::PembimbingDitetapkan->value,
                    ])
                    ->formatStateUsing(fn(string $state): string => self::statusLabel($state)),
                TextColumn::make('submitted_at')
                    ->label('Tanggal Submit')
                    ->dateTime('d M Y')
                    ->sortable(),
                TextColumn::make('approvedBy.name')
                    ->label('Disetujui Oleh')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('assign_sempro')
                    ->label('Jadwalkan Sempro')
                    ->icon('heroicon-m-calendar')
                    ->color('warning')
                    ->visible(fn(ThesisSubmission $record): bool => $record->status === ThesisSubmissionStatus::MenungguPersetujuan->value)
                    ->action(function (ThesisSubmission $record) {
                        $userId = auth()->id();

                        if ($userId === null) {
                            return null;
                        }

                        $sempro = app(SemproWorkflowService::class)->ensureSemproForSubmission($record, $userId);

                        return redirect(SemproResource::getUrl('edit', ['record' => $sempro->getKey()]));
                    }),
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'menunggu_persetujuan' => 'Menunggu Review',
            'sempro_dijadwalkan' => 'Sempro Dijadwalkan',
            'revisi_sempro' => 'Revisi Sempro',
            'sempro_selesai' => 'Sempro Selesai',
            'pembimbing_ditetapkan' => 'Pembimbing Ditetapkan',
            default => ucwords(str_replace('_', ' ', $status)),
        };
    }
}
