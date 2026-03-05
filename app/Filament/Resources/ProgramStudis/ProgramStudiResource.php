<?php

namespace App\Filament\Resources\ProgramStudis;

use App\Filament\Resources\ProgramStudis\Pages\CreateProgramStudi;
use App\Filament\Resources\ProgramStudis\Pages\EditProgramStudi;
use App\Filament\Resources\ProgramStudis\Pages\ListProgramStudis;
use App\Filament\Resources\ProgramStudis\Schemas\ProgramStudiForm;
use App\Filament\Resources\ProgramStudis\Tables\ProgramStudisTable;
use App\Models\ProgramStudi;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProgramStudiResource extends Resource
{
    protected static ?string $model = ProgramStudi::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    public static function getNavigationGroup(): ?string
    {
        return 'System Management';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(\App\Enums\AppRole::SuperAdmin) ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return 'Program Studi';
    }

    public static function getModelLabel(): string
    {
        return 'Program Studi';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Program Studi';
    }

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ProgramStudiForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProgramStudisTable::configure($table);
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
            'index' => ListProgramStudis::route('/'),
            'create' => CreateProgramStudi::route('/create'),
            'edit' => EditProgramStudi::route('/{record}/edit'),
        ];
    }
}
