<?php

namespace App\Http\Controllers;

use App\Enums\AdvisorType;
use App\Enums\AppRole;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('welcome', [
            'highlights' => $this->highlights(),
        ]);
    }

    public function schedules(): Response
    {
        $scheduleItems = $this->scheduleItems();
        $now = now();

        return Inertia::render('public/jadwal', [
            'upcomingSchedules' => $scheduleItems
                ->filter(fn(array $item): bool => $item['scheduledAt'] !== null && Carbon::parse($item['scheduledAt'])->greaterThanOrEqualTo($now))
                ->values()
                ->all(),
            'pastSchedules' => $scheduleItems
                ->filter(fn(array $item): bool => $item['scheduledAt'] !== null && Carbon::parse($item['scheduledAt'])->lt($now))
                ->sortByDesc('scheduledAt')
                ->values()
                ->all(),
        ]);
    }

    public function advisors(): Response
    {
        $advisorDirectory = $this->advisorDirectory();

        return Inertia::render('public/pembimbing', [
            'advisorDirectory' => $advisorDirectory->all(),
            'advisorPrograms' => $advisorDirectory
                ->map(fn(array $advisor): array => [
                    'slug' => $advisor['programSlug'],
                    'name' => $advisor['programStudi'],
                ])
                ->unique('slug')
                ->sortBy('name')
                ->values()
                ->all(),
        ]);
    }

    public function topics(): Response
    {
        return Inertia::render('public/topik', [
            'semproTitles' => $this->semproTitles()->all(),
        ]);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function highlights(): array
    {
        $scheduleItemsCount = ThesisDefense::query()
            ->whereIn('type', ['sempro', 'sidang'])
            ->whereIn('status', ['scheduled', 'completed'])
            ->count();

        $advisorCount = User::query()
            ->whereHas('roles', function ($query): void {
                $query->where('name', AppRole::Dosen->value);
            })
            ->whereHas('dosenProfile', function ($query): void {
                $query->where('is_active', true);
            })
            ->count();

        $topicCount = ThesisProject::query()
            ->whereHas('activeSupervisorAssignments', function ($query): void {
                $query->where('status', 'active');
            })
            ->whereHas('semproDefenses', function ($query): void {
                $query->whereIn('status', ['scheduled', 'completed']);
            })
            ->count();

        return [
            [
                'label' => 'Jadwal Publik',
                'value' => (string) $scheduleItemsCount,
            ],
            [
                'label' => 'Dosen Pembimbing',
                'value' => (string) $advisorCount,
            ],
            [
                'label' => 'Topik Sempro',
                'value' => (string) $topicCount,
            ],
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function scheduleItems(): Collection
    {
        return ThesisDefense::query()
            ->with([
                'project.student.mahasiswaProfile',
                'project.programStudi',
                'titleVersion',
            ])
            ->whereIn('type', ['sempro', 'sidang'])
            ->whereIn('status', ['scheduled', 'completed'])
            ->whereNotNull('scheduled_for')
            ->orderBy('scheduled_for')
            ->limit(60)
            ->get()
            ->map(function (ThesisDefense $defense): array {
                $project = $defense->project;

                return [
                    'id' => $defense->id,
                    'type' => $defense->type,
                    'typeLabel' => $defense->type === 'sidang' ? 'Sidang' : 'Sempro',
                    'studentName' => $project?->student?->name ?? '-',
                    'studentNim' => $project?->student?->mahasiswaProfile?->nim ?? '-',
                    'programStudi' => $project?->programStudi?->name ?? '-',
                    'title' => $defense->titleVersion?->title_id ?? '-',
                    'scheduledAt' => $defense->scheduled_for?->toIso8601String(),
                    'scheduledFor' => $defense->scheduled_for?->locale('id')->translatedFormat('d F Y, H:i'),
                    'location' => $defense->location ?? '-',
                    'mode' => $defense->mode ?? '-',
                    'statusLabel' => $defense->status === 'completed' ? 'Selesai' : 'Terjadwal',
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function advisorDirectory(): Collection
    {
        return User::query()
            ->whereHas('roles', function ($query): void {
                $query->where('name', AppRole::Dosen->value);
            })
            ->whereHas('dosenProfile', function ($query): void {
                $query->where('is_active', true);
            })
            ->with([
                'dosenProfile.programStudi',
            ])
            ->withCount([
                'thesisSupervisorAssignments as primary_advisee_count' => function ($query): void {
                    $query->where('role', AdvisorType::Primary->value)
                        ->where('status', 'active')
                        ->whereHas('project', function ($projectQuery): void {
                            $projectQuery->where('state', 'active');
                        });
                },
                'thesisSupervisorAssignments as secondary_advisee_count' => function ($query): void {
                    $query->where('role', AdvisorType::Secondary->value)
                        ->where('status', 'active')
                        ->whereHas('project', function ($projectQuery): void {
                            $projectQuery->where('state', 'active');
                        });
                },
            ])
            ->orderBy('name')
            ->get()
            ->map(function (User $lecturer): array {
                $profile = $lecturer->dosenProfile;
                $programStudi = $profile?->programStudi;
                $primaryCount = (int) $lecturer->primary_advisee_count;
                $secondaryCount = (int) $lecturer->secondary_advisee_count;

                return [
                    'id' => $lecturer->id,
                    'name' => $lecturer->name,
                    'programStudi' => $programStudi?->name ?? 'Belum diatur',
                    'programSlug' => $programStudi?->slug ?? 'umum',
                    'concentration' => $profile?->concentration ?? ProgramStudi::DEFAULT_GENERAL_CONCENTRATION,
                    'primaryCount' => $primaryCount,
                    'secondaryCount' => $secondaryCount,
                    'totalActiveCount' => $primaryCount + $secondaryCount,
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function semproTitles(): Collection
    {
        return ThesisProject::query()
            ->with([
                'latestTitle',
                'programStudi',
                'activeSupervisorAssignments' => function ($query): void {
                    $query->orderBy('role');
                },
                'activeSupervisorAssignments.lecturer',
                'semproDefenses' => function ($query): void {
                    $query->whereIn('status', ['scheduled', 'completed'])
                        ->orderByDesc('scheduled_for');
                },
            ])
            ->withMax([
                'semproDefenses as latest_sempro_at' => function ($query): void {
                    $query->whereIn('status', ['scheduled', 'completed']);
                },
            ], 'scheduled_for')
            ->whereHas('activeSupervisorAssignments', function ($query): void {
                $query->where('status', 'active');
            })
            ->whereHas('semproDefenses', function ($query): void {
                $query->whereIn('status', ['scheduled', 'completed']);
            })
            ->orderByDesc('latest_sempro_at')
            ->limit(30)
            ->get()
            ->map(function (ThesisProject $project): array {
                $latestSempro = $project->semproDefenses->first();

                return [
                    'id' => $project->id,
                    'programStudi' => $project->programStudi?->name ?? '-',
                    'title' => $project->latestTitle?->title_id ?? '-',
                    'summary' => $project->latestTitle?->proposal_summary ?? '-',
                    'semproStatus' => $latestSempro?->status === 'completed' ? 'Selesai' : 'Terjadwal',
                    'semproDate' => $latestSempro?->scheduled_for?->locale('id')->translatedFormat('d F Y, H:i'),
                    'advisors' => $project->activeSupervisorAssignments
                        ->map(fn($assignment): array => [
                            'name' => $assignment->lecturer?->name ?? '-',
                            'label' => $assignment->role === AdvisorType::Primary->value ? 'Pembimbing 1' : 'Pembimbing 2',
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values();
    }
}
