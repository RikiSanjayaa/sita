<?php

namespace App\Filament\Widgets;

use App\Models\ThesisProject;
use App\Models\ThesisRevision;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AdminOverviewStats extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $heading = 'Ringkasan Admin';

    protected ?string $description = 'Angka inti untuk membantu admin melihat beban kerja dan perhatian utama hari ini.';

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $projects = $this->scopedProjectsQuery();

        $activeProjects = (clone $projects)
            ->where('state', 'active')
            ->count();

        $needsAttention = (clone $projects)
            ->where('state', 'active')
            ->where(function (Builder $query): void {
                $query->where(function (Builder $subQuery): void {
                    $subQuery->whereIn('phase', ['research', 'sidang'])
                        ->has('activeSupervisorAssignments', '<', 2);
                })->orWhereHas('revisions', function (Builder $revisionQuery): void {
                    $revisionQuery->whereIn('status', ['open', 'submitted']);
                })->orWhere(function (Builder $subQuery): void {
                    $subQuery->whereIn('phase', ['title_review', 'sempro'])
                        ->whereDoesntHave('semproDefenses', function (Builder $defenseQuery): void {
                            $defenseQuery->whereIn('status', ['scheduled', 'completed']);
                        });
                })->orWhere(function (Builder $subQuery): void {
                    $subQuery->whereIn('phase', ['research', 'sidang'])
                        ->whereDoesntHave('sidangDefenses', function (Builder $defenseQuery): void {
                            $defenseQuery->whereIn('status', ['scheduled', 'completed']);
                        });
                });
            })
            ->count();

        $openRevisions = ThesisRevision::query()
            ->whereIn('status', ['open', 'submitted'])
            ->whereHas('project', function (Builder $query): void {
                $this->scopeProjectsByAdmin($query);
            })
            ->count();

        $upcomingAgenda = (clone $projects)
            ->whereHas('defenses', function (Builder $query): void {
                $query->where('status', 'scheduled')
                    ->whereNotNull('scheduled_for')
                    ->whereBetween('scheduled_for', [now(), now()->copy()->addDays(7)]);
            })
            ->count();

        return [
            Stat::make('Proyek Aktif', (string) $activeProjects)
                ->description('Total proyek tugas akhir yang saat ini masih aktif.')
                ->color('primary'),
            Stat::make('Perlu Tindak Lanjut', (string) $needsAttention)
                ->description('Proyek yang masih butuh pembimbing, jadwal, atau penutupan fase.')
                ->color('warning'),
            Stat::make('Revisi Terbuka', (string) $openRevisions)
                ->description('Revisi yang masih perlu dipantau atau diselesaikan.')
                ->color('danger'),
            Stat::make('Agenda 7 Hari', (string) $upcomingAgenda)
                ->description('Sempro atau sidang yang sudah terjadwal dalam waktu dekat.')
                ->color('success'),
        ];
    }

    private function scopedProjectsQuery(): Builder
    {
        $query = ThesisProject::query();

        $this->scopeProjectsByAdmin($query);

        return $query;
    }

    private function scopeProjectsByAdmin(Builder $query): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        $prodiId = $user?->adminProgramStudiId();

        if ($prodiId !== null) {
            $query->where('program_studi_id', $prodiId);
        }
    }
}
