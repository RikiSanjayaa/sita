<?php

namespace App\Filament\Resources\SystemAuditLogs;

use App\Filament\Resources\SystemAuditLogs\Pages\ListSystemAuditLogs;
use App\Filament\Resources\SystemAuditLogs\Tables\SystemAuditLogsTable;
use App\Models\SystemAuditLog;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SystemAuditLogResource extends Resource
{
    protected static ?string $model = SystemAuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return 'Sistem';
    }

    public static function getNavigationLabel(): string
    {
        return 'Audit Sistem';
    }

    public static function getModelLabel(): string
    {
        return 'Audit Sistem';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Audit Sistem';
    }

    public static function table(Table $table): Table
    {
        return SystemAuditLogsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('user');

        /** @var User|null $user */
        $user = Auth::user();

        if ($user?->hasRole('admin')) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSystemAuditLogs::route('/'),
        ];
    }
}
