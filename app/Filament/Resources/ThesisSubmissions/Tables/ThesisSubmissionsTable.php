<?php

namespace App\Filament\Resources\ThesisSubmissions\Tables;

use App\Enums\ThesisSubmissionStatus;
use App\Filament\Resources\Sempros\SemproResource;
use App\Models\ThesisSubmission;
use App\Services\SemproWorkflowService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
                    ->sortable(),
                TextColumn::make('program_studi')
                    ->label('Program Studi')
                    ->searchable(),
                TextColumn::make('title_id')
                    ->label('Judul')
                    ->limit(45)
                    ->searchable(),
                BadgeColumn::make('status')
                    ->colors([
                        'gray' => 'menunggu_persetujuan',
                        'info' => 'sempro_dijadwalkan',
                        'warning' => 'revisi_sempro',
                        'success' => ['sempro_selesai', 'pembimbing_ditetapkan'],
                    ])
                    ->formatStateUsing(fn(string $state): string => ucwords(str_replace('_', ' ', $state))),
                TextColumn::make('submitted_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('approved_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('approvedBy.name')
                    ->label('Disetujui Oleh')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'menunggu_persetujuan' => 'Menunggu Persetujuan',
                        'sempro_dijadwalkan' => 'Sempro Dijadwalkan',
                        'revisi_sempro' => 'Revisi Sempro',
                        'sempro_selesai' => 'Sempro Selesai',
                        'pembimbing_ditetapkan' => 'Pembimbing Ditetapkan',
                    ]),
            ])
            ->recordActions([
                Action::make('assign_sempro')
                    ->label('Jadwalkan Sempro')
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
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
