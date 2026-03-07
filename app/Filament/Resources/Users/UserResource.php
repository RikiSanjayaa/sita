<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Schemas\UserInfolist;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with([
                'roles',
                'mahasiswaProfile.programStudi',
                'dosenProfile.programStudi',
                'adminProfile.programStudi',
            ])
            ->withCount([
                'thesisSupervisorAssignments as active_primary_supervision_count' => static fn(Builder $query): Builder => $query
                    ->where('role', 'primary')
                    ->where('status', 'active')
                    ->whereHas('project', static fn(Builder $projectQuery): Builder => $projectQuery->where('state', 'active')),
                'thesisSupervisorAssignments as active_secondary_supervision_count' => static fn(Builder $query): Builder => $query
                    ->where('role', 'secondary')
                    ->where('status', 'active')
                    ->whereHas('project', static fn(Builder $projectQuery): Builder => $projectQuery->where('state', 'active')),
                'thesisDefenseExaminerAssignments as scheduled_sempro_examiner_count' => static fn(Builder $query): Builder => $query
                    ->whereHas('defense', static fn(Builder $defenseQuery): Builder => $defenseQuery
                        ->where('type', 'sempro')
                        ->where('status', 'scheduled')
                        ->whereHas('project', static fn(Builder $projectQuery): Builder => $projectQuery->where('state', 'active'))),
                'thesisDefenseExaminerAssignments as scheduled_sidang_examiner_count' => static fn(Builder $query): Builder => $query
                    ->whereHas('defense', static fn(Builder $defenseQuery): Builder => $defenseQuery
                        ->where('type', 'sidang')
                        ->where('status', 'scheduled')
                        ->whereHas('project', static fn(Builder $projectQuery): Builder => $projectQuery->where('state', 'active'))),
            ]);

        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $prodiId = $user?->adminProgramStudiId();

        if ($prodiId !== null) {
            $query->where(function (Builder $q) use ($prodiId): void {
                $q->whereHas('mahasiswaProfile', fn(Builder $sub): Builder => $sub->where('program_studi_id', $prodiId))
                    ->orWhereHas('dosenProfile', fn(Builder $sub): Builder => $sub->where('program_studi_id', $prodiId))
                    ->orWhereHas('adminProfile', fn(Builder $sub): Builder => $sub->where('program_studi_id', $prodiId));
            });
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
