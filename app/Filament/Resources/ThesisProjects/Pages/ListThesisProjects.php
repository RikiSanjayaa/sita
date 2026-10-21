<?php

namespace App\Filament\Resources\ThesisProjects\Pages;

use App\Filament\Resources\ThesisProjects\ThesisProjectResource;
use App\Support\AcademicTerminology;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListThesisProjects extends ListRecords
{
    protected static string $resource = ThesisProjectResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua '.AcademicTerminology::neutral()['finalWork']),
            'perlu-sempro' => Tab::make('Perlu '.AcademicTerminology::neutral()['proposalExamShort'])
                ->modifyQueryUsing(fn(Builder $query): Builder => $query
                    ->where('state', 'active')
                    ->whereIn('phase', ['title_review', 'sempro'])
                    ->whereDoesntHave('semproDefenses', fn(Builder $defenseQuery): Builder => $defenseQuery
                        ->whereIn('status', ['scheduled', 'completed']))),
            'perlu-pembimbing' => Tab::make('Perlu Pembimbing')
                ->modifyQueryUsing(fn(Builder $query): Builder => $query
                    ->where('state', 'active')
                    ->whereIn('phase', ['research', 'sidang'])
                    ->has('activeSupervisorAssignments', '<', 2)),
            'perlu-sidang' => Tab::make('Perlu '.AcademicTerminology::neutral()['finalExamShort'])
                ->modifyQueryUsing(fn(Builder $query): Builder => $query
                    ->where('state', 'active')
                    ->whereIn('phase', ['research', 'sidang'])
                    ->whereDoesntHave('sidangDefenses', fn(Builder $defenseQuery): Builder => $defenseQuery
                        ->whereIn('status', ['scheduled', 'completed']))),
            'revisi-terbuka' => Tab::make('Revisi Terbuka')
                ->modifyQueryUsing(fn(Builder $query): Builder => $query
                    ->whereHas('revisions', fn(Builder $revisionQuery): Builder => $revisionQuery
                        ->whereIn('status', ['open', 'submitted']))),
        ];
    }
}
