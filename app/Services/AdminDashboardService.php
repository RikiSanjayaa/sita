<?php

namespace App\Services;

use App\Filament\Resources\ThesisProjects\Tables\ThesisProjectsTable;
use App\Filament\Resources\ThesisProjects\ThesisProjectResource;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\ThesisProjectEvent;
use App\Models\ThesisRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AdminDashboardService
{
    public function metrics(?User $user): array
    {
        $projects = $this->projectsQuery($user);

        $activeProjects = (clone $projects)
            ->where('state', 'active')
            ->count();

        $earlyStageProjects = (clone $projects)
            ->where('state', 'active')
            ->whereIn('phase', ['title_review', 'sempro'])
            ->count();

        $lateStageProjects = (clone $projects)
            ->where('state', 'active')
            ->whereIn('phase', ['research', 'sidang'])
            ->count();

        $needsSempro = (clone $projects)
            ->where('state', 'active')
            ->whereIn('phase', ['title_review', 'sempro'])
            ->whereDoesntHave('semproDefenses', function (Builder $query): void {
                $query->whereIn('status', ['scheduled', 'completed']);
            })
            ->count();

        $needsSidang = (clone $projects)
            ->where('state', 'active')
            ->whereIn('phase', ['research', 'sidang'])
            ->whereDoesntHave('sidangDefenses', function (Builder $query): void {
                $query->whereIn('status', ['scheduled', 'completed']);
            })
            ->count();

        $incompleteSupervisors = (clone $projects)
            ->where('state', 'active')
            ->whereIn('phase', ['research', 'sidang'])
            ->has('activeSupervisorAssignments', '<', 2)
            ->count();

        $openRevisions = ThesisRevision::query()
            ->whereIn('status', ['open', 'submitted'])
            ->whereHas('project', function (Builder $query) use ($user): void {
                $this->scopeProjectsByUser($query, $user);
            })
            ->count();

        $projectsWithOpenRevisions = ThesisRevision::query()
            ->whereIn('status', ['open', 'submitted'])
            ->whereHas('project', function (Builder $query) use ($user): void {
                $this->scopeProjectsByUser($query, $user);
            })
            ->distinct()
            ->count('project_id');

        $overdueRevisionProjects = ThesisRevision::query()
            ->whereIn('status', ['open', 'submitted'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereHas('project', function (Builder $query) use ($user): void {
                $this->scopeProjectsByUser($query, $user);
                $query->where('state', 'active');
            })
            ->distinct()
            ->count('project_id');

        $unassignedLateStageProjects = (clone $projects)
            ->where('state', 'active')
            ->whereIn('phase', ['research', 'sidang'])
            ->doesntHave('activeSupervisorAssignments')
            ->count();

        $upcomingAgenda = (clone $projects)
            ->whereHas('defenses', function (Builder $query): void {
                $query->where('status', 'scheduled')
                    ->whereNotNull('scheduled_for')
                    ->whereBetween('scheduled_for', [now(), now()->copy()->addDays(7)]);
            })
            ->count();

        $completedThisMonth = (clone $projects)
            ->where('state', 'completed')
            ->whereBetween('completed_at', [now()->copy()->startOfMonth(), now()->copy()->endOfMonth()])
            ->count();

        $onHold = (clone $projects)
            ->where('state', 'on_hold')
            ->count();

        return [
            'activeProjects' => $activeProjects,
            'earlyStageProjects' => $earlyStageProjects,
            'lateStageProjects' => $lateStageProjects,
            'needsSempro' => $needsSempro,
            'needsSidang' => $needsSidang,
            'incompleteSupervisors' => $incompleteSupervisors,
            'openRevisions' => $openRevisions,
            'projectsWithOpenRevisions' => $projectsWithOpenRevisions,
            'overdueRevisionProjects' => $overdueRevisionProjects,
            'upcomingAgenda' => $upcomingAgenda,
            'completedThisMonth' => $completedThisMonth,
            'onHold' => $onHold,
            'unassignedLateStageProjects' => $unassignedLateStageProjects,
        ];
    }

    public function phaseDistribution(?User $user): Collection
    {
        $counts = $this->projectsQuery($user)
            ->selectRaw('phase, count(*) as aggregate')
            ->groupBy('phase')
            ->pluck('aggregate', 'phase');

        return collect([
            ['phase' => 'title_review', 'label' => 'Review Judul', 'color' => 'gray', 'hex' => '#94a3b8'],
            ['phase' => 'sempro', 'label' => 'Sempro', 'color' => 'info', 'hex' => '#06b6d4'],
            ['phase' => 'research', 'label' => 'Riset', 'color' => 'warning', 'hex' => '#f59e0b'],
            ['phase' => 'sidang', 'label' => 'Sidang', 'color' => 'primary', 'hex' => '#2563eb'],
            ['phase' => 'completed', 'label' => 'Selesai', 'color' => 'success', 'hex' => '#16a34a'],
        ])->map(function (array $item) use ($counts): array {
            $item['count'] = (int) ($counts[$item['phase']] ?? 0);

            return $item;
        });
    }

    public function activityTrend(?User $user, int $months = 6): array
    {
        $currentMonths = collect(range($months - 1, 0))
            ->map(fn (int $offset) => now()->copy()->startOfMonth()->subMonths($offset));

        $current = $currentMonths
            ->map(fn ($month): int => $this->projectEventCountForPeriod($user, $month->copy()->startOfMonth(), $month->copy()->endOfMonth()))
            ->all();

        $previous = $currentMonths
            ->map(fn ($month): int => $this->projectEventCountForPeriod($user, $month->copy()->subMonths($months)->startOfMonth(), $month->copy()->subMonths($months)->endOfMonth()))
            ->all();

        return [
            'labels' => $currentMonths->map(fn ($month): string => $month->format('M Y'))->all(),
            'current' => $current,
            'previous' => $previous,
            'currentTotal' => array_sum($current),
            'previousTotal' => array_sum($previous),
        ];
    }

    public function priorityItems(?User $user): array
    {
        $metrics = $this->metrics($user);
        $baseUrl = ThesisProjectResource::getUrl('index');

        return [
            [
                'label' => 'Perlu Sempro',
                'count' => $metrics['needsSempro'],
                'description' => 'Mahasiswa masih berada di review judul/sempro tapi belum punya agenda sempro.',
                'url' => $baseUrl.'?activeTab=perlu-sempro',
            ],
            [
                'label' => 'Perlu Sidang',
                'count' => $metrics['needsSidang'],
                'description' => 'Proyek riset/sidang belum memiliki jadwal atau riwayat sidang.',
                'url' => $baseUrl.'?activeTab=perlu-sidang',
            ],
            [
                'label' => 'Pembimbing Kurang',
                'count' => $metrics['incompleteSupervisors'],
                'description' => 'Proyek aktif di fase lanjut dengan jumlah pembimbing belum lengkap.',
                'url' => $baseUrl.'?activeTab=perlu-pembimbing',
            ],
            [
                'label' => 'Revisi Terbuka',
                'count' => $metrics['openRevisions'],
                'description' => 'Revisi yang masih perlu dipantau sebelum proyek bisa bergerak maju.',
                'url' => $baseUrl.'?activeTab=revisi-terbuka',
            ],
        ];
    }

    public function projectsNeedingAttention(?User $user, int $limit = 6): Collection
    {
        return $this->attentionProjectsQuery($user)
            ->limit($limit)
            ->get()
            ->map(function (ThesisProject $project): array {
                $agenda = $project->defenses
                    ->where('status', 'scheduled')
                    ->whereNotNull('scheduled_for')
                    ->sortBy('scheduled_for')
                    ->first();

                return [
                    'student' => $project->student?->name ?? '-',
                    'nim' => $project->student?->mahasiswaProfile?->nim ?? '-',
                    'phase' => ThesisProjectsTable::phaseLabel($project->phase),
                    'phaseColor' => $this->phaseColor($project->phase),
                    'reason' => $this->attentionReasonPublic($project),
                    'agenda' => $this->projectAgendaSummary($project),
                    'url' => ThesisProjectResource::getUrl('view', ['record' => $project]),
                ];
            });
    }

    public function attentionProjectsQuery(?User $user): Builder
    {
        return $this->buildAttentionProjectsQuery($user);
    }

    public function recentEvents(?User $user, int $limit = 7): Collection
    {
        $query = ThesisProjectEvent::query()
            ->with([
                'actor',
                'project.student',
                'project.programStudi',
            ])
            ->latest('occurred_at');

        if ($user?->adminProgramStudiId() !== null) {
            $query->whereHas('project', function (Builder $projectQuery) use ($user): void {
                $projectQuery->where('program_studi_id', $user->adminProgramStudiId());
            });
        }

        return $query
            ->limit($limit)
            ->get()
            ->map(fn(ThesisProjectEvent $event): array => [
                'label' => $event->label,
                'description' => $event->description ?? 'Tanpa catatan tambahan.',
                'student' => $event->project?->student?->name ?? '-',
                'actor' => $event->actor?->name ?? 'Sistem',
                'time' => $event->occurred_at?->diffForHumans() ?? '-',
                'url' => ThesisProjectResource::getUrl('view', ['record' => $event->project]),
            ]);
    }

    public function agendaItems(?User $user, int $limit = 6): Collection
    {
        $query = ThesisDefense::query()
            ->with([
                'project.student.mahasiswaProfile',
                'project.programStudi',
            ])
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_for')
            ->whereBetween('scheduled_for', [now(), now()->copy()->addDays(14)])
            ->orderBy('scheduled_for');

        if ($user?->adminProgramStudiId() !== null) {
            $query->whereHas('project', function (Builder $projectQuery) use ($user): void {
                $projectQuery->where('program_studi_id', $user->adminProgramStudiId());
            });
        }

        return $query
            ->limit($limit)
            ->get()
            ->map(fn(ThesisDefense $defense): array => [
                'label' => $defense->type === 'sidang' ? 'Sidang' : 'Sempro',
                'student' => $defense->project?->student?->name ?? '-',
                'nim' => $defense->project?->student?->mahasiswaProfile?->nim ?? '-',
                'programStudi' => $defense->project?->programStudi?->name ?? '-',
                'when' => $defense->scheduled_for?->translatedFormat('D, d M Y H:i') ?? '-',
                'location' => trim(implode(' - ', array_filter([$defense->location, $defense->mode]))),
                'url' => ThesisProjectResource::getUrl('view', ['record' => $defense->project]),
            ]);
    }

    /**
     * @return array<int, int>
     */
    public function weeklyMetricHistory(?User $user, string $metric): array
    {
        return collect(range(5, 0))
            ->prepend(0)
            ->map(function (int $offset) use ($user, $metric): int {
                $start = now()->copy()->startOfWeek()->subWeeks($offset);
                $end = $start->copy()->endOfWeek();

                return match ($metric) {
                    'needs_sempro' => $this->projectsQuery($user)
                        ->where('state', 'active')
                        ->whereIn('phase', ['title_review', 'sempro'])
                        ->whereBetween('started_at', [$start, $end])
                        ->count(),
                    'needs_sidang' => $this->projectsQuery($user)
                        ->where('state', 'active')
                        ->whereIn('phase', ['research', 'sidang'])
                        ->whereBetween('started_at', [$start, $end])
                        ->count(),
                    'revisions' => ThesisRevision::query()
                        ->whereIn('status', ['open', 'submitted'])
                        ->whereBetween('created_at', [$start, $end])
                        ->whereHas('project', function (Builder $query) use ($user): void {
                            $this->scopeProjectsByUser($query, $user);
                        })
                        ->count(),
                    'overdue_revision_projects' => ThesisRevision::query()
                        ->whereIn('status', ['open', 'submitted'])
                        ->whereNotNull('due_at')
                        ->whereBetween('due_at', [$start, $end])
                        ->whereHas('project', function (Builder $query) use ($user): void {
                            $this->scopeProjectsByUser($query, $user);
                            $query->where('state', 'active');
                        })
                        ->distinct()
                        ->count('project_id'),
                    'agenda' => ThesisDefense::query()
                        ->where('status', 'scheduled')
                        ->whereBetween('scheduled_for', [$start, $end])
                        ->whereHas('project', function (Builder $query) use ($user): void {
                            $this->scopeProjectsByUser($query, $user);
                        })
                        ->count(),
                    'unassigned_late_stage' => $this->projectsQuery($user)
                        ->where('state', 'active')
                        ->whereIn('phase', ['research', 'sidang'])
                        ->doesntHave('activeSupervisorAssignments')
                        ->whereBetween('started_at', [$start, $end])
                        ->count(),
                    'incomplete_supervisors' => $this->projectsQuery($user)
                        ->where('state', 'active')
                        ->whereIn('phase', ['research', 'sidang'])
                        ->has('activeSupervisorAssignments', '<', 2)
                        ->whereBetween('started_at', [$start, $end])
                        ->count(),
                    default => 0,
                };
            })
            ->all();
    }

    private function projectsQuery(?User $user): Builder
    {
        $query = ThesisProject::query();
        $this->scopeProjectsByUser($query, $user);

        return $query;
    }

    public function attentionReasonPublic(ThesisProject $record): string
    {
        return $this->attentionReason($record);
    }

    public function projectAgendaSummary(ThesisProject $record): string
    {
        $agenda = $record->defenses
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_for')
            ->sortBy('scheduled_for')
            ->first();

        if (! $agenda instanceof ThesisDefense) {
            return 'Belum ada agenda terdekat';
        }

        return sprintf(
            '%s #%d - %s',
            strtoupper($agenda->type),
            $agenda->attempt_no,
            $agenda->scheduled_for?->format('d M Y H:i') ?? '-',
        );
    }

    private function buildAttentionProjectsQuery(?User $user): Builder
    {
        $query = ThesisProject::query()
            ->with([
                'student.mahasiswaProfile',
                'defenses',
                'activeSupervisorAssignments',
            ])
            ->withCount([
                'revisions as open_revisions_count' => fn(Builder $builder): Builder => $builder
                    ->whereIn('status', ['open', 'submitted']),
            ])
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
            ->orderByDesc('started_at');

        $this->scopeProjectsByUser($query, $user);

        return $query;
    }

    private function scopeProjectsByUser(Builder $query, ?User $user): void
    {
        if ($user?->adminProgramStudiId() !== null) {
            $query->where('program_studi_id', $user->adminProgramStudiId());
        }
    }

    private function projectEventCountForPeriod(?User $user, $start, $end): int
    {
        $query = ThesisProjectEvent::query()
            ->whereBetween('occurred_at', [$start, $end]);

        if ($user?->adminProgramStudiId() !== null) {
            $query->whereHas('project', function (Builder $projectQuery) use ($user): void {
                $projectQuery->where('program_studi_id', $user->adminProgramStudiId());
            });
        }

        return $query->count();
    }

    private function attentionReason(ThesisProject $record): string
    {
        if (($record->open_revisions_count ?? 0) > 0) {
            return 'Masih ada revisi terbuka yang perlu dipantau.';
        }

        if (in_array($record->phase, ['research', 'sidang'], true) && $record->activeSupervisorAssignments->count() < 2) {
            return 'Pembimbing aktif belum lengkap untuk fase ini.';
        }

        if (in_array($record->phase, ['title_review', 'sempro'], true) && $record->defenses->where('type', 'sempro')->whereIn('status', ['scheduled', 'completed'])->isEmpty()) {
            return 'Belum ada sempro yang dijadwalkan atau diselesaikan.';
        }

        if (in_array($record->phase, ['research', 'sidang'], true) && $record->defenses->where('type', 'sidang')->whereIn('status', ['scheduled', 'completed'])->isEmpty()) {
            return 'Belum ada sidang yang dijadwalkan atau diselesaikan.';
        }

        return 'Perlu pengecekan manual dari admin.';
    }

    private function phaseColor(string $phase): string
    {
        return match ($phase) {
            'title_review' => 'gray',
            'sempro' => 'info',
            'research' => 'warning',
            'sidang' => 'primary',
            'completed' => 'success',
            default => 'gray',
        };
    }
}
