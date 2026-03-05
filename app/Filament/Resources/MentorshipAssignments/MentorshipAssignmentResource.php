<?php

namespace App\Filament\Resources\MentorshipAssignments;

use App\Filament\Resources\MentorshipAssignments\Pages\CreateMentorshipAssignment;
use App\Filament\Resources\MentorshipAssignments\Pages\EditMentorshipAssignment;
use App\Filament\Resources\MentorshipAssignments\Pages\ListMentorshipAssignments;
use App\Filament\Resources\MentorshipAssignments\Pages\ViewMentorshipAssignment;
use App\Filament\Resources\MentorshipAssignments\Schemas\MentorshipAssignmentForm;
use App\Filament\Resources\MentorshipAssignments\Schemas\MentorshipAssignmentInfolist;
use App\Filament\Resources\MentorshipAssignments\Tables\MentorshipAssignmentsTable;
use App\Models\MentorshipAssignment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MentorshipAssignmentResource extends Resource
{
    protected static ?string $model = MentorshipAssignment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return 'Pembimbingan';
    }

    public static function getModelLabel(): string
    {
        return 'Pembimbingan';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Pembimbingan';
    }

    public static function getNavigationGroup(): string
    {
        return 'Tugas Akhir';
    }

    public static function form(Schema $schema): Schema
    {
        return MentorshipAssignmentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MentorshipAssignmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MentorshipAssignmentsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with([
                'student.mahasiswaProfile',
                'lecturer.dosenProfile',
                'assignedBy',
            ]);

        $prodiId = auth()->user()?->adminProgramStudiId();

        if ($prodiId !== null) {
            $query->whereHas('student.mahasiswaProfile', fn(Builder $q): Builder => $q->where('program_studi_id', $prodiId));
        }

        return $query;
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
            'index' => ListMentorshipAssignments::route('/'),
            'create' => CreateMentorshipAssignment::route('/create'),
            'view' => ViewMentorshipAssignment::route('/{record}'),
            'edit' => EditMentorshipAssignment::route('/{record}/edit'),
        ];
    }
}
