<?php

namespace App\Filament\Resources\Faculties;

use App\Enums\AppRole;
use App\Filament\Resources\Faculties\Pages\CreateFaculty;
use App\Filament\Resources\Faculties\Pages\EditFaculty;
use App\Filament\Resources\Faculties\Pages\ListFaculties;
use App\Filament\Resources\Faculties\Schemas\FacultyForm;
use App\Filament\Resources\Faculties\Tables\FacultiesTable;
use App\Models\Faculty;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class FacultyResource extends Resource
{
    protected static ?string $model = Faculty::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return 'System Management';
    }

    public static function getNavigationLabel(): string
    {
        return 'Fakultas';
    }

    public static function getModelLabel(): string
    {
        return 'Fakultas';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Fakultas';
    }

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        return $user?->hasRole(AppRole::SuperAdmin) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof Faculty
            && ! $record->is_placeholder
            && ! $record->programStudis()->exists();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return FacultyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FacultiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('programStudis');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFaculties::route('/'),
            'create' => CreateFaculty::route('/create'),
            'edit' => EditFaculty::route('/{record}/edit'),
        ];
    }
}
