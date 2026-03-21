<?php

namespace App\Filament\Resources\SystemAnnouncements;

use App\Enums\AppRole;
use App\Filament\Resources\SystemAnnouncements\Pages\CreateSystemAnnouncement;
use App\Filament\Resources\SystemAnnouncements\Pages\EditSystemAnnouncement;
use App\Filament\Resources\SystemAnnouncements\Pages\ListSystemAnnouncements;
use App\Filament\Resources\SystemAnnouncements\Schemas\SystemAnnouncementForm;
use App\Filament\Resources\SystemAnnouncements\Tables\SystemAnnouncementsTable;
use App\Models\SystemAnnouncement;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class SystemAnnouncementResource extends Resource
{
    protected static ?string $model = SystemAnnouncement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->hasAnyRole([AppRole::Admin->value, AppRole::SuperAdmin->value]) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        $prodiId = $user->adminProgramStudiId();

        return $prodiId === null || (int) $record->getAttribute('program_studi_id') === $prodiId;
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function getNavigationGroup(): string
    {
        return 'System Management';
    }

    public static function getNavigationLabel(): string
    {
        return 'Pengumuman Sistem';
    }

    public static function getModelLabel(): string
    {
        return 'Pengumuman Sistem';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Pengumuman Sistem';
    }

    public static function form(Schema $schema): Schema
    {
        return SystemAnnouncementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SystemAnnouncementsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['programStudi', 'createdBy', 'updatedBy']);

        /** @var User|null $user */
        $user = Auth::user();
        $prodiId = $user?->adminProgramStudiId();

        if ($prodiId !== null) {
            $query->where('program_studi_id', $prodiId);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSystemAnnouncements::route('/'),
            'create' => CreateSystemAnnouncement::route('/create'),
            'edit' => EditSystemAnnouncement::route('/{record}/edit'),
        ];
    }
}
