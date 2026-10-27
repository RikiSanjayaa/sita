<?php

namespace App\Services;

use App\Models\KaprodiAssignment;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipDocument;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisDocument;
use App\Models\ThesisProject;
use App\Models\ThesisRevision;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Support\AcademicTerminology;
use App\Support\DefenseRevisionLog;
use App\Support\WitaDateTime;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class KaprodiPortalService
{
    public function __construct(
        private readonly MentorshipProgressService $mentorshipProgressService,
        private readonly StudentProgressSnapshot $studentProgressSnapshot,
        private readonly StudentActivityService $studentActivityService,
        private readonly DosenBimbinganService $dosenBimbinganService,
    ) {}

    /**
     * Action-first dashboard: what needs the kaprodi, this week's agenda, the students
     * who need attention first, then the passive statistics.
     *
     * @return array<string, mixed>
     */
    public function dashboard(ProgramStudi $programStudi, KaprodiAssignment $assignment): array
    {
        $projects = $this->projects($programStudi);
        $students = $this->studentProfiles($programStudi);
        $lastActivityByProject = $this->studentActivityService->latestByProject($projects);
        $rows = $this->studentRows($students, $projects, $lastActivityByProject, []);
        $actionable = $this->actionableProjects($projects, $students);

        return [
            'programStudi' => $this->programStudiSummary($programStudi),
            'actionItems' => $this->actionItems($programStudi, $assignment, $rows, $actionable),
            'upcomingAgenda' => $this->deadlines($programStudi, 8),
            'attentionStudents' => $this->attentionStudents($rows, $actionable, $projects, $lastActivityByProject),
            'stats' => [
                'students' => $students->count(),
                'archives' => $projects->whereIn('state', ['completed', 'cancelled'])->count(),
                'activeStudents' => $students->filter(fn(MahasiswaProfile $profile): bool => (bool) $profile->is_active)->count(),
                'withoutSupervisors' => $rows->filter(fn(array $row): bool => in_array('tanpa_pembimbing', $row['needsAction'], true))->count(),
                'upcomingExams' => $this->agendaDefenses($programStudi)->count(),
                'expiredMentorship' => $rows->filter(fn(array $row): bool => in_array('masa_habis', $row['needsAction'], true))->count(),
            ],
            'recentArchives' => $this->archiveRows($programStudi, 4),
            'phaseDistribution' => $this->phaseDistribution($projects),
            'defenseProgress' => $this->defenseProgress($programStudi)->take(5)->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function studentIndex(ProgramStudi $programStudi, ?User $viewer): array
    {
        $projects = $this->projects($programStudi);
        $students = $this->studentProfiles($programStudi);
        $lastActivityByProject = $this->studentActivityService->latestByProject($projects);
        $studentRows = $this->studentRows(
            $students,
            $projects,
            $lastActivityByProject,
            $this->studentProgressSnapshot->forProjects($projects, $viewer, lastActivity: $lastActivityByProject),
        );

        return [
            'programStudi' => $this->programStudiSummary($programStudi),
            'filters' => [
                'phases' => $this->phaseDistribution($projects),
                'statuses' => collect(['Aktif', 'Nonaktif'])->map(fn(string $status): array => [
                    'label' => $status,
                    'count' => $studentRows->where('status', $status)->count(),
                ])->values()->all(),
                'angkatan' => $students->pluck('angkatan')->filter()->unique()->sort()->values()->all(),
                'concentrations' => $students->pluck('concentration')->filter()->unique()->sort()->values()->all(),
            ],
            'students' => $studentRows->values()->all(),
            'archives' => $this->archiveRows($programStudi, null)->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function exams(ProgramStudi $programStudi): array
    {
        $defenses = ThesisDefense::query()
            ->whereHas('project', fn($query) => $query->current()->where('program_studi_id', $programStudi->id))
            ->with([
                'project.student.mahasiswaProfile',
                'project.latestTitle',
                ...DefenseRevisionLog::RELATIONS,
            ])
            ->latest('scheduled_for')
            ->get();

        return [
            'programStudi' => $this->programStudiSummary($programStudi),
            'summaryCards' => collect(['sempro', 'sidang'])
                ->map(fn(string $type): array => [
                    'label' => $this->defenseTypeLabel($type),
                    'value' => (string) $defenses->where('type', $type)->count(),
                    'description' => $defenses->where('type', $type)->whereIn('status', ['scheduled', 'awaiting_finalization'])->count().' perlu dipantau',
                ])
                ->values()
                ->all(),
            'progress' => $this->defenseProgress($programStudi)->values()->all(),
            'exams' => $defenses->map(fn(ThesisDefense $defense): array => [
                'id' => $defense->id,
                'projectId' => $defense->project_id,
                'type' => $this->defenseTypeLabelForProject($defense->type, $defense->project),
                'typeKey' => $defense->type,
                'status' => $this->statusLabel($defense->status),
                'statusKey' => $defense->status,
                'result' => $this->defenseResultLabel($defense),
                'averageScore' => $this->averageScore($defense),
                'scoreRequired' => $defense->requiresScore(),
                'grade' => $this->grade($this->averageScore($defense)),
                'student' => $defense->project?->student?->name ?? 'Mahasiswa',
                'terminology' => $defense->project instanceof ThesisProject
                    ? AcademicTerminology::forProject($defense->project)
                    : AcademicTerminology::neutral(),
                'nim' => $defense->project?->student?->mahasiswaProfile?->nim,
                'title' => $defense->project?->latestTitle?->title_id ?? '-',
                'attempt' => $defense->attempt_no,
                'scheduledFor' => $this->formatDefenseSchedule($defense),
                'scheduledForInput' => $defense->scheduled_for?->format('Y-m-d\TH:i') ?? '',
                'scheduledDateStartInput' => $defense->scheduled_for?->format('Y-m-d') ?? '',
                'scheduledDateEndInput' => $defense->scheduled_until?->format('Y-m-d') ?? '',
                'scheduledTimeInput' => $defense->scheduled_for?->format('H:i') ?? '',
                'location' => $defense->location,
                'mode' => $defense->mode ?? 'offline',
                'canManageSchedule' => $this->canManageDefenseSchedule($defense),
                'examiners' => $defense->examiners
                    ->sortBy('order_no')
                    ->map(fn($examiner): array => [
                        'id' => $examiner->lecturer_user_id,
                        'name' => $examiner->lecturer?->name ?? '-',
                        'role' => $this->examinerRoleLabel($examiner->role),
                        'decision' => $this->defenseDecisionLabel($defense, $examiner->decision),
                        'score' => $examiner->score,
                        'profileUrl' => route('users.profile.show', ['user' => $examiner->lecturer_user_id]),
                    ])
                    ->values()
                    ->all(),
                'revisionCount' => $defense->revisions->count(),
                'revisionLog' => DefenseRevisionLog::forDefense($defense),
                'studentProfileUrl' => route('users.profile.show', ['user' => $defense->project?->student_user_id]),
            ])->values()->all(),
            'schedulableProjects' => $this->schedulableProjects($programStudi)->values()->all(),
            'calendarEvents' => $defenses
                ->filter(fn(ThesisDefense $defense): bool => $defense->scheduled_for !== null)
                ->map(fn(ThesisDefense $defense): array => [
                    'id' => 'defense-'.$defense->id,
                    'title' => $this->defenseTypeLabelForProject($defense->type, $defense->project).' - '.$defense->project?->student?->name,
                    'topic' => $this->defenseTypeLabelForProject($defense->type, $defense->project).' #'.$defense->attempt_no,
                    'person' => $defense->project?->student?->name ?? 'Mahasiswa',
                    'category' => 'ujian',
                    'start' => $defense->scheduled_for?->toIso8601String(),
                    'end' => ($defense->scheduled_until ?? $defense->scheduled_for?->copy()->addHours(2))?->toIso8601String(),
                    'location' => $defense->location ?? '-',
                    'status' => in_array($defense->status, ['completed', 'cancelled'], true) ? $defense->status : 'scheduled',
                    'personRole' => 'student',
                    'notes' => $defense->notes,
                    'requestedBy' => 'Kaprodi',
                ])
                ->values()
                ->all(),
            'agenda' => $this->deadlines($programStudi, 8),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function lecturers(ProgramStudi $programStudi): array
    {
        return [
            'programStudi' => $this->programStudiSummary($programStudi),
            'lecturers' => $this->lecturerRows($programStudi)->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function archives(ProgramStudi $programStudi): array
    {
        return [
            'programStudi' => $this->programStudiSummary($programStudi),
            'archives' => $this->archiveRows($programStudi, null)->values()->all(),
            'documents' => $this->archiveDocuments($programStudi)->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function documents(ProgramStudi $programStudi, User $viewer): array
    {
        $canDownload = app(DocumentAccessService::class)->kaprodiCan($viewer, $programStudi->id, KaprodiAssignment::CAPABILITY_DOWNLOAD_DOCUMENTS);
        $workspaceDocuments = MentorshipDocument::query()
            ->whereHas('student.mahasiswaProfile', fn($query) => $query->where('program_studi_id', $programStudi->id))
            ->with(['student.mahasiswaProfile', 'lecturer'])
            ->latest('created_at')
            ->get()
            ->groupBy(fn(MentorshipDocument $document): string => $this->workspaceDocumentGroupKey($document))
            ->map(function (Collection $documents, string $key) use ($canDownload): array {
                /** @var MentorshipDocument $document */
                $document = $documents->sortByDesc('created_at')->first();
                $counts = $this->documentReviewCounts($documents->map(fn (MentorshipDocument $copy): string => DocumentRevisionService::displayStatus($copy)));

                return [
                    'id' => 'workspace-'.md5($key),
                    'detailUrl' => DocumentDetailService::workspaceUrl($document, 'kaprodi'),
                    'source' => 'Workspace',
                    'mahasiswa' => $document->student?->name ?? '-',
                    'nim' => $document->student?->mahasiswaProfile?->nim,
                    'title' => $document->title,
                    'file' => $document->file_name,
                    'uploadedAt' => WitaDateTime::format($document->created_at, withLabel: false),
                    'uploadedSort' => $document->created_at?->getTimestamp() ?? 0,
                    'status' => $this->aggregateDocumentStatus($documents->map(fn (MentorshipDocument $copy): string => DocumentRevisionService::displayStatus($copy))),
                    'reviewCount' => $documents->count(),
                    'pendingCount' => $counts['pending'],
                    'revisionCount' => $counts['revision'],
                    'approvedCount' => $counts['approved'],
                    'completedRevisionCount' => $counts['completed'],
                    'fileUrl' => ! $canDownload ? null : ($document->storage_path === null
                        ? $document->file_url
                        : route('files.documents.download', ['document' => $document->id, 'portal' => 'kaprodi'])),
                    'profileUrl' => route('users.profile.show', ['user' => $document->student_user_id]),
                    'reviews' => $documents
                        ->sortBy(fn(MentorshipDocument $review): string => $review->lecturer?->name ?? '')
                        ->map(fn(MentorshipDocument $review): array => [
                            'id' => $review->id,
                            'reviewer' => $review->lecturer?->name ?? 'Belum diarahkan',
                            'status' => $this->statusLabel(DocumentRevisionService::displayStatus($review)),
                            'revisionNotes' => $review->revision_notes,
                            'uploadedAt' => WitaDateTime::format($review->created_at, withLabel: false),
                            'reviewedAt' => WitaDateTime::formatOrNull($review->reviewed_at),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values();

        $thesisDocuments = ThesisDocument::query()
            ->whereHas('project', fn($query) => $query->current()->where('program_studi_id', $programStudi->id))
            ->with(['project.student', 'defense'])
            ->latest('uploaded_at')
            ->get()
            ->map(function (ThesisDocument $document) use ($canDownload): array {
                $counts = $this->documentReviewCounts(collect([$document->status]));

                return [
                    'id' => 'thesis-'.$document->id,
                    'detailUrl' => route('kaprodi.dokumen.thesis.show', ['document' => $document->id]),
                    'source' => 'Tugas Akhir',
                    'mahasiswa' => $document->project?->student?->name ?? '-',
                    'nim' => $document->project?->student?->mahasiswaProfile?->nim,
                    'title' => $document->title,
                    'file' => $document->file_name,
                    'uploadedAt' => WitaDateTime::format($document->uploaded_at),
                    'uploadedSort' => $document->uploaded_at?->getTimestamp() ?? 0,
                    'status' => $this->statusLabel($document->status),
                    'reviewCount' => 1,
                    'pendingCount' => $counts['pending'],
                    'revisionCount' => $counts['revision'],
                    'approvedCount' => $counts['approved'],
                    'completedRevisionCount' => $counts['completed'],
                    'fileUrl' => $canDownload ? route('files.thesis-documents.download', ['document' => $document->id, 'portal' => 'kaprodi']) : null,
                    'profileUrl' => route('users.profile.show', ['user' => $document->project?->student_user_id]),
                    'reviews' => [
                        [
                            'id' => $document->id,
                            'reviewer' => $document->defense === null ? 'Pembimbing/Penguji' : $this->defenseTypeLabel($document->defense->type),
                            'status' => $this->statusLabel($document->status),
                            'revisionNotes' => $document->notes,
                            'uploadedAt' => WitaDateTime::format($document->uploaded_at),
                            'reviewedAt' => null,
                        ],
                    ],
                ];
            });

        return [
            'programStudi' => $this->programStudiSummary($programStudi),
            'documentQueue' => $workspaceDocuments
                ->concat($thesisDocuments)
                ->sortByDesc('uploadedSort')
                ->map(function (array $document): array {
                    unset($document['uploadedSort']);

                    return $document;
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function studentDetail(ProgramStudi $programStudi, User $student): array
    {
        $profile = $student->mahasiswaProfile;

        abort_unless($profile !== null && (int) $profile->program_studi_id === (int) $programStudi->id, 404);

        $projects = ThesisProject::query()
            ->current()
            ->where('program_studi_id', $programStudi->id)
            ->where('student_user_id', $student->id)
            ->with([
                'latestTitle',
                'latestTitle.discussionLecturer',
                'supervisorAssignments.lecturer',
                'defenses.examiners.lecturer',
                'defenses.revisions',
                'documents',
                'events.actor',
            ])
            ->latest('started_at')
            ->get();

        return [
            'programStudi' => [
                'id' => $programStudi->id,
                'name' => $programStudi->name,
                'slug' => $programStudi->slug,
            ],
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'email' => $student->email,
                'avatar' => $student->avatar,
                'nim' => $profile->nim,
                'angkatan' => $profile->angkatan,
                'degreeLevel' => strtoupper((string) $profile->degree_level),
                'concentration' => $profile->concentration,
                'isObeCurriculum' => (bool) $profile->is_obe_curriculum,
                'status' => $profile->is_active ? 'Aktif' : 'Nonaktif',
            ],
            'projects' => $projects->map(fn(ThesisProject $project): array => $this->projectDetail($project))->values()->all(),
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, count: int}>
     */
    private function phaseDistribution(EloquentCollection $projects): array
    {
        return collect(['title_review', 'sempro', 'research', 'sidang', 'completed', 'cancelled'])
            ->map(fn(string $phase): array => [
                'key' => $phase,
                'label' => $this->phaseLabel($phase),
                'count' => $projects->where('phase', $phase)->count(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{type: string, status: string, label: string, count: int}>
     */
    private function defenseProgress(ProgramStudi $programStudi): Collection
    {
        $rows = ThesisDefense::query()
            ->whereHas('project', fn($query) => $query->current()->where('program_studi_id', $programStudi->id))
            ->get()
            ->groupBy(fn(ThesisDefense $defense): string => $defense->type.'|'.$defense->status);

        return $rows
            ->map(function (Collection $items, string $key): array {
                [$type, $status] = explode('|', $key);

                return [
                    'type' => $type,
                    'status' => $status,
                    'label' => $this->defenseTypeLabel($type).' - '.$this->statusLabel($status),
                    'count' => $items->count(),
                ];
            })
            ->sortBy('label')
            ->values()
            ->values();
    }

    /**
     * @return array<int, array{name: string, role: string, count: int}>
     */
    private function lecturerMonitoring(EloquentCollection $projects, ?int $limit = 8): Collection
    {
        $rows = $projects
            ->flatMap(function (ThesisProject $project): Collection {
                $supervisors = $project->supervisorAssignments
                    ->map(fn($assignment): array => [
                        'id' => $assignment->lecturer_user_id,
                        'name' => $assignment->lecturer?->name ?? '-',
                        'role' => $assignment->role === 'primary' ? 'Pembimbing 1' : 'Pembimbing 2',
                        'student' => $project->student?->name,
                    ]);

                $examiners = $project->defenses
                    ->flatMap(fn(ThesisDefense $defense): Collection => $defense->examiners
                        ->map(fn($examiner): array => [
                            'id' => $examiner->lecturer_user_id,
                            'name' => $examiner->lecturer?->name ?? '-',
                            'role' => $this->defenseTypeLabel($defense->type).' Penguji',
                            'student' => $project->student?->name,
                        ]));

                return $supervisors->concat($examiners);
            })
            ->groupBy(fn(array $row): string => $row['id'].'|'.$row['role'])
            ->map(fn(Collection $rows): array => [
                'name' => (string) $rows->first()['name'],
                'role' => (string) $rows->first()['role'],
                'count' => $rows->count(),
                'students' => $rows->pluck('student')->filter()->unique()->values()->all(),
            ])
            ->sortByDesc('count');

        return ($limit === null ? $rows : $rows->take($limit))->values();
    }

    /**
     * Today (WITA) through seven days ahead, as app-timezone instants for the queries.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function agendaWindow(): array
    {
        $now = CarbonImmutable::now(WitaDateTime::TIMEZONE);

        return [
            $now->startOfDay()->setTimezone(config('app.timezone')),
            $now->addDays(7)->endOfDay()->setTimezone(config('app.timezone')),
        ];
    }

    /**
     * Scheduled exams inside the agenda window. The agenda list and the upcoming-exam
     * statistic both read this, so they can never disagree on what counts.
     *
     * @return Builder<ThesisDefense>
     */
    private function agendaDefenses(ProgramStudi $programStudi): Builder
    {
        [$windowStart, $windowEnd] = $this->agendaWindow();

        return ThesisDefense::query()
            ->whereHas('project', fn($query) => $query->current()->where('program_studi_id', $programStudi->id))
            ->whereNotNull('scheduled_for')
            ->whereBetween('scheduled_for', [$windowStart, $windowEnd])
            ->whereIn('status', ['scheduled', 'awaiting_finalization']);
    }

    /**
     * Day label, clock time and today flag of an agenda entry, all in WITA.
     *
     * @return array{dayLabel: string, time: string, isToday: bool}
     */
    private function agendaTiming(CarbonInterface $at): array
    {
        $today = CarbonImmutable::now(WitaDateTime::TIMEZONE)->startOfDay();
        $local = CarbonImmutable::instance($at)->setTimezone(WitaDateTime::TIMEZONE)->locale('id');
        $isToday = $local->isSameDay($today);

        return [
            'dayLabel' => $isToday
                ? 'Hari ini, '.$local->translatedFormat('j F')
                : ($local->isSameDay($today->addDay()) ? 'Besok, '.$local->translatedFormat('j F') : $local->translatedFormat('l, j F')),
            'time' => $local->format('H.i'),
            'isToday' => $isToday,
        ];
    }

    /**
     * @return array<int, array{id: string, badge: string, title: string, subtitle: string, date: string, dayLabel: string, time: string, isToday: bool}>
     */
    private function deadlines(ProgramStudi $programStudi, int $limit = 8): array
    {
        [$windowStart, $windowEnd] = $this->agendaWindow();

        $defenseDeadlines = $this->agendaDefenses($programStudi)
            ->with('project.student')
            ->orderBy('scheduled_for')
            ->limit(6)
            ->get()
            ->map(fn(ThesisDefense $defense): array => [
                'id' => 'defense-'.$defense->id,
                'badge' => $this->defenseTypeLabel($defense->type),
                'title' => $defense->project?->student?->name ?? 'Mahasiswa',
                'subtitle' => $this->statusLabel($defense->status).' - '.$defense->location,
                'date' => WitaDateTime::format($defense->scheduled_for),
                ...$this->agendaTiming($defense->scheduled_for),
            ]);

        $revisionDeadlines = ThesisRevision::query()
            ->whereHas('project', fn($query) => $query->current()->where('program_studi_id', $programStudi->id))
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$windowStart, $windowEnd])
            ->whereNull('resolved_at')
            ->with('project.student')
            ->orderBy('due_at')
            ->limit(6)
            ->get()
            ->map(fn(ThesisRevision $revision): array => [
                'id' => 'revision-'.$revision->id,
                'badge' => 'Revisi',
                'title' => $revision->project?->student?->name ?? 'Mahasiswa',
                'subtitle' => $revision->notes ?? 'Deadline revisi',
                'date' => WitaDateTime::format($revision->due_at),
                ...$this->agendaTiming($revision->due_at),
            ]);

        return $defenseDeadlines
            ->concat($revisionDeadlines)
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function studentRows(EloquentCollection $students, EloquentCollection $projects, array $lastActivityByProject, array $progress): Collection
    {
        $mentorshipProgress = $this->mentorshipProgressService->forProjects($projects);

        return $students
            ->sortBy(fn(MahasiswaProfile $profile): string => $profile->user?->name ?? '')
            ->map(function (MahasiswaProfile $profile) use ($lastActivityByProject, $mentorshipProgress, $progress, $projects): array {
                $project = $projects
                    ->where('student_user_id', $profile->user_id)
                    ->sortByDesc('started_at')
                    ->first();

                $risk = $this->progressRisk(
                    $project,
                    $lastActivityByProject[$project?->id] ?? null,
                );

                return [
                    'id' => $profile->user_id,
                    'projectId' => $project?->id,
                    'canManageSupervisors' => $project instanceof ThesisProject && $project->state === 'active',
                    'name' => $profile->user?->name ?? '-',
                    'nim' => $profile->nim,
                    'avatar' => $profile->user?->avatar,
                    'status' => $profile->is_active ? 'Aktif' : 'Nonaktif',
                    'angkatan' => $profile->angkatan,
                    'degreeLevel' => strtoupper((string) $profile->degree_level),
                    'terminology' => AcademicTerminology::forDegreeLevel($profile->degree_level, (bool) $profile->is_obe_curriculum),
                    'concentration' => $profile->concentration,
                    'isObeCurriculum' => (bool) $profile->is_obe_curriculum,
                    'phase' => $this->phaseLabel($project?->phase),
                    'phaseKey' => $project?->phase ?? 'none',
                    'projectState' => $this->statusLabel($project?->state),
                    'projectStateKey' => $project?->state ?? 'none',
                    'mentorshipTicket' => $project instanceof ThesisProject ? [
                        'suspended' => $project->isMentorshipSuspended(),
                        'validUntil' => $project->mentorship_valid_until?->format('Y-m-d'),
                    ] : null,
                    'title' => $project?->latestTitle?->title_id ?? '-',
                    'advisors' => $project?->activeSupervisorAssignments
                        ->map(fn($assignment): string => $assignment->lecturer?->name ?? '-')
                        ->values()
                        ->all() ?? [],
                    'discussionLecturer' => $this->proposedLecturerPayload($project?->latestTitle?->discussionLecturer),
                    'supervisorAssignments' => $project?->activeSupervisorAssignments
                        ->sortBy('role')
                        ->map(fn($assignment): array => [
                            'lecturerUserId' => $assignment->lecturer_user_id,
                            'name' => $assignment->lecturer?->name ?? '-',
                            'role' => $assignment->role === 'primary' ? 'Pembimbing 1' : 'Pembimbing 2',
                        ])
                        ->values()
                        ->all() ?? [],
                    'mentorshipProgress' => $project instanceof ThesisProject ? $mentorshipProgress[$project->getKey()] ?? [] : [],
                    'progressRisk' => $risk,
                    'needsAction' => $this->needsActionFlags(
                        $project,
                        (bool) $profile->is_active,
                        $mentorshipProgress[$project?->getKey()] ?? [],
                        $risk,
                    ),
                    'progress' => $progress[$project?->id] ?? null,
                    'profileUrl' => route('users.profile.show', ['user' => $profile->user_id]),
                ];
            })
            ->values();
    }

    /**
     * @return array{lecturerUserId: int, name: string}|null
     */
    private function proposedLecturerPayload(?User $lecturer): ?array
    {
        if ($lecturer === null) {
            return null;
        }

        return [
            'lecturerUserId' => (int) $lecturer->id,
            'name' => $lecturer->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function projectDetail(ThesisProject $project): array
    {
        return [
            'id' => $project->id,
            'title' => $project->latestTitle?->title_id ?? '-',
            'discussionLecturer' => $this->proposedLecturerPayload($project->latestTitle?->discussionLecturer),
            'phase' => $this->phaseLabel($project->phase),
            'state' => $this->statusLabel($project->state),
            'startedAt' => WitaDateTime::format($project->started_at, 'd M Y', false),
            'completedAt' => WitaDateTime::format($project->completed_at, 'd M Y', false),
            'mentorshipProgress' => $this->mentorshipProgressService->forProject($project),
            'supervisors' => $project->supervisorAssignments
                ->sortBy('role')
                ->map(fn($assignment): array => [
                    'name' => $assignment->lecturer?->name ?? '-',
                    'role' => $assignment->role === 'primary' ? 'Pembimbing 1' : 'Pembimbing 2',
                    'status' => $this->statusLabel($assignment->status),
                ])
                ->values()
                ->all(),
            'defenses' => $project->defenses
                ->sortBy('scheduled_for')
                ->map(fn(ThesisDefense $defense): array => [
                    'id' => $defense->id,
                    'type' => $this->defenseTypeLabel($defense->type),
                    'status' => $this->statusLabel($defense->status),
                    'result' => $this->statusLabel($defense->result),
                    'scheduledFor' => WitaDateTime::format($defense->scheduled_for),
                    'location' => $defense->location,
                    'examiners' => $defense->examiners
                        ->map(fn($examiner): array => [
                            'name' => $examiner->lecturer?->name ?? '-',
                            'role' => $this->statusLabel($examiner->role),
                            'decision' => $this->statusLabel($examiner->decision),
                            'score' => $examiner->score,
                        ])
                        ->values()
                        ->all(),
                    'revisions' => $defense->revisions
                        ->map(fn($revision): array => [
                            'status' => $this->statusLabel($revision->status),
                            'dueAt' => WitaDateTime::format($revision->due_at),
                            'notes' => $revision->notes,
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'documents' => $project->documents
                ->sortByDesc('uploaded_at')
                ->map(fn($document): array => [
                    'title' => $document->title,
                    'kind' => $this->statusLabel($document->kind),
                    'status' => $this->statusLabel($document->status),
                    'version' => $document->version_no,
                    'uploadedAt' => WitaDateTime::format($document->uploaded_at),
                ])
                ->values()
                ->all(),
            'events' => $project->events
                ->sortByDesc('occurred_at')
                ->take(8)
                ->map(fn($event): array => [
                    'label' => $event->label,
                    'description' => $event->description,
                    'actor' => $event->actor?->name,
                    'occurredAt' => WitaDateTime::format($event->occurred_at),
                ])
                ->values()
                ->all(),
        ];
    }

    private function progressRisk(?ThesisProject $project, ?CarbonInterface $lastStudentActivity): array
    {
        $terms = AcademicTerminology::neutral();
        if (! $project instanceof ThesisProject) {
            return [
                'level' => 'medium',
                'label' => 'Perlu Data',
                'description' => 'Mahasiswa belum memiliki '.$terms['finalWorkLower'].' yang tercatat.',
                'lastActivityAt' => '-',
                'lastActivityLabel' => 'Belum ada '.$terms['finalWorkLower'],
                'daysIdle' => null,
                'signals' => ['Belum ada '.$terms['finalWorkLower']],
            ];
        }
        if (in_array($project->state, ['completed', 'cancelled'], true)) {
            return [
                'level' => 'low',
                'label' => 'Arsip',
                'description' => $terms['finalWork'].' sudah masuk arsip prodi.',
                'lastActivityAt' => WitaDateTime::format($project->completed_at ?? $project->cancelled_at ?? $project->updated_at, 'd M Y', false),
                'lastActivityLabel' => $terms['finalWork'].' selesai/diarsipkan',
                'daysIdle' => null,
                'signals' => [$terms['finalWork'].' arsip'],
            ];
        }

        $latestActivity = $this->latestProjectActivity($project, $lastStudentActivity);
        $daysIdle = $latestActivity['at'] === null
            ? null
            : ($latestActivity['at']->isFuture() ? 0 : (int) $latestActivity['at']->diffInDays(now()));
        $thresholds = $this->progressRiskThresholds($project->phase);
        $signals = [];

        if ($project->activeSupervisorAssignments->isEmpty()) {
            $signals[] = 'Belum ada pembimbing aktif';
        }

        $mentorshipLapsed = $project->isMentorshipSuspended();

        if ($mentorshipLapsed) {
            $signals[] = 'Masa aktif bimbingan habis';
        }

        $openRevisions = $project->defenses
            ->flatMap(fn(ThesisDefense $defense): Collection => $defense->revisions)
            ->whereNull('resolved_at')
            ->count();

        if ($openRevisions > 0) {
            $signals[] = "{$openRevisions} revisi terbuka";
        }

        if ($daysIdle === null) {
            $level = 'high';
            $label = 'Risiko Telat';
            $description = 'Belum ada aktivitas progres yang dapat dibaca dari log.';
            $signals[] = 'Belum ada aktivitas';
        } elseif ($daysIdle >= $thresholds['high']) {
            $level = 'high';
            $label = 'Risiko Telat';
            $description = "Tidak ada aktivitas progres selama {$daysIdle} hari.";
        } elseif ($daysIdle >= $thresholds['medium'] || $signals !== []) {
            $level = 'medium';
            $label = 'Perlu Dipantau';
            $description = match (true) {
                $daysIdle >= $thresholds['medium'] => "Aktivitas terakhir {$daysIdle} hari lalu.",
                $mentorshipLapsed => 'Masa aktif bimbingan habis.',
                default => 'Ada sinyal progres yang perlu dipantau.',
            };
        } else {
            $level = 'low';
            $label = 'Terkendali';
            $description = 'Aktivitas progres masih relatif baru.';
        }

        if ($signals === []) {
            $signals[] = $latestActivity['label'];
        }

        return [
            'level' => $level,
            'label' => $label,
            'description' => $description,
            'lastActivityAt' => $latestActivity['at'] === null
                ? '-'
                : WitaDateTime::format($latestActivity['at'], 'd M Y', false),
            'lastActivityLabel' => $latestActivity['label'],
            'daysIdle' => $daysIdle,
            'signals' => array_values(array_unique($signals)),
        ];
    }

    /**
     * Student activity (StudentActivityService, the same value the progress peek shows)
     * is the last activity whenever it exists. Project-side signals are only a fallback
     * for projects without any, so one row never shows two different dates.
     *
     * @return array{at: mixed, label: string}
     */
    private function latestProjectActivity(ThesisProject $project, ?CarbonInterface $lastStudentActivity): array
    {
        if ($lastStudentActivity !== null) {
            return ['at' => $lastStudentActivity, 'label' => 'Aktivitas mahasiswa'];
        }

        $terms = AcademicTerminology::neutral();
        $candidates = collect([
            [
                'at' => $project->updated_at ?? $project->started_at,
                'label' => 'Update '.$terms['finalWork'],
            ],
            [
                'at' => $project->events->max('occurred_at'),
                'label' => 'Log progres',
            ],
            [
                'at' => $project->documents->max('uploaded_at'),
                'label' => 'Dokumen terakhir',
            ],
            [
                'at' => $project->defenses->max('scheduled_for'),
                'label' => 'Jadwal ujian',
            ],
        ])->filter(fn(array $candidate): bool => $candidate['at'] !== null);

        if ($candidates->isEmpty()) {
            return ['at' => null, 'label' => 'Belum ada aktivitas'];
        }

        return $candidates
            ->sortByDesc(fn(array $candidate): int => $candidate['at']->getTimestamp())
            ->first();
    }

    /**
     * @return array{medium: int, high: int}
     */
    private function progressRiskThresholds(?string $phase): array
    {
        return match ($phase) {
            'research' => ['medium' => 30, 'high' => 60],
            'title_review', 'sempro', 'sidang' => ['medium' => 21, 'high' => 45],
            default => ['medium' => 21, 'high' => 45],
        };
    }

    private function phaseLabel(?string $phase): string
    {
        return match ($phase) {
            'cancelled' => 'Dibatalkan',
            default => AcademicTerminology::phaseLabel($phase),
        };
    }

    private function defenseTypeLabel(?string $type): string
    {
        return AcademicTerminology::defenseLabel(AcademicTerminology::neutral(), $type);
    }

    private function defenseTypeLabelForProject(?string $type, ?ThesisProject $project): string
    {
        $terminology = $project instanceof ThesisProject
            ? AcademicTerminology::forProject($project)
            : AcademicTerminology::neutral();

        return match ($type) {
            'sempro' => $terminology['proposalExamShort'],
            'sidang' => $terminology['finalExam'],
            default => 'Ujian',
        };
    }

    private function statusLabel(?string $status): string
    {
        if ($status === null || $status === '') {
            return '-';
        }

        return match ($status) {
            'active' => 'Aktif',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            'scheduled' => 'Terjadwal',
            'awaiting_finalization' => 'Menunggu Finalisasi',
            'pending' => 'Menunggu',
            'pass' => 'Lulus',
            'pass_with_revision' => 'Lulus',
            'fail' => 'Tidak Lulus',
            'approved' => 'Disetujui',
            'needs_revision' => 'Perlu Revisi',
            'revision_completed' => 'Revisi selesai',
            'open' => 'Terbuka',
            'submitted' => 'Dikirim',
            'resolved' => 'Selesai',
            default => str($status)->replace('_', ' ')->headline()->toString(),
        };
    }

    private function defenseResultLabel(ThesisDefense $defense): string
    {
        return match ($defense->result) {
            'pending' => 'Menunggu',
            'pass' => 'Lulus',
            'pass_with_revision' => $defense->type === 'sidang' ? 'Lulus dengan Syarat' : 'Lulus',
            'fail' => 'Tidak Lulus',
            default => $this->statusLabel($defense->result),
        };
    }

    private function defenseDecisionLabel(ThesisDefense $defense, ?string $decision): string
    {
        return match ($decision) {
            null, '', 'pending' => 'Menunggu',
            'pass' => 'Lulus',
            'pass_with_revision' => $defense->type === 'sidang' ? 'Lulus dengan Syarat' : 'Lulus',
            'fail' => 'Tidak Lulus',
            default => $this->statusLabel($decision),
        };
    }

    private function formatDefenseSchedule(ThesisDefense $defense): string
    {
        return WitaDateTime::translatedDateRange($defense->scheduled_for, $defense->scheduled_until);
    }

    private function workspaceDocumentGroupKey(MentorshipDocument $document): string
    {
        return implode('|', [
            $document->student_user_id,
            $document->document_group ?? 'legacy-'.$document->id,
            $document->version_number ?: 1,
            $document->file_name ?: $document->title,
        ]);
    }

    private function aggregateDocumentStatus(Collection $statuses): string
    {
        if ($statuses->contains('needs_revision')) {
            return 'Perlu Revisi';
        }
        if ($statuses->contains('revision_completed')) {
            return 'Revisi selesai';
        }

        if ($statuses->contains(fn(?string $status): bool => $status !== 'approved')) {
            return 'Perlu Review';
        }

        return 'Disetujui';
    }

    /**
     * @return array{pending: int, revision: int, approved: int, completed: int}
     */
    private function documentReviewCounts(Collection $statuses): array
    {
        return [
            'pending' => $statuses->filter(fn(?string $status): bool => ! in_array($status, ['approved', 'needs_revision', 'revision_completed'], true))->count(),
            'revision' => $statuses->filter(fn(?string $status): bool => $status === 'needs_revision')->count(),
            'approved' => $statuses->filter(fn(?string $status): bool => $status === 'approved')->count(),
            'completed' => $statuses->filter(fn(?string $status): bool => $status === 'revision_completed')->count(),
        ];
    }

    private function examinerRoleLabel(?string $role): string
    {
        return match ($role) {
            'primary_supervisor' => 'Pembimbing 1',
            'secondary_supervisor' => 'Pembimbing 2',
            'examiner' => 'Penguji',
            default => $this->statusLabel($role),
        };
    }

    private function averageScore(ThesisDefense $defense): ?float
    {
        $scores = $defense->examiners
            ->pluck('score')
            ->filter(fn($score): bool => $score !== null)
            ->map(fn($score): float => (float) $score);

        if ($scores->isEmpty()) {
            return null;
        }

        return round($scores->average(), 2);
    }

    private function canManageDefenseSchedule(ThesisDefense $defense): bool
    {
        return $defense->project?->state === 'active'
            && ! in_array($defense->status, ['awaiting_finalization', 'completed', 'cancelled'], true);
    }

    private function schedulableProjects(ProgramStudi $programStudi): Collection
    {
        return ThesisProject::query()
            ->current()
            ->where('program_studi_id', $programStudi->id)
            ->where('state', 'active')
            ->with([
                'student.mahasiswaProfile',
                'latestTitle',
                'activeSupervisorAssignments.lecturer',
                'defenses.examiners.lecturer',
            ])
            ->latest('updated_at')
            ->get()
            ->map(fn(ThesisProject $project): array => [
                'id' => $project->id,
                'student' => $project->student?->name ?? 'Mahasiswa',
                'nim' => $project->student?->mahasiswaProfile?->nim,
                'title' => $project->latestTitle?->title_id ?? '-',
                'terminology' => AcademicTerminology::forProject($project),
                'phase' => $this->phaseLabel($project->phase),
                'supervisors' => $project->activeSupervisorAssignments
                    ->sortBy('role')
                    ->map(fn($assignment): array => [
                        'id' => $assignment->lecturer_user_id,
                        'name' => $assignment->lecturer?->name ?? '-',
                        'role' => $assignment->role === 'primary' ? 'Pembimbing 1' : 'Pembimbing 2',
                    ])
                    ->values()
                    ->all(),
                'mentorshipProgress' => $this->mentorshipProgressService->forProject($project),
                'latestSempro' => $this->latestDefenseSummary($project, 'sempro'),
                'latestSidang' => $this->latestDefenseSummary($project, 'sidang'),
            ])
            ->values();
    }

    private function latestDefenseSummary(ThesisProject $project, string $type): ?array
    {
        $defense = $project->defenses
            ->where('type', $type)
            ->sortByDesc('attempt_no')
            ->first();

        if (! $defense instanceof ThesisDefense) {
            return null;
        }

        return [
            'id' => $defense->id,
            'attempt' => $defense->attempt_no,
            'status' => $this->statusLabel($defense->status),
            'statusKey' => $defense->status,
            'resultKey' => $defense->result,
            'scheduledFor' => $this->formatDefenseSchedule($defense),
            'scheduledForInput' => $defense->scheduled_for?->format('Y-m-d\TH:i') ?? '',
            'scheduledDateStartInput' => $defense->scheduled_for?->format('Y-m-d') ?? '',
            'scheduledDateEndInput' => $defense->scheduled_until?->format('Y-m-d') ?? '',
            'scheduledTimeInput' => $defense->scheduled_for?->format('H:i') ?? '',
            'location' => $defense->location,
            'mode' => $defense->mode ?? 'offline',
            'canManageSchedule' => $this->canManageDefenseSchedule($defense),
            'examiners' => $defense->examiners
                ->sortBy('order_no')
                ->map(fn($examiner): array => [
                    'id' => $examiner->lecturer_user_id,
                    'name' => $examiner->lecturer?->name ?? '-',
                    'role' => $this->examinerRoleLabel($examiner->role),
                ])
                ->values()
                ->all(),
        ];
    }

    private function grade(?float $score): ?string
    {
        if ($score === null) {
            return null;
        }

        return match (true) {
            $score <= 20 => 'E',
            $score <= 40 => 'D',
            $score <= 50 => 'C',
            $score <= 60 => 'C+',
            $score <= 70 => 'B',
            $score <= 80 => 'B+',
            default => 'A',
        };
    }

    /**
     * @return array{id: int, name: string, slug: string}
     */
    private function programStudiSummary(ProgramStudi $programStudi): array
    {
        return [
            'id' => $programStudi->id,
            'name' => $programStudi->name,
            'slug' => $programStudi->slug,
        ];
    }

    private function projects(ProgramStudi $programStudi): EloquentCollection
    {
        return ThesisProject::query()
            ->current()
            ->where('program_studi_id', $programStudi->id)
            ->with([
                'student.mahasiswaProfile',
                'latestTitle',
                'latestTitle.discussionLecturer',
                'supervisorAssignments.lecturer',
                'activeSupervisorAssignments.lecturer',
                'defenses.examiners.lecturer',
                'defenses.revisions',
                'documents',
                'events',
            ])
            ->latest('updated_at')
            ->get();
    }

    private function studentProfiles(ProgramStudi $programStudi): EloquentCollection
    {
        return MahasiswaProfile::query()
            ->where('program_studi_id', $programStudi->id)
            ->with('user')
            ->get();
    }

    /**
     * A project is "actionable" while the student is still being supervised: active,
     * not archived and a student who is not deactivated. Every action queue and the
     * attention list read only these, so archived students never show up there.
     */
    private function isActionable(ThesisProject $project, bool $studentActive): bool
    {
        return $studentActive && $project->state === 'active' && ! $project->isArchived();
    }

    /**
     * @return EloquentCollection<int, ThesisProject>
     */
    private function actionableProjects(EloquentCollection $projects, EloquentCollection $students): EloquentCollection
    {
        $activeStudentIds = $students
            ->filter(fn(MahasiswaProfile $profile): bool => (bool) $profile->is_active)
            ->pluck('user_id')
            ->flip();

        return $projects
            ->filter(fn(ThesisProject $project): bool => $this->isActionable($project, $activeStudentIds->has($project->student_user_id)))
            ->values();
    }

    /**
     * Row flags the Mahasiswa Prodi filter and the dashboard queue share, so both count
     * the same students: masa_habis, tanpa_pembimbing, bimbingan_kurang, tidak_aktif.
     *
     * @param  array<int, array<string, mixed>>  $mentorshipProgress
     * @param  array<string, mixed>  $risk
     * @return list<string>
     */
    private function needsActionFlags(?ThesisProject $project, bool $studentActive, array $mentorshipProgress, array $risk): array
    {
        if (! $project instanceof ThesisProject || ! $this->isActionable($project, $studentActive)) {
            return [];
        }

        $flags = [];

        if ($project->isMentorshipSuspended()) {
            $flags[] = 'masa_habis';
        }

        $passedSempro = in_array($project->phase, ['research', 'sidang'], true)
            || $project->defenses->contains(fn(ThesisDefense $defense): bool => $defense->type === 'sempro'
                && $defense->status === 'completed'
                && in_array($defense->result, ThesisProject::PUBLIC_TITLE_SEMPRO_RESULTS, true));

        if ($passedSempro && $project->activeSupervisorAssignments->isEmpty()) {
            $flags[] = 'tanpa_pembimbing';
        }

        if ($project->phase === 'sidang' && $this->mentorshipProgressService->unmet($mentorshipProgress) !== []) {
            $flags[] = 'bimbingan_kurang';
        }

        if (($risk['level'] ?? null) === 'high') {
            $flags[] = 'tidak_aktif';
        }

        return $flags;
    }

    /**
     * "Perlu tindakan", most urgent first. Scheduling and supervisor items need the matching
     * kaprodi capability; items with a zero count are left out.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  EloquentCollection<int, ThesisProject>  $actionable
     * @return list<array{id: string, label: string, description: string, value: int, href: string, tone: string, actionLabel: string, names: list<string>}>
     */
    private function actionItems(ProgramStudi $programStudi, KaprodiAssignment $assignment, Collection $rows, EloquentCollection $actionable): array
    {
        $terms = AcademicTerminology::neutral();
        $flagged = fn(string $flag): Collection => $rows
            ->filter(fn(array $row): bool => in_array($flag, $row['needsAction'], true))
            ->pluck('name');
        $needsSchedule = fn(string $phase, string $type): Collection => $actionable
            ->filter(fn(ThesisProject $project): bool => $project->phase === $phase && $this->needsDefenseSchedule($project, $type))
            ->map(fn(ThesisProject $project): string => $project->student?->name ?? 'Mahasiswa');
        $item = fn(string $id, string $label, string $description, string $href, string $tone, string $actionLabel, Collection $names): ?array => $names->isEmpty() ? null : [
            'id' => $id,
            'label' => $label,
            'description' => $description,
            'value' => $names->count(),
            'href' => $href,
            'tone' => $tone,
            'actionLabel' => $actionLabel,
            'names' => $names->values()->all(),
        ];

        $awaitingFinalization = ThesisDefense::query()
            ->whereHas('project', fn($query) => $query
                ->current()
                ->where('program_studi_id', $programStudi->id)
                ->where('state', 'active')
                ->whereNull('archived_at'))
            ->where('status', 'awaiting_finalization')
            ->with('project.student')
            ->get()
            ->map(fn(ThesisDefense $defense): string => $defense->project?->student?->name ?? 'Mahasiswa');

        $items = [
            $item(
                'awaiting-finalization',
                'Ujian menunggu finalisasi',
                'Ujian sudah dilaksanakan, hasilnya belum difinalisasi.',
                route('kaprodi.sempro-sidang', ['status' => 'awaiting_finalization']),
                'warning',
                'Buka ujian',
                $awaitingFinalization,
            ),
            $item(
                'mentorship-lapsed',
                'Masa aktif bimbingan habis',
                'Penjadwalan bimbingan ditangguhkan sampai admin memperpanjang masa aktifnya.',
                route('kaprodi.mahasiswa.index', ['activeFocus' => 'masa_habis']),
                'danger',
                'Lihat mahasiswa',
                $flagged('masa_habis'),
            ),
            $assignment->hasCapability(KaprodiAssignment::CAPABILITY_MANAGE_SUPERVISORS) ? $item(
                'without-supervisor',
                'Pembimbing belum ditetapkan',
                'Sudah melewati '.$terms['proposalExamShort'].', belum ada pembimbing aktif.',
                route('kaprodi.mahasiswa.index', ['activeFocus' => 'tanpa_pembimbing']),
                'warning',
                'Tetapkan pembimbing',
                $flagged('tanpa_pembimbing'),
            ) : null,
            $assignment->hasCapability(KaprodiAssignment::CAPABILITY_SCHEDULE_SIDANG) ? $item(
                'schedule-sidang',
                'Siap dijadwalkan '.$terms['finalExamShort'],
                'Belum punya jadwal '.$terms['finalExamShort'].'.',
                route('kaprodi.sempro-sidang', ['jadwalkan' => 'sidang']),
                'warning',
                'Jadwalkan',
                $needsSchedule('sidang', 'sidang'),
            ) : null,
            $assignment->hasCapability(KaprodiAssignment::CAPABILITY_SCHEDULE_SEMPRO) ? $item(
                'schedule-sempro',
                'Siap dijadwalkan '.$terms['proposalExamShort'],
                'Belum punya jadwal '.$terms['proposalExamShort'].'.',
                route('kaprodi.sempro-sidang', ['jadwalkan' => 'sempro']),
                'warning',
                'Jadwalkan',
                $needsSchedule('sempro', 'sempro'),
            ) : null,
            $item(
                'inactive',
                'Belum ada aktivitas 30 hari terakhir',
                'Tidak ada aktivitas progres dalam waktu lama.',
                route('kaprodi.mahasiswa.index', ['activeFocus' => 'tidak_aktif']),
                'danger',
                'Lihat mahasiswa',
                $flagged('tidak_aktif'),
            ),
            $item(
                'mentorship-minimum',
                'Minimal bimbingan belum terpenuhi',
                'Menjelang '.$terms['finalExamShort'].', jumlah bimbingan belum mencapai minimal.',
                route('kaprodi.mahasiswa.index', ['activeFocus' => 'bimbingan_kurang']),
                'warning',
                'Lihat mahasiswa',
                $flagged('bimbingan_kurang'),
            ),
            $assignment->hasCapability(KaprodiAssignment::CAPABILITY_MANAGE_LECTURER_QUOTA) ? $item(
                'over-quota',
                'Dosen melebihi kuota bimbingan',
                'Jumlah mahasiswa bimbingan aktif melewati kuota dosen.',
                route('kaprodi.dosen-prodi'),
                'warning',
                'Atur kuota',
                $this->lecturersOverQuota($actionable),
            ) : null,
        ];

        // Only the leading card carries a name (at most one); the rest are counts only.
        return collect($items)
            ->filter()
            ->values()
            ->map(fn(array $item, int $index): array => [
                ...$item,
                'names' => $index === 0 ? array_slice($item['names'], 0, 1) : [],
            ])
            ->all();
    }

    /**
     * The latest attempt of the exam is missing, cancelled or failed, so a new one can be scheduled.
     */
    private function needsDefenseSchedule(ThesisProject $project, string $type): bool
    {
        $latest = $project->defenses
            ->where('type', $type)
            ->sortByDesc('attempt_no')
            ->first();

        return ! $latest instanceof ThesisDefense
            || in_array($latest->status, ['draft', 'cancelled'], true)
            || ($latest->status === 'completed' && $latest->result === 'fail');
    }

    /**
     * Names of lecturers whose active students (same rule as the Dosen Prodi page) exceed
     * their quota.
     *
     * @param  EloquentCollection<int, ThesisProject>  $actionable
     * @return Collection<int, string>
     */
    private function lecturersOverQuota(EloquentCollection $actionable): Collection
    {
        $studentsByLecturer = [];

        foreach ($actionable as $project) {
            foreach ($project->activeSupervisorAssignments as $assignment) {
                $studentsByLecturer[$assignment->lecturer_user_id][$project->student_user_id] = true;
            }
        }

        if ($studentsByLecturer === []) {
            return collect();
        }

        return User::query()
            ->whereIn('id', array_keys($studentsByLecturer))
            ->with('dosenProfile')
            ->orderBy('name')
            ->get()
            ->filter(fn(User $lecturer): bool => count($studentsByLecturer[$lecturer->id]) > $this->dosenBimbinganService->lecturerQuota($lecturer))
            ->pluck('name')
            ->values();
    }

    /**
     * Students who need attention, worst first: lapsed masa aktif bimbingan, inactive,
     * mentorship minimum unmet, no supervisor, then "perlu dipantau". Each carries the
     * StudentProgressSnapshot card payload, built only for the shown students.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{total: int, students: list<array<string, mixed>>}
     */
    private function attentionStudents(Collection $rows, EloquentCollection $actionable, EloquentCollection $projects, array $lastActivityByProject, int $limit = 6): array
    {
        $problemLabels = [
            'masa_habis' => 'Masa aktif bimbingan habis',
            'bimbingan_kurang' => 'Minimal bimbingan belum terpenuhi',
            'tanpa_pembimbing' => 'Belum ada pembimbing aktif',
        ];
        $rank = static function (array $row): int {
            foreach (['masa_habis', 'tidak_aktif', 'bimbingan_kurang', 'tanpa_pembimbing'] as $position => $flag) {
                if (in_array($flag, $row['needsAction'], true)) {
                    return $position;
                }
            }

            return 4;
        };
        $actionableIds = $actionable->pluck('id')->flip();

        $ranked = $rows
            ->filter(fn(array $row): bool => $actionableIds->has($row['projectId'])
                && ($row['needsAction'] !== [] || $row['progressRisk']['level'] === 'medium'))
            ->sortBy(fn(array $row): array => [$rank($row), $row['name']])
            ->values();
        $shown = $ranked->take($limit);
        $snapshots = $this->studentProgressSnapshot->forProjects(
            $projects->whereIn('id', $shown->pluck('projectId')->all())->values(),
            null,
            detailForAll: true,
            lastActivity: $lastActivityByProject,
        );

        return [
            'total' => $ranked->count(),
            'students' => $shown->map(function (array $row) use ($problemLabels, $snapshots): array {
                $problems = collect($row['needsAction'])
                    ->map(fn(string $flag): string => $flag === 'tidak_aktif' ? $row['progressRisk']['description'] : $problemLabels[$flag])
                    ->values()
                    ->all();

                return [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'nim' => $row['nim'],
                    'avatar' => $row['avatar'],
                    'profileUrl' => $row['profileUrl'],
                    'level' => $row['progressRisk']['level'] === 'high' || array_intersect(['masa_habis', 'tidak_aktif'], $row['needsAction']) !== []
                        ? 'high'
                        : 'medium',
                    'problems' => $problems === [] ? [$row['progressRisk']['description']] : $problems,
                    'progress' => $snapshots[$row['projectId']] ?? null,
                ];
            })->values()->all(),
        ];
    }

    private function archiveRows(ProgramStudi $programStudi, ?int $limit): Collection
    {
        $query = ThesisProject::query()
            ->current()
            ->where('program_studi_id', $programStudi->id)
            ->where('state', 'completed')
            ->with([
                'student.mahasiswaProfile',
                'latestTitle',
                'documents',
                'defenses',
            ])
            ->latest('completed_at')
            ->latest('cancelled_at')
            ->latest('updated_at');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get()
            ->map(fn(ThesisProject $project): array => [
                'id' => $project->id,
                'studentId' => $project->student_user_id,
                'student' => $project->student?->name ?? 'Mahasiswa',
                'nim' => $project->student?->mahasiswaProfile?->nim,
                'avatar' => $project->student?->avatar,
                'angkatan' => $project->student?->mahasiswaProfile?->angkatan,
                'degreeLevel' => strtoupper((string) $project->student?->mahasiswaProfile?->degree_level),
                'concentration' => $project->student?->mahasiswaProfile?->concentration,
                'isObeCurriculum' => (bool) $project->student?->mahasiswaProfile?->is_obe_curriculum,
                'title' => $project->latestTitle?->title_id ?? '-',
                'state' => $this->statusLabel($project->state),
                'phase' => $this->phaseLabel($project->phase),
                'completedAt' => WitaDateTime::format($project->completed_at ?? $project->cancelled_at, 'd M Y', false),
                'documentCount' => $project->documents->count(),
                'defenseCount' => $project->defenses->count(),
                'profileUrl' => route('users.profile.show', ['user' => $project->student_user_id]),
            ])
            ->values();
    }

    private function lecturerRows(ProgramStudi $programStudi): Collection
    {
        return User::query()
            ->whereHas('activeDosenProgramStudiAssignments', fn($query) => $query->where('program_studi_id', $programStudi->id))
            ->with(['dosenProfile', 'activeDosenProgramStudiAssignments', 'expertiseFields'])
            ->orderBy('name')
            ->get()
            ->map(function (User $lecturer) use ($programStudi): array {
                $assignments = $lecturer->activeDosenProgramStudiAssignments
                    ->where('program_studi_id', $programStudi->id)
                    ->sortBy('concentration')
                    ->values();
                $concentrations = $assignments
                    ->pluck('concentration')
                    ->filter()
                    ->unique()
                    ->values();

                $supervisorAssignments = ThesisSupervisorAssignment::query()
                    ->where('lecturer_user_id', $lecturer->id)
                    ->whereHas('project', fn($query) => $query->current()->where('program_studi_id', $programStudi->id))
                    ->with('project.student')
                    ->get();

                $examinerDefenses = ThesisDefense::query()
                    ->whereHas('project', fn($query) => $query->current()->where('program_studi_id', $programStudi->id))
                    ->whereHas('examiners', fn($query) => $query->where('lecturer_user_id', $lecturer->id))
                    ->with('project.student')
                    ->get();

                $activeStudents = $supervisorAssignments
                    ->where('status', 'active')
                    ->filter(fn(ThesisSupervisorAssignment $assignment): bool => $assignment->project?->state === 'active' && ! $assignment->project->isArchived())
                    ->pluck('project.student.name')
                    ->filter()
                    ->unique()
                    ->values();

                return [
                    'id' => $lecturer->id,
                    'name' => $lecturer->name,
                    'avatar' => $lecturer->avatar,
                    'nik' => $lecturer->dosenProfile?->nik ?? '-',
                    'concentration' => $concentrations->first(),
                    'concentrations' => $concentrations->all(),
                    'expertiseFields' => $lecturer->expertiseFields
                        ->pluck('name')
                        ->sort()
                        ->values()
                        ->all(),
                    'status' => ($lecturer->dosenProfile?->is_active ?? true) ? 'Aktif' : 'Nonaktif',
                    'quota' => (int) ($lecturer->dosenProfile?->supervision_quota ?? 0),
                    'activeSupervisionCount' => $activeStudents->count(),
                    'primaryCount' => $supervisorAssignments->where('role', 'primary')->count(),
                    'secondaryCount' => $supervisorAssignments->where('role', 'secondary')->count(),
                    'semproCount' => $examinerDefenses->where('type', 'sempro')->count(),
                    'sidangCount' => $examinerDefenses->where('type', 'sidang')->count(),
                    'upcomingExamCount' => $examinerDefenses->whereIn('status', ['scheduled', 'awaiting_finalization'])->count(),
                    'activeStudents' => $activeStudents->all(),
                    'profileUrl' => route('users.profile.show', ['user' => $lecturer->id]),
                ];
            })
            ->values();
    }

    private function archiveDocuments(ProgramStudi $programStudi): Collection
    {
        return ThesisDocument::query()
            ->whereHas('project', fn($query) => $query
                ->current()
                ->where('program_studi_id', $programStudi->id)
                ->where('state', 'completed'))
            ->with('project.student')
            ->latest('uploaded_at')
            ->limit(12)
            ->get()
            ->map(fn(ThesisDocument $document): array => [
                'id' => $document->id,
                'title' => $document->title,
                'kind' => $this->statusLabel($document->kind),
                'status' => $this->statusLabel($document->status),
                'student' => $document->project?->student?->name ?? 'Mahasiswa',
                'uploadedAt' => WitaDateTime::format($document->uploaded_at),
            ])
            ->values();
    }
}
