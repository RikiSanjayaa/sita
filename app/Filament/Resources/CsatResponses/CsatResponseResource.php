<?php

namespace App\Filament\Resources\CsatResponses;

use App\Filament\Resources\CsatResponses\Pages\ListCsatResponses;
use App\Filament\Resources\CsatResponses\Tables\CsatResponsesTable;
use App\Models\CsatResponse;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CsatResponseResource extends Resource
{
    protected static ?string $model = CsatResponse::class;

    protected static ?string $recordTitleAttribute = 'id';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?int $navigationSort = 4;

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
        return false;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Sistem';
    }

    public static function getNavigationLabel(): string
    {
        return 'CSAT & Umpan Balik';
    }

    public static function getModelLabel(): string
    {
        return 'Respons CSAT';
    }

    public static function getPluralModelLabel(): string
    {
        return 'CSAT & Umpan Balik';
    }

    public static function table(Table $table): Table
    {
        return CsatResponsesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['programStudi', 'user']);

        /** @var User|null $user */
        $user = Auth::user();

        return $query->visibleToAdmin($user);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCsatResponses::route('/'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'user.name',
            'programStudi.name',
            'kritik',
            'saran',
        ];
    }
}
