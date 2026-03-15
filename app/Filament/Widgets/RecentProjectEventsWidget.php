<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ThesisProjects\ThesisProjectResource;
use App\Models\ThesisProjectEvent;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class RecentProjectEventsWidget extends TableWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = [
        'xl' => 5,
    ];

    protected ?string $pollingInterval = null;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Aktivitas Admin Terkini')
            ->query($this->buildTableQuery())
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Waktu')
                    ->since(),
                TextColumn::make('label')
                    ->label('Aktivitas')
                    ->description(fn(ThesisProjectEvent $record): string => $record->project?->student?->name ?? '-')
                    ->wrap(),
                TextColumn::make('actor.name')
                    ->label('Aktor')
                    ->placeholder('Sistem'),
                TextColumn::make('project.programStudi.name')
                    ->label('Prodi')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('openProject')
                    ->label('Buka Proyek')
                    ->url(fn(ThesisProjectEvent $record): string => ThesisProjectResource::getUrl('view', ['record' => $record->project]))
                    ->icon('heroicon-m-arrow-top-right-on-square'),
            ]);
    }

    private function buildTableQuery(): Builder
    {
        $query = ThesisProjectEvent::query()
            ->with([
                'actor',
                'project.student',
                'project.programStudi',
            ]);

        /** @var User|null $user */
        $user = Auth::user();
        $prodiId = $user?->adminProgramStudiId();

        if ($prodiId !== null) {
            $query->whereHas('project', fn(Builder $projectQuery): Builder => $projectQuery->where('program_studi_id', $prodiId));
        }

        return $query;
    }
}
