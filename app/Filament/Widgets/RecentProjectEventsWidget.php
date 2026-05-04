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

    public function table(Table $table): Table
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $table
            ->heading('Aktivitas Admin Terkini')
            ->query($this->query($user))
            ->defaultPaginationPageOption(5)
            ->paginated([5, 10])
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                TextColumn::make('label')
                    ->label('Aktivitas')
                    ->description(fn(ThesisProjectEvent $record): string => ($record->project?->student?->name ?? '-').' - '.($record->actor?->name ?? 'Sistem'))
                    ->wrap(),
                TextColumn::make('occurred_at')
                    ->label('Waktu')
                    ->since(),
                TextColumn::make('description')
                    ->label('Catatan')
                    ->placeholder('-')
                    ->limit(80)
                    ->wrap(),
            ])
            ->recordActions([
                Action::make('openProject')
                    ->label('Buka')
                    ->url(fn(ThesisProjectEvent $record): string => ThesisProjectResource::getUrl('view', ['record' => $record->project]))
                    ->icon('heroicon-m-arrow-top-right-on-square'),
            ]);
    }

    private function query(?User $user): Builder
    {
        $query = ThesisProjectEvent::query()
            ->with([
                'actor',
                'project.student',
            ]);

        if ($user?->adminProgramStudiId() !== null) {
            $query->whereHas('project', function (Builder $projectQuery) use ($user): void {
                $projectQuery->where('program_studi_id', $user->adminProgramStudiId());
            });
        }

        return $query;
    }
}
