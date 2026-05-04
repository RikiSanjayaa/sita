<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ThesisProjects\ThesisProjectResource;
use App\Models\ThesisProject;
use App\Models\User;
use App\Services\AdminDashboardService;
use App\Support\Filament\BadgeStyles;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Auth;

class ProjectsNeedingAttentionWidget extends TableWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = [
        'xl' => 7,
    ];

    public function table(Table $table): Table
    {
        /** @var User|null $user */
        $user = Auth::user();
        $service = app(AdminDashboardService::class);

        return $table
            ->heading('Antrian Proyek Prioritas')
            ->description('Proyek yang paling perlu tindakan admin berikutnya.')
            ->query($service->attentionProjectsQuery($user))
            ->defaultPaginationPageOption(5)
            ->paginated([5, 10])
            ->columns([
                TextColumn::make('student.name')
                    ->label('Mahasiswa')
                    ->description(fn(ThesisProject $record): string => $record->student?->mahasiswaProfile?->nim ?? '-')
                    ->searchable(),
                TextColumn::make('phase')
                    ->label('Fase')
                    ->badge()
                    ->formatStateUsing(fn(ThesisProject $record): string => \App\Filament\Resources\ThesisProjects\Tables\ThesisProjectsTable::phaseLabel($record->phase))
                    ->color(fn(ThesisProject $record): string => BadgeStyles::phaseColor($record->phase))
                    ->icon(fn(ThesisProject $record): string => BadgeStyles::phaseIcon($record->phase)),
                TextColumn::make('attention_reason')
                    ->label('Tindakan')
                    ->state(fn(ThesisProject $record): string => $service->attentionReasonPublic($record))
                    ->wrap(),
                TextColumn::make('next_agenda')
                    ->label('Agenda Terdekat')
                    ->state(fn(ThesisProject $record): string => $service->projectAgendaSummary($record))
                    ->placeholder('-')
                    ->wrap(),
            ])
            ->recordActions([
                Action::make('openProject')
                    ->label('Buka')
                    ->url(fn(ThesisProject $record): string => ThesisProjectResource::getUrl('view', ['record' => $record]))
                    ->icon('heroicon-m-arrow-top-right-on-square'),
            ]);
    }
}
