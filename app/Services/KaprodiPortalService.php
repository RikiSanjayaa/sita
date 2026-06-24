<?php

namespace App\Services;

use App\Models\MahasiswaProfile;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipDocument;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisDocument;
use App\Models\ThesisProject;
use App\Models\ThesisRevision;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Support\AcademicTerminology;
use App\Support\WitaDateTime;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class KaprodiPortalService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(ProgramStudi $programStudi): array
    {
        $projects = $this->projects($programStudi);
        $students = $this->studentProfiles($programStudi);
        $latestChatByStudent = $this->latestChatActivityByStudent($students->pluck('user_id'));
        $latestDocumentByStudent = $this->latestMentorshipDocumentActivityByStudent($students->pluck('user_id'));
        $attentionItems = $this->attentionItems($programStudi, $projects, $students, $latestChatByStudent, $latestDocumentByStudent);

        return [
            'programStudi' => $this->programStudiSummary($programStudi),
            'workSummary' => [
                'label' => $attentionItems->isNotEmpty() ? 'Perlu Dipantau' : 'Terkendali',
                'headline' => $attentionItems->isNotEmpty()
                    ? $attentionItems->count().' perhatian'
                    : 'Tidak ada perhatian mendesak',
                'description' => $attentionItems->isNotEmpty()
                    ? 'Ada jadwal, revisi, atau progres mahasiswa yang perlu dipantau kaprodi.'
                    : 'Agenda dan progres prodi sedang dalam kondisi tenang.',
                'metrics' => [
                    [
                        'label' => 'Mahasiswa',
                        'value' => (string) $students->count(),
                    ],
                    [
                        'label' => 'Arsip',
                        'value' => (string) $projects->whereIn('state', ['completed', 'cancelled'])->count(),
                    ],
                ],
            ],
            'attentionItems' => $attentionItems->values()->all(),
            'upcomingAgenda' => $this->deadlines($programStudi, 5),
            'recentArchives' => $this->archiveRows($programStudi, 4),
            'phaseDistribution' => $this->phaseDistribution($projects),
            'defenseProgress' => $this->defenseProgress($programStudi)->take(5)->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function studentIndex(ProgramStudi $programStudi): array
    {
        $projects = $this->projects($programStudi);
        $students = $this->studentProfiles($programStudi);
        $latestChatByStudent = $this->latestChatActivityByStudent($students->pluck('user_id'));
        $latestDocumentByStudent = $this->latestMentorshipDocumentActivityByStudent($students->pluck('user_id'));
        $studentRows = $this->studentRows($students, $projects, $latestChatByStudent, $latestDocumentByStudent);

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
            ->whereHas('project', fn($query) => $query->where('program_studi_id', $programStudi->id))
            ->with([
                'project.student.mahasiswaProfile',
                'project.latestTitle',
                'examiners.lecturer',
                'revisions',
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
    public function documents(ProgramStudi $programStudi): array
    {
        $workspaceDocuments = MentorshipDocument::query()
            ->whereHas('student.mahasiswaProfile', fn($query) => $query->where('program_studi_id', $programStudi->id))
            ->with(['student.mahasiswaProfile', 'lecturer'])
            ->latest('created_at')
            ->get()
            ->groupBy(fn(MentorshipDocument $document): string => $this->workspaceDocumentGroupKey($document))
            ->map(function (Collection $documents, string $key): array {
                /** @var MentorshipDocument $document */
                $document = $documents->sortByDesc('created_at')->first();
                $counts = $this->documentReviewCounts($documents->pluck('status'));

                return [
                    'id' => 'workspace-'.md5($key),
                    'source' => 'Workspace',
                    'mahasiswa' => $document->student?->name ?? '-',
                    'nim' => $document->student?->mahasiswaProfile?->nim,
                    'title' => $document->title,
                    'file' => $document->file_name,
                    'uploadedAt' => $document->created_at?->format('d M Y H:i') ?? '-',
                    'uploadedSort' => $document->created_at?->getTimestamp() ?? 0,
                    'status' => $this->aggregateDocumentStatus($documents->pluck('status')),
                    'reviewCount' => $documents->count(),
                    'pendingCount' => $counts['pending'],
                    'revisionCount' => $counts['revision'],
                    'approvedCount' => $counts['approved'],
                    'fileUrl' => $document->storage_path === null
                        ? $document->file_url
                        : route('files.documents.download', ['document' => $document->id]),
                    'profileUrl' => route('users.profile.show', ['user' => $document->student_user_id]),
                    'reviews' => $documents
                        ->sortBy(fn(MentorshipDocument $review): string => $review->lecturer?->name ?? '')
                        ->map(fn(MentorshipDocument $review): array => [
                            'id' => $review->id,
                            'reviewer' => $review->lecturer?->name ?? 'Belum diarahkan',
                            'status' => $this->statusLabel($review->status),
                            'revisionNotes' => $review->revision_notes,
                            'uploadedAt' => $review->created_at?->format('d M Y H:i') ?? '-',
                            'reviewedAt' => $review->reviewed_at?->format('d M Y H:i'),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values();

        $thesisDocuments = ThesisDocument::query()
            ->whereHas('project', fn($query) => $query->where('program_studi_id', $programStudi->id))
            ->with(['project.student', 'defense'])
            ->latest('uploaded_at')
            ->get()
            ->map(function (ThesisDocument $document): array {
                $counts = $this->documentReviewCounts(collect([$document->status]));

                return [
                    'id' => 'thesis-'.$document->id,
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
                    'fileUrl' => route('files.thesis-documents.download', ['document' => $document->id]),
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
            ->where('program_studi_id', $programStudi->id)
            ->where('student_user_id', $student->id)
            ->with([
                'latestTitle',
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
            ->whereHas('project', fn($query) => $query->where('program_studi_id', $programStudi->id))
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
     * @return array<int, array{id: string, badge: string, title: string, subtitle: string, date: string}>
     */
    private function deadlines(ProgramStudi $programStudi, int $limit = 8): array
    {
        $defenseDeadlines = ThesisDefense::query()
            ->whereHas('project', fn($query) => $query->where('program_studi_id', $programStudi->id))
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '>=', now()->subDay())
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
            ]);

        $revisionDeadlines = ThesisRevision::query()
            ->whereHas('project', fn($query) => $query->where('program_studi_id', $programStudi->id))
            ->whereNotNull('due_at')
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
    private function studentRows(EloquentCollection $students, EloquentCollection $projects, Collection $latestChatByStudent, Collection $latestDocumentByStudent): Collection
    {
        return $students
            ->sortBy(fn(MahasiswaProfile $profile): string => $profile->user?->name ?? '')
            ->map(function (MahasiswaProfile $profile) use ($latestChatByStudent, $latestDocumentByStudent, $projects): array {
                $project = $projects
                    ->where('student_user_id', $profile->user_id)
                    ->sortByDesc('started_at')
                    ->first();

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
                    'terminology' => AcademicTerminology::forDegreeLevel($profile->degree_level),
                    'concentration' => $profile->concentration,
                    'phase' => $this->phaseLabel($project?->phase),
                    'phaseKey' => $project?->phase ?? 'none',
                    'projectState' => $this->statusLabel($project?->state),
                    'projectStateKey' => $project?->state ?? 'none',
                    'title' => $project?->latestTitle?->title_id ?? '-',
                    'advisors' => $project?->activeSupervisorAssignments
                        ->map(fn($assignment): string => $assignment->lecturer?->name ?? '-')
                        ->values()
                        ->all() ?? [],
                    'supervisorAssignments' => $project?->activeSupervisorAssignments
                        ->sortBy('role')
                        ->map(fn($assignment): array => [
                            'lecturerUserId' => $assignment->lecturer_user_id,
                            'name' => $assignment->lecturer?->name ?? '-',
                            'role' => $assignment->role === 'primary' ? 'Pembimbing 1' : 'Pembimbing 2',
                        ])
                        ->values()
                        ->all() ?? [],
                    'progressRisk' => $this->progressRisk(
                        $project,
                        $latestChatByStudent->get((int) $profile->user_id),
                        $latestDocumentByStudent->get((int) $profile->user_id),
                    ),
                    'profileUrl' => route('users.profile.show', ['user' => $profile->user_id]),
                ];
            })
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function projectDetail(ThesisProject $project): array
    {
        return [
            'id' => $project->id,
            'title' => $project->latestTitle?->title_id ?? '-',
            'phase' => $this->phaseLabel($project->phase),
            'state' => $this->statusLabel($project->state),
            'startedAt' => WitaDateTime::format($project->started_at, 'd M Y', false),
            'completedAt' => WitaDateTime::format($project->completed_at, 'd M Y', false),
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

    private function progressRisk(?ThesisProject $project, ?MentorshipChatMessage $latestChat, ?MentorshipDocument $latestMentorshipDocument): array
    {
        if (! $project instanceof ThesisProject) {
            return [
                'level' => 'medium',
                'label' => 'Perlu Data',
                'description' => 'Mahasiswa belum memiliki proyek tugas akhir yang tercatat.',
                'lastActivityAt' => '-',
                'lastActivityLabel' => 'Belum ada proyek',
                'daysIdle' => null,
                'signals' => ['Belum ada proyek'],
            ];
        }

        if (in_array($project->state, ['completed', 'cancelled'], true)) {
            return [
                'level' => 'low',
                'label' => 'Arsip',
                'description' => 'Proyek sudah masuk arsip prodi.',
                'lastActivityAt' => WitaDateTime::format($project->completed_at ?? $project->cancelled_at ?? $project->updated_at, 'd M Y', false),
                'lastActivityLabel' => 'Proyek selesai/diarsipkan',
                'daysIdle' => null,
                'signals' => ['Proyek arsip'],
            ];
        }

        $latestActivity = $this->latestProjectActivity($project, $latestChat, $latestMentorshipDocument);
        $daysIdle = $latestActivity['at'] === null
            ? null
            : ($latestActivity['at']->isFuture() ? 0 : (int) $latestActivity['at']->diffInDays(now()));
        $thresholds = $this->progressRiskThresholds($project->phase);
        $signals = [];

        if ($project->activeSupervisorAssignments->isEmpty()) {
            $signals[] = 'Belum ada pembimbing aktif';
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
            $description = $daysIdle >= $thresholds['medium']
                ? "Aktivitas terakhir {$daysIdle} hari lalu."
                : 'Ada sinyal progres yang perlu dipantau.';
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
     * @return array{at: mixed, label: string}
     */
    private function latestProjectActivity(ThesisProject $project, ?MentorshipChatMessage $latestChat, ?MentorshipDocument $latestMentorshipDocument): array
    {
        $candidates = collect([
            [
                'at' => $project->updated_at ?? $project->started_at,
                'label' => 'Update proyek',
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
            [
                'at' => $latestChat?->created_at,
                'label' => 'Chat terakhir',
            ],
            [
                'at' => $latestMentorshipDocument?->reviewed_at ?? $latestMentorshipDocument?->updated_at ?? $latestMentorshipDocument?->created_at,
                'label' => 'Dokumen bimbingan',
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
            'title_review' => 'Review Judul',
            'sempro' => 'Proposal',
            'research' => 'Penelitian',
            'sidang' => 'Ujian Akhir',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            default => '-',
        };
    }

    private function defenseTypeLabel(?string $type): string
    {
        return match ($type) {
            'sempro' => 'Proposal',
            'sidang' => 'Ujian Akhir',
            default => 'Ujian',
        };
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
            $document->document_group ?: $document->category ?: $document->title,
            $document->version_number ?: 1,
            $document->file_name ?: $document->title,
        ]);
    }

    private function aggregateDocumentStatus(Collection $statuses): string
    {
        if ($statuses->contains('needs_revision')) {
            return 'Perlu Revisi';
        }

        if ($statuses->contains(fn(?string $status): bool => $status !== 'approved')) {
            return 'Perlu Review';
        }

        return 'Disetujui';
    }

    /**
     * @return array{pending: int, revision: int, approved: int}
     */
    private function documentReviewCounts(Collection $statuses): array
    {
        return [
            'pending' => $statuses->filter(fn(?string $status): bool => ! in_array($status, ['approved', 'needs_revision'], true))->count(),
            'revision' => $statuses->filter(fn(?string $status): bool => $status === 'needs_revision')->count(),
            'approved' => $statuses->filter(fn(?string $status): bool => $status === 'approved')->count(),
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
            ->where('program_studi_id', $programStudi->id)
            ->with([
                'student.mahasiswaProfile',
                'latestTitle',
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

    private function latestChatActivityByStudent(Collection $studentIds): Collection
    {
        $ids = $studentIds
            ->filter()
            ->map(fn($id): int => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return MentorshipChatMessage::query()
            ->whereHas('thread', fn($query) => $query->whereIn('student_user_id', $ids->all()))
            ->with('thread:id,student_user_id')
            ->latest('created_at')
            ->get()
            ->filter(fn(MentorshipChatMessage $message): bool => $message->thread?->student_user_id !== null)
            ->unique(fn(MentorshipChatMessage $message): int => (int) $message->thread->student_user_id)
            ->mapWithKeys(fn(MentorshipChatMessage $message): array => [
                (int) $message->thread->student_user_id => $message,
            ]);
    }

    private function latestMentorshipDocumentActivityByStudent(Collection $studentIds): Collection
    {
        $ids = $studentIds
            ->filter()
            ->map(fn($id): int => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return MentorshipDocument::query()
            ->whereIn('student_user_id', $ids->all())
            ->latest('updated_at')
            ->get()
            ->unique(fn(MentorshipDocument $document): int => (int) $document->student_user_id)
            ->mapWithKeys(fn(MentorshipDocument $document): array => [
                (int) $document->student_user_id => $document,
            ]);
    }

    private function attentionItems(ProgramStudi $programStudi, EloquentCollection $projects, EloquentCollection $students, Collection $latestChatByStudent, Collection $latestDocumentByStudent): Collection
    {
        $unfinalizedDefenses = ThesisDefense::query()
            ->whereHas('project', fn($query) => $query->where('program_studi_id', $programStudi->id))
            ->where('status', 'awaiting_finalization')
            ->count();

        $openRevisions = ThesisRevision::query()
            ->whereHas('project', fn($query) => $query->where('program_studi_id', $programStudi->id))
            ->whereNull('resolved_at')
            ->count();

        $studentsWithoutProject = $students
            ->filter(fn(MahasiswaProfile $profile): bool => $projects->where('student_user_id', $profile->user_id)->isEmpty())
            ->count();

        $projectsWithoutSupervisor = $projects
            ->filter(fn(ThesisProject $project): bool => $project->state === 'active' && $project->activeSupervisorAssignments->isEmpty())
            ->count();

        $riskStudents = $this->studentRows($students, $projects, $latestChatByStudent, $latestDocumentByStudent)
            ->where('status', 'Aktif')
            ->where('progressRisk.level', 'high')
            ->count();

        return collect([
            [
                'id' => 'progress-risk',
                'label' => 'Risiko telat',
                'value' => $riskStudents,
                'description' => 'Mahasiswa aktif dengan jeda progres panjang.',
                'href' => route('kaprodi.mahasiswa.index'),
            ],
            [
                'id' => 'unfinalized-defenses',
                'label' => 'Ujian belum final',
                'value' => $unfinalizedDefenses,
                'description' => 'Sempro/sidang menunggu finalisasi hasil.',
                'href' => route('kaprodi.sempro-sidang'),
            ],
            [
                'id' => 'open-revisions',
                'label' => 'Revisi terbuka',
                'value' => $openRevisions,
                'description' => 'Revisi masih berjalan atau melewati tenggat.',
                'href' => route('kaprodi.sempro-sidang'),
            ],
            [
                'id' => 'students-without-project',
                'label' => 'Tanpa proyek',
                'value' => $studentsWithoutProject,
                'description' => 'Mahasiswa prodi belum memiliki proyek aktif/arsip.',
                'href' => route('kaprodi.mahasiswa.index'),
            ],
            [
                'id' => 'projects-without-supervisor',
                'label' => 'Tanpa pembimbing',
                'value' => $projectsWithoutSupervisor,
                'description' => 'Proyek aktif belum memiliki pembimbing aktif.',
                'href' => route('kaprodi.dosen-prodi'),
            ],
        ])->filter(fn(array $item): bool => $item['value'] > 0)->values();
    }

    private function archiveRows(ProgramStudi $programStudi, ?int $limit): Collection
    {
        $query = ThesisProject::query()
            ->where('program_studi_id', $programStudi->id)
            ->whereIn('state', ['completed', 'cancelled'])
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
                    ->whereHas('project', fn($query) => $query->where('program_studi_id', $programStudi->id))
                    ->with('project.student')
                    ->get();

                $examinerDefenses = ThesisDefense::query()
                    ->whereHas('project', fn($query) => $query->where('program_studi_id', $programStudi->id))
                    ->whereHas('examiners', fn($query) => $query->where('lecturer_user_id', $lecturer->id))
                    ->with('project.student')
                    ->get();

                $activeStudents = $supervisorAssignments
                    ->where('status', 'active')
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
                ->where('program_studi_id', $programStudi->id)
                ->whereIn('state', ['completed', 'cancelled']))
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
