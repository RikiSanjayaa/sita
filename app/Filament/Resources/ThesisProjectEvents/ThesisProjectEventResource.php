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
use Illuminate\Support\Facades\Auth;

class ThesisProjectEventResource extends Resource
{
    protected static ?string $model = ThesisProjectEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'Tugas Akhir';
    }

    public static function getNavigationLabel(): string
    {
        return 'Audit Logs';
    }

    public static function getModelLabel(): string
    {
        return 'Audit Log';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Audit Logs';
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
}
