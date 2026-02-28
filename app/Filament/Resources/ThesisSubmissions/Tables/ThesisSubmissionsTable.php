<?php

namespace App\Filament\Resources\ThesisSubmissions\Tables;

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
                        'gray' => 'intake_created',
                        'info' => ['proposal_submitted', 'sempro_scheduled'],
                        'warning' => 'sempro_revision',
                        'success' => ['sempro_approved', 'mentorship_assigned'],
                    ]),
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
                        'intake_created' => 'intake_created',
                        'proposal_submitted' => 'proposal_submitted',
                        'sempro_scheduled' => 'sempro_scheduled',
                        'sempro_revision' => 'sempro_revision',
                        'sempro_approved' => 'sempro_approved',
                        'mentorship_assigned' => 'mentorship_assigned',
                    ]),
            ])
            ->recordActions([
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
