<?php

namespace App\Http\Controllers;

use App\Enums\AdvisorType;
use App\Enums\AppRole;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use Illuminate\Http\Request;
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

    public function schedules(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $upcomingSchedules = $this->upcomingScheduleItems($search, (int) $request->integer('upcoming_page', 1));
        $followUpSchedules = $this->followUpScheduleItems($search, (int) $request->integer('follow_up_page', 1));

        return Inertia::render('public/jadwal', [
            'filters' => [
                'search' => $search,
            ],
            'upcomingSchedules' => $upcomingSchedules['items'],
            'upcomingPagination' => $upcomingSchedules['pagination'],
            'followUpSchedules' => $followUpSchedules['items'],
            'followUpPagination' => $followUpSchedules['pagination'],
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
            'concentrationStudentTotals' => $this->advisorConcentrationTotals(),
        ]);
    }

    public function topics(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $program = trim((string) $request->string('program'));
        $topicData = $this->semproTitles(
            search: $search,
            program: $program,
            page: (int) $request->integer('page', 1),
        );

        return Inertia::render('public/topik', [
            'filters' => [
                'search' => $search,
                'program' => $program,
            ],
            'semproTitles' => $topicData['items'],
            'topicPagination' => $topicData['pagination'],
            'topicPrograms' => $this->topicPrograms()
                ->map(fn(array $item): array => [
                    'slug' => $item['programSlug'],
                    'name' => $item['programStudi'],
                ])
                ->unique('slug')
                ->sortBy('name')
                ->values()
                ->all(),
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
                $query->where('status', 'completed');
            })
            ->count();

        return [
            [
                'label' => 'Jadwal',
                'value' => (string) $scheduleItemsCount,
            ],
            [
                'label' => 'Dosen',
                'value' => (string) $advisorCount,
            ],
            [
                'label' => 'Topik',
                'value' => (string) $topicCount,
            ],
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function upcomingScheduleItems(string $search = '', int $page = 1): array
    {
        $paginator = ThesisDefense::query()
            ->with([
                'project.student.mahasiswaProfile',
                'project.programStudi',
                'titleVersion',
            ])
            ->whereIn('type', ['sempro', 'sidang'])
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '>=', now())
            ->orderBy('scheduled_for')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery
                        ->whereHas('project.student', function ($studentQuery) use ($search): void {
                            $studentQuery->where('name', 'like', "%{$search}%")
                                ->orWhereHas('mahasiswaProfile', function ($profileQuery) use ($search): void {
                                    $profileQuery->where('nim', 'like', "%{$search}%");
                                });
                        })
                        ->orWhereHas('project.programStudi', function ($programQuery) use ($search): void {
                            $programQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('titleVersion', function ($titleQuery) use ($search): void {
                            $titleQuery->where('title_id', 'like', "%{$search}%")
                                ->orWhere('title_en', 'like', "%{$search}%")
                                ->orWhere('proposal_summary', 'like', "%{$search}%");
                        })
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('mode', 'like', "%{$search}%");
                });
            })
            ->simplePaginate(perPage: 10, pageName: 'upcoming_page', page: $page)
            ->through(function (ThesisDefense $defense): array {
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
                    'statusLabel' => 'Terjadwal',
                    'statusTone' => 'default',
                    'statusDetail' => null,
                ];
            })
            ->withQueryString();

        return [
            'items' => $paginator->items(),
            'pagination' => $this->simplePaginationData($paginator),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function followUpScheduleItems(string $search = '', int $page = 1): array
    {
        $now = now();

        $paginator = ThesisDefense::query()
            ->with([
                'project.student.mahasiswaProfile',
                'project.programStudi',
                'titleVersion',
                'examiners',
                'revisions',
            ])
            ->whereIn('type', ['sempro', 'sidang'])
            ->where('status', 'completed')
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '>=', $now->copy()->subDays(45))
            ->orderByDesc('scheduled_for')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery
                        ->whereHas('project.student', function ($studentQuery) use ($search): void {
                            $studentQuery->where('name', 'like', "%{$search}%")
                                ->orWhereHas('mahasiswaProfile', function ($profileQuery) use ($search): void {
                                    $profileQuery->where('nim', 'like', "%{$search}%");
                                });
                        })
                        ->orWhereHas('project.programStudi', function ($programQuery) use ($search): void {
                            $programQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('titleVersion', function ($titleQuery) use ($search): void {
                            $titleQuery->where('title_id', 'like', "%{$search}%")
                                ->orWhere('title_en', 'like', "%{$search}%")
                                ->orWhere('proposal_summary', 'like', "%{$search}%");
                        })
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('mode', 'like', "%{$search}%");
                });
            })
            ->where(function ($query): void {
                $query->where('result', 'pass_with_revision')
                    ->orWhereHas('revisions', function ($revisionQuery): void {
                        $revisionQuery->whereIn('status', ['open', 'submitted']);
                    })
                    ->orWhereHas('examiners', function ($examinerQuery): void {
                        $examinerQuery->where(function ($pendingQuery): void {
                            $pendingQuery->whereNull('decision')
                                ->orWhereNull('score');
                        });
                    });
            })
            ->simplePaginate(perPage: 8, pageName: 'follow_up_page', page: $page)
            ->through(function (ThesisDefense $defense) use ($now): array {
                $project = $defense->project;
                $openRevisions = $defense->revisions->whereIn('status', ['open', 'submitted']);
                $hasOverdueRevision = $openRevisions->contains(function ($revision) use ($now): bool {
                    return $revision->due_at !== null && $revision->due_at->lt($now);
                });
                $hasOpenRevision = $openRevisions->isNotEmpty();
                $hasPendingGrades = $defense->examiners->isNotEmpty()
                    && $defense->examiners->contains(function ($examiner): bool {
                        return $examiner->decision === null || $examiner->score === null;
                    });

                $statusLabel = 'Perlu Tindak Lanjut';
                $statusTone = 'warning';
                $statusDetail = 'Masih ada hal yang perlu diselesaikan setelah seminar.';

                if ($hasOverdueRevision) {
                    $statusLabel = 'Revisi Terlambat';
                    $statusTone = 'danger';
                    $statusDetail = 'Batas revisi sudah lewat dan belum seluruhnya diselesaikan.';
                } elseif ($hasOpenRevision) {
                    $statusLabel = 'Revisi Berjalan';
                    $statusTone = 'warning';
                    $statusDetail = 'Hasil seminar membutuhkan perbaikan yang masih aktif.';
                } elseif ($hasPendingGrades) {
                    $statusLabel = 'Nilai Belum Lengkap';
                    $statusTone = 'muted';
                    $statusDetail = 'Masih ada penguji yang belum melengkapi keputusan atau nilainya.';
                }

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
                    'statusLabel' => $statusLabel,
                    'statusTone' => $statusTone,
                    'statusDetail' => $statusDetail,
                ];
            })
            ->withQueryString();

        return [
            'items' => $paginator->items(),
            'pagination' => $this->simplePaginationData($paginator),
        ];
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
     * @return array<string, array<string, int>>
     */
    private function advisorConcentrationTotals(): array
    {
        return ThesisSupervisorAssignment::query()
            ->with([
                'lecturer.dosenProfile.programStudi',
                'project',
            ])
            ->where('status', 'active')
            ->whereHas('project', function ($query): void {
                $query->where('state', 'active');
            })
            ->whereHas('lecturer.dosenProfile', function ($query): void {
                $query->where('is_active', true);
            })
            ->get()
            ->groupBy(function (ThesisSupervisorAssignment $assignment): string {
                $profile = $assignment->lecturer?->dosenProfile;
                $programSlug = $profile?->programStudi?->slug ?? 'umum';
                $concentration = $profile?->concentration ?? ProgramStudi::DEFAULT_GENERAL_CONCENTRATION;

                return sprintf('%s||%s', $programSlug, $concentration);
            })
            ->reduce(function (array $carry, Collection $assignments, string $key): array {
                [$programSlug, $concentration] = explode('||', $key, 2);

                $carry[$programSlug] ??= [];
                $carry[$programSlug][$concentration] = $assignments
                    ->pluck('project.student_user_id')
                    ->filter()
                    ->unique()
                    ->count();

                return $carry;
            }, []);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function semproTitles(string $search = '', string $program = '', int $page = 1): array
    {
        $paginator = ThesisProject::query()
            ->with([
                'latestTitle',
                'programStudi',
                'student.mahasiswaProfile',
                'activeSupervisorAssignments' => function ($query): void {
                    $query->orderBy('role');
                },
                'activeSupervisorAssignments.lecturer',
                'semproDefenses' => function ($query): void {
                    $query->where('status', 'completed')
                        ->orderByDesc('scheduled_for');
                },
            ])
            ->withMax([
                'semproDefenses as latest_sempro_at' => function ($query): void {
                    $query->where('status', 'completed');
                },
            ], 'scheduled_for')
            ->whereHas('activeSupervisorAssignments', function ($query): void {
                $query->where('status', 'active');
            })
            ->whereHas('semproDefenses', function ($query): void {
                $query->where('status', 'completed');
            })
            ->when($program !== '', function ($query) use ($program): void {
                $query->whereHas('programStudi', function ($programQuery) use ($program): void {
                    $programQuery->where('slug', $program);
                });
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery
                        ->whereHas('student', function ($studentQuery) use ($search): void {
                            $studentQuery->where('name', 'like', "%{$search}%")
                                ->orWhereHas('mahasiswaProfile', function ($profileQuery) use ($search): void {
                                    $profileQuery->where('nim', 'like', "%{$search}%");
                                });
                        })
                        ->orWhereHas('programStudi', function ($programQuery) use ($search): void {
                            $programQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('latestTitle', function ($titleQuery) use ($search): void {
                            $titleQuery->where('title_id', 'like', "%{$search}%")
                                ->orWhere('title_en', 'like', "%{$search}%")
                                ->orWhere('proposal_summary', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('latest_sempro_at')
            ->simplePaginate(perPage: 10, pageName: 'page', page: $page)
            ->through(function (ThesisProject $project): array {
                $latestSempro = $project->semproDefenses->first();

                return [
                    'id' => $project->id,
                    'programStudi' => $project->programStudi?->name ?? '-',
                    'programSlug' => $project->programStudi?->slug ?? 'umum',
                    'studentName' => $project->student?->name ?? '-',
                    'studentNim' => $project->student?->mahasiswaProfile?->nim ?? '-',
                    'title' => $project->latestTitle?->title_id ?? '-',
                    'titleEn' => $project->latestTitle?->title_en ?? '-',
                    'summary' => $project->latestTitle?->proposal_summary ?? '-',
                    'year' => $latestSempro?->scheduled_for?->format('Y') ?? '-',
                    'seminarDate' => $latestSempro?->scheduled_for?->locale('id')->translatedFormat('d F Y, H:i'),
                    'advisors' => $project->activeSupervisorAssignments
                        ->map(fn($assignment): array => [
                            'name' => $assignment->lecturer?->name ?? '-',
                            'label' => $assignment->role === AdvisorType::Primary->value ? 'Pembimbing 1' : 'Pembimbing 2',
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->withQueryString();

        return [
            'items' => $paginator->items(),
            'pagination' => $this->simplePaginationData($paginator),
        ];
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    private function topicPrograms(): Collection
    {
        return ThesisProject::query()
            ->with('programStudi')
            ->whereHas('activeSupervisorAssignments', function ($query): void {
                $query->where('status', 'active');
            })
            ->whereHas('semproDefenses', function ($query): void {
                $query->where('status', 'completed');
            })
            ->get()
            ->map(fn(ThesisProject $project): array => [
                'programSlug' => $project->programStudi?->slug ?? 'umum',
                'programStudi' => $project->programStudi?->name ?? '-',
            ])
            ->unique('programSlug')
            ->sortBy('programStudi')
            ->values();
    }

    private function simplePaginationData($paginator): array
    {
        return [
            'currentPage' => $paginator->currentPage(),
            'perPage' => $paginator->perPage(),
            'hasMorePages' => $paginator->hasMorePages(),
            'nextPage' => $paginator->hasMorePages() ? $paginator->currentPage() + 1 : null,
            'previousPage' => $paginator->onFirstPage() ? null : $paginator->currentPage() - 1,
        ];
    }
}
