<?php

namespace App\Filament\Resources\ThesisProjectEvents;

use App\Filament\Resources\ThesisProjectEvents\Pages\ListThesisProjectEvents;
use App\Filament\Resources\ThesisProjectEvents\Tables\ThesisProjectEventsTable;
use App\Models\ThesisProjectEvent;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ThesisProjectEventResource extends Resource
{
    protected static ?string $model = ThesisProjectEvent::class;

    protected static ?string $recordTitleAttribute = 'label';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'Tugas Akhir';
    }

    public static function getNavigationLabel(): string
    {
        return 'Audit Tugas Akhir';
    }

    public static function getModelLabel(): string
    {
        return 'Audit Tugas Akhir';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Audit Tugas Akhir';
    }

    public static function table(Table $table): Table
    {
        return ThesisProjectEventsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with([
                'actor',
                'project.student.mahasiswaProfile',
                'project.programStudi',
                'project.latestTitle',
            ]);

        /** @var User|null $user */
        $user = Auth::user();
        $prodiId = $user?->adminProgramStudiId();

        if ($prodiId !== null) {
            $query->whereHas('project', fn(Builder $projectQuery): Builder => $projectQuery->where('program_studi_id', $prodiId));
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListThesisProjectEvents::route('/'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'label',
            'description',
            'event_type',
            'actor.name',
            'project.student.name',
            'project.latestTitle.title_id',
        ];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var ThesisProjectEvent $record */
        return array_filter([
            'Mahasiswa' => $record->project?->student?->name,
            'Prodi' => $record->project?->programStudi?->name,
            'Aktor' => $record->actor?->name ?? 'Sistem',
        ]);
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return static::getEloquentQuery();
    }
}
