<?php

namespace App\Filament\Resources\ExpertiseFields;

use App\Enums\AppRole;
use App\Filament\Resources\ExpertiseFields\Pages\CreateExpertiseField;
use App\Filament\Resources\ExpertiseFields\Pages\EditExpertiseField;
use App\Filament\Resources\ExpertiseFields\Pages\ListExpertiseFields;
use App\Filament\Resources\ExpertiseFields\Schemas\ExpertiseFieldForm;
use App\Filament\Resources\ExpertiseFields\Tables\ExpertiseFieldsTable;
use App\Models\ExpertiseField;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ExpertiseFieldResource extends Resource
{
    protected static ?string $model = ExpertiseField::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return 'System Management';
    }

    public static function getNavigationLabel(): string
    {
        return 'Bidang Keilmuan';
    }

    public static function getModelLabel(): string
    {
        return 'Bidang Keilmuan';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Bidang Keilmuan';
    }

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        return $user?->hasRole(AppRole::SuperAdmin) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof ExpertiseField && ! $record->lecturers()->exists();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ExpertiseFieldForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExpertiseFieldsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('lecturers');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExpertiseFields::route('/'),
            'create' => CreateExpertiseField::route('/create'),
            'edit' => EditExpertiseField::route('/{record}/edit'),
        ];
    }
}
