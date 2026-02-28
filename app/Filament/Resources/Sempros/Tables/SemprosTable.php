<?php

namespace App\Filament\Resources\Sempros\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SemprosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('submission.student.name')
                    ->label('Mahasiswa')
                    ->sortable(),
                BadgeColumn::make('status')
                    ->colors([
                        'gray' => 'draft',
                        'info' => 'scheduled',
                        'warning' => 'revision_open',
                        'success' => 'approved',
                    ]),
                TextColumn::make('scheduled_for')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('location')
                    ->searchable(),
                TextColumn::make('mode')
                    ->searchable(),
                TextColumn::make('revision_due_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('approved_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('approvedBy.name')
                    ->label('Approved By')
                    ->sortable(),
                TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'draft',
                        'scheduled' => 'scheduled',
                        'revision_open' => 'revision_open',
                        'approved' => 'approved',
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
