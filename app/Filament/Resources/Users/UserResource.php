<?php

namespace App\Filament\Resources\Users;

use App\Enums\AssignmentStatus;
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
        return parent::getEloquentQuery()
            ->with(['roles', 'mahasiswaProfile', 'dosenProfile'])
            ->withCount([
                'thesisSubmissions as thesis_submission_count',
                'mentorshipAssignmentsAsLecturer as active_bimbingan_count' => static fn (Builder $query): Builder => $query->where('status', AssignmentStatus::Active->value),
                'mentorshipAssignmentsAsLecturer as finished_bimbingan_count' => static fn (Builder $query): Builder => $query->where('status', AssignmentStatus::Ended->value),
                'semproExaminerAssignments as active_uji_count' => static fn (Builder $query): Builder => $query->whereHas('sempro', static fn (Builder $semproQuery): Builder => $semproQuery->whereIn('status', ['draft', 'scheduled', 'revision_open'])),
            ]);
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
