<?php

namespace App\Filament\Resources\Sempros;

use App\Filament\Resources\Sempros\Pages\CreateSempro;
use App\Filament\Resources\Sempros\Pages\EditSempro;
use App\Filament\Resources\Sempros\Pages\ListSempros;
use App\Filament\Resources\Sempros\Pages\ViewSempro;
use App\Filament\Resources\Sempros\Schemas\SemproForm;
use App\Filament\Resources\Sempros\Schemas\SemproInfolist;
use App\Filament\Resources\Sempros\Tables\SemprosTable;
use App\Models\Sempro;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SemproResource extends Resource
{
    protected static ?string $model = Sempro::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return 'Seminar Proposal';
    }

    public static function getModelLabel(): string
    {
        return 'Sempro';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Sempro';
    }

    public static function getNavigationGroup(): string
    {
        return 'Tugas Akhir';
    }

    public static function form(Schema $schema): Schema
    {
        return SemproForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SemproInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SemprosTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with([
                'submission.student.mahasiswaProfile',
                'examiners.examiner',
                'revisions',
                'approvedBy',
                'createdBy',
            ]);

        $prodiId = auth()->user()?->adminProgramStudiId();

        if ($prodiId !== null) {
            $query->whereHas('thesisSubmission', fn(Builder $q): Builder => $q->where('program_studi_id', $prodiId));
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSempros::route('/'),
            'create' => CreateSempro::route('/create'),
            'view' => ViewSempro::route('/{record}'),
            'edit' => EditSempro::route('/{record}/edit'),
        ];
    }
}
