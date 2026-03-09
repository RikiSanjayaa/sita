<?php

namespace App\Http\Controllers;

use App\Enums\AdvisorType;
use App\Enums\AppRole;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
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
        return Inertia::render('public/jadwal', [
            'upcomingSchedules' => $this->upcomingScheduleItems()->all(),
            'followUpSchedules' => $this->followUpScheduleItems()->all(),
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

    public function topics(): Response
    {
        $semproTitles = $this->semproTitles();

        return Inertia::render('public/topik', [
            'semproTitles' => $semproTitles->all(),
            'topicPrograms' => $semproTitles
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
    private function upcomingScheduleItems(): Collection
    {
        return ThesisDefense::query()
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
            ->limit(24)
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
                    'statusLabel' => 'Terjadwal',
                    'statusTone' => 'default',
                    'statusDetail' => null,
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function followUpScheduleItems(): Collection
    {
        $now = now();

        return ThesisDefense::query()
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
            ->get()
            ->filter(function (ThesisDefense $defense): bool {
                $hasOpenRevision = $defense->revisions
                    ->whereIn('status', ['open', 'submitted'])
                    ->isNotEmpty();

                $hasPendingGrades = $defense->examiners->isNotEmpty()
                    && $defense->examiners->contains(function ($examiner): bool {
                        return $examiner->decision === null || $examiner->score === null;
                    });

                return $hasOpenRevision || $hasPendingGrades || $defense->result === 'pass_with_revision';
            })
            ->take(12)
            ->map(function (ThesisDefense $defense) use ($now): array {
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
    private function semproTitles(): Collection
    {
        return ThesisProject::query()
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
            ->orderByDesc('latest_sempro_at')
            ->limit(30)
            ->get()
            ->map(function (ThesisProject $project): array {
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
            ->values();
    }
}
