<?php

namespace App\Filament\Resources\StudentGuides;

use App\Enums\AppRole;
use App\Filament\Resources\StudentGuides\Pages\EditStudentGuide;
use App\Filament\Resources\StudentGuides\Pages\ListStudentGuides;
use App\Filament\Resources\StudentGuides\Schemas\StudentGuideForm;
use App\Filament\Resources\StudentGuides\Tables\StudentGuidesTable;
use App\Models\ProgramStudi;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class StudentGuideResource extends Resource
{
    protected static ?string $model = ProgramStudi::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasAnyRole([AppRole::Admin->value, AppRole::SuperAdmin->value]) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        $prodiId = $user->adminProgramStudiId();

        return $prodiId === null || (int) $record->getKey() === $prodiId;
    }

    public static function getNavigationGroup(): string
    {
        return 'Tugas Akhir';
    }

    public static function getNavigationLabel(): string
    {
        return 'Panduan Mahasiswa';
    }

    public static function getModelLabel(): string
    {
        return 'Panduan Mahasiswa';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Panduan Mahasiswa';
    }

    public static function form(Schema $schema): Schema
    {
        return StudentGuideForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StudentGuidesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with('studentGuideUpdatedBy');

        /** @var User|null $user */
        $user = Auth::user();
        $prodiId = $user?->adminProgramStudiId();

        if ($prodiId !== null) {
            $query->whereKey($prodiId);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudentGuides::route('/'),
            'edit' => EditStudentGuide::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [];
    }
}
