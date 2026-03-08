<?php

namespace App\Filament\Resources\ThesisProjects;

use App\Filament\Resources\ThesisProjects\Pages\ListThesisProjects;
use App\Filament\Resources\ThesisProjects\Pages\ViewThesisProject;
use App\Filament\Resources\ThesisProjects\Schemas\ThesisProjectInfolist;
use App\Filament\Resources\ThesisProjects\Tables\ThesisProjectsTable;
use App\Models\ThesisProject;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ThesisProjectResource extends Resource
{
    protected static ?string $model = ThesisProject::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 0;

    public static function getNavigationLabel(): string
    {
        return 'Proyek Tugas Akhir';
    }

    public static function getModelLabel(): string
    {
        return 'Proyek Tugas Akhir';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Proyek Tugas Akhir';
    }

    public static function getNavigationGroup(): string
    {
        return 'Tugas Akhir';
    }

    public static function infolist(Schema $schema): Schema
    {
        return ThesisProjectInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ThesisProjectsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with([
                'student.mahasiswaProfile',
                'student.mentorshipDocumentsAsStudent.lecturer',
                'programStudi',
                'latestTitle',
                'titles.decidedBy',
                'activeSupervisorAssignments.lecturer.dosenProfile',
                'supervisorAssignments.lecturer.dosenProfile',
                'documents' => fn($builder) => $builder
                    ->with([
                        'titleVersion',
                        'defense',
                        'revision',
                        'uploadedBy',
                    ])
                    ->orderByDesc('uploaded_at')
                    ->orderByDesc('version_no'),
                'defenses' => fn($builder) => $builder
                    ->with([
                        'titleVersion',
                        'examiners.lecturer.dosenProfile',
                        'revisions.requestedBy',
                        'revisions.resolvedBy',
                    ])
                    ->orderBy('type')
                    ->orderBy('attempt_no'),
                'events.actor',
            ])
            ->withCount([
                'semproDefenses as sempro_attempts_count',
                'sidangDefenses as sidang_attempts_count',
                'revisions as open_revisions_count' => fn(Builder $builder): Builder => $builder
                    ->whereIn('status', ['open', 'submitted']),
            ]);

        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $prodiId = $user?->adminProgramStudiId();

        if ($prodiId !== null) {
            $query->where('program_studi_id', $prodiId);
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListThesisProjects::route('/'),
            'view' => ViewThesisProject::route('/{record}'),
        ];
    }
}
