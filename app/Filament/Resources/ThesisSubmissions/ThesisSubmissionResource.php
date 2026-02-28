<?php

namespace App\Filament\Resources\ThesisSubmissions;

use App\Filament\Resources\ThesisSubmissions\Pages\CreateThesisSubmission;
use App\Filament\Resources\ThesisSubmissions\Pages\EditThesisSubmission;
use App\Filament\Resources\ThesisSubmissions\Pages\ListThesisSubmissions;
use App\Filament\Resources\ThesisSubmissions\Pages\ViewThesisSubmission;
use App\Filament\Resources\ThesisSubmissions\Schemas\ThesisSubmissionForm;
use App\Filament\Resources\ThesisSubmissions\Schemas\ThesisSubmissionInfolist;
use App\Filament\Resources\ThesisSubmissions\Tables\ThesisSubmissionsTable;
use App\Models\ThesisSubmission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ThesisSubmissionResource extends Resource
{
    protected static ?string $model = ThesisSubmission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return 'Judul & Proposal';
    }

    public static function getModelLabel(): string
    {
        return 'Judul & Proposal';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Judul & Proposal';
    }

    public static function getNavigationGroup(): string
    {
        return 'Tugas Akhir Workflow';
    }

    public static function form(Schema $schema): Schema
    {
        return ThesisSubmissionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ThesisSubmissionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ThesisSubmissionsTable::configure($table);
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
            'index' => ListThesisSubmissions::route('/'),
            'create' => CreateThesisSubmission::route('/create'),
            'view' => ViewThesisSubmission::route('/{record}'),
            'edit' => EditThesisSubmission::route('/{record}/edit'),
        ];
    }
}
