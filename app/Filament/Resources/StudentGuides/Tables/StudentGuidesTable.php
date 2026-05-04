<?php

namespace App\Filament\Resources\StudentGuides\Tables;

use App\Models\ProgramStudi;
use App\Support\StudentGuideContent;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StudentGuidesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Program Studi')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('student_guide_content')
                    ->label('Status')
                    ->state(fn(ProgramStudi $record): string => filled($record->student_guide_content) ? 'Disesuaikan' : 'Default')
                    ->badge()
                    ->color(fn(string $state): string => $state === 'Disesuaikan' ? 'success' : 'gray'),
                TextColumn::make('guidance_count')
                    ->label('Panduan')
                    ->state(fn(ProgramStudi $record): int => StudentGuideContent::summary($record->student_guide_content)['guidance_cards'])
                    ->badge(),
                TextColumn::make('faq_count')
                    ->label('FAQ')
                    ->state(fn(ProgramStudi $record): int => StudentGuideContent::summary($record->student_guide_content)['faq_items'])
                    ->badge(),
                TextColumn::make('template_count')
                    ->label('Template')
                    ->state(fn(ProgramStudi $record): int => StudentGuideContent::summary($record->student_guide_content)['template_docs'])
                    ->badge(),
                TextColumn::make('studentGuideUpdatedBy.name')
                    ->label('Diubah Oleh')
                    ->placeholder('Belum pernah'),
                TextColumn::make('student_guide_updated_at')
                    ->label('Terakhir Diubah')
                    ->since()
                    ->placeholder('Belum pernah')
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([]);
    }
}
