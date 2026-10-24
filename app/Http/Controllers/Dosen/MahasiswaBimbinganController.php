<?php

namespace App\Http\Controllers\Dosen;

use App\Enums\AdvisorType;
use App\Http\Controllers\Controller;
use App\Models\MentorshipChatThread;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisProject;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Services\DosenBimbinganService;
use App\Services\MentorshipProgressService;
use App\Services\StudentActivityService;
use App\Services\StudentAttention;
use App\Services\StudentProgressSnapshot;
use App\Services\ThesisProjectNoteService;
use App\Services\UserProfilePresenter;
use App\Support\AcademicTerminology;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MahasiswaBimbinganController extends Controller
{
    public function __construct(
        private readonly DosenBimbinganService $dosenBimbinganService,
        private readonly UserProfilePresenter $userProfilePresenter,
        private readonly ThesisProjectNoteService $noteService,
        private readonly MentorshipProgressService $mentorshipProgressService,
        private readonly StudentActivityService $studentActivityService,
        private readonly StudentProgressSnapshot $studentProgressSnapshot,
        private readonly StudentAttention $studentAttention,
    ) {}

    public function __invoke(Request $request): Response
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);

        $assignments = $this->dosenBimbinganService->activeAssignmentsWithStudent($lecturer);
        $examinerRecords = ThesisDefenseExaminer::query()
            ->with([
                'defense.project.student.mahasiswaProfile',
                'defense.project.defenses',
                'defense.project.activeSupervisorAssignments.lecturer.dosenProfile.programStudi',
                'defense.project.activeSupervisorAssignments.lecturer.roles',
            ])
            ->where('lecturer_user_id', $lecturer->id)
            ->whereHas('defense', fn ($query) => $query->where('status', '!=', 'cancelled'))
            ->whereHas('defense.project', fn ($query) => $query->current()->whereHas('student'))
            ->get();

        $activeExaminerRecords = $examinerRecords
            ->filter(fn (ThesisDefenseExaminer $examiner): bool => $this->isActiveExaminerRecord($examiner))
            ->values();

        $historyExaminerRecords = $examinerRecords
            ->reject(fn (ThesisDefenseExaminer $examiner): bool => $this->isActiveExaminerRecord($examiner))
            ->values();

        $studentIds = $assignments
            ->pluck('project.student_user_id')
            ->merge($activeExaminerRecords->pluck('defense.project.student_user_id'))
            ->filter()
            ->unique()
            ->values();

        $threadIdsByStudent = MentorshipChatThread::query()
            ->where('type', 'pembimbing')
            ->whereIn('student_user_id', $studentIds)
            ->pluck('id', 'student_user_id');

        $examThreadIdsByStudentAndType = $this->examThreadIdsByStudentAndType($studentIds->all());

        $completedByStudent = $this->mentorshipProgressService->completedByStudentForLecturer($lecturer->id, $assignments);
        $archivedAssignments = $this->dosenBimbinganService->archivedAssignmentsWithStudent($lecturer);
        $projects = $assignments->pluck('project')
            ->merge($archivedAssignments->pluck('project'))
            ->merge($examinerRecords->pluck('defense.project'))
            ->filter()
            ->values();
        $uniqueProjects = new EloquentCollection($projects->unique('id')->values()->all());
        // One last-activity rule everywhere: the snapshot's value is what the active rows show.
        $lastActivityByProject = $this->studentProgressSnapshot->lastActivity($uniqueProjects, $lecturer);
        $progressByProject = $this->studentProgressSnapshot->forProjects($uniqueProjects, $lecturer, lastActivity: $lastActivityByProject);
        $latestActivity = $this->activityByRelation($assignments, $activeExaminerRecords, $lastActivityByProject);
        $attentionByProject = $this->studentAttention->forSupervisedProjects(
            new EloquentCollection($assignments->pluck('project')->filter()->unique('id')->values()->all()),
            $lecturer,
            $lastActivityByProject,
        );

        // Row building calls UserProfilePresenter::summary() per student; preload what it needs once.
        (new EloquentCollection($projects->pluck('student')->filter()->all()))->loadMissing([
            'roles',
            'adminProfile.programStudi',
            'dosenProfile.programStudi',
            'expertiseFields',
            'activeDosenProgramStudiAssignments.programStudi',
            'kaprodiAssignment.programStudi',
            'mahasiswaProfile.programStudi',
        ]);
        $rows = collect($assignments->map(function ($assignment) use ($latestActivity, $threadIdsByStudent, $completedByStudent, $progressByProject, $attentionByProject): array {
            return $this->buildRow($assignment, $latestActivity, $threadIdsByStudent, $completedByStudent, $progressByProject, $attentionByProject);
        }))
            ->merge($activeExaminerRecords->map(function (ThesisDefenseExaminer $examiner) use ($examThreadIdsByStudentAndType, $latestActivity, $progressByProject): array {
                return $this->buildExaminerRow($examiner, $latestActivity, $examThreadIdsByStudentAndType, $progressByProject);
            }))
            ->sortBy([
                ['name', 'asc'],
                ['relationType', 'asc'],
                ['contextLabel', 'asc'],
            ])
            ->values()
            ->all();

        // Archived / history rows
        $archivedStudentIds = $archivedAssignments
            ->pluck('project.student_user_id')
            ->merge($historyExaminerRecords->pluck('defense.project.student_user_id'))
            ->filter()
            ->unique()
            ->values();

        $archivedThreadIds = MentorshipChatThread::query()
            ->where('type', 'pembimbing')
            ->whereIn('student_user_id', $archivedStudentIds)
            ->pluck('id', 'student_user_id');

        $archivedExamThreadIdsByStudentAndType = $this->examThreadIdsByStudentAndType($archivedStudentIds->all());

        $archivedCompletedByStudent = $this->mentorshipProgressService->completedByStudentForLecturer($lecturer->id, $archivedAssignments);

        // History rows stay within the window in which this lecturer was related to the student.
        $archivedLatestActivity = $this->studentActivityService->latest($archivedAssignments, $historyExaminerRecords);
        $historyRows = collect($archivedAssignments->map(function ($assignment) use ($archivedLatestActivity, $archivedThreadIds, $archivedCompletedByStudent, $progressByProject): array {
            return $this->buildRow($assignment, $archivedLatestActivity, $archivedThreadIds, $archivedCompletedByStudent, $progressByProject);
        }))
            ->merge($historyExaminerRecords->map(function (ThesisDefenseExaminer $examiner) use ($archivedExamThreadIdsByStudentAndType, $archivedLatestActivity, $progressByProject): array {
                return $this->buildExaminerRow($examiner, $archivedLatestActivity, $archivedExamThreadIdsByStudentAndType, $progressByProject);
            }))
            ->sortBy([
                ['name', 'asc'],
                ['relationType', 'asc'],
                ['contextLabel', 'asc'],
            ])
            ->values()
            ->all();

        return Inertia::render('dosen/mahasiswa-bimbingan', [
            'mahasiswaRows' => $rows,
            'historyRows' => $historyRows,
            'activeCount' => $assignments->count(),
            'relatedCount' => count($rows),
            'capacityLimit' => $this->dosenBimbinganService->lecturerQuota($lecturer),
            'projectNotes' => $this->noteService->payload(
                $lecturer,
                $assignments->pluck('project')
                    ->merge($activeExaminerRecords->pluck('defense.project'))
                    ->filter()
                    ->unique('id'),
            ),
        ]);
    }

    /**
     * @param  array<string, \Illuminate\Support\Carbon>  $latestActivity
     * @param  \Illuminate\Support\Collection<string,mixed>  $threadIdsByStudent
     * @param  array<int,int>  $completedByStudent
     * @param  array<int, array<string, mixed>>  $progressByProject
     * @param  array<int, list<array{key: string, label: string}>>  $attentionByProject
     * @return array<string,mixed>
     */
    private function buildRow(
        mixed $assignment,
        array $latestActivity,
        mixed $threadIdsByStudent,
        array $completedByStudent,
        array $progressByProject,
        array $attentionByProject = [],
    ): array {
        $student = $assignment->project?->student;
        $profile = $student?->mahasiswaProfile;
        $project = $assignment->project;
        $studentUserId = $assignment->project?->student_user_id;
        $threadId = $studentUserId === null ? null : $threadIdsByStudent->get($studentUserId);

        $stage = $this->stageSummary($project, $student);
        $studentSummary = $this->userProfilePresenter->summary($student);

        return [
            'nim' => $profile?->nim ?? '-',
            'studentUserId' => $student?->id,
            'projectId' => $project?->id,
            'name' => $student?->name ?? '-',
            'avatar' => $studentSummary['avatar'] ?? null,
            'profileUrl' => $studentSummary['profileUrl'] ?? null,
            'advisorType' => $assignment->role === AdvisorType::Primary->value ? 'Pembimbing 1' : 'Pembimbing 2',
            'relationType' => 'pembimbing',
            'roleLabel' => $assignment->role === AdvisorType::Primary->value ? 'Pembimbing 1' : 'Pembimbing 2',
            'contextLabel' => 'Bimbingan',
            'contextDescription' => 'Relasi pembimbing aktif',
            'otherAdvisors' => $project?->activeSupervisorAssignments
                ->filter(fn (ThesisSupervisorAssignment $a): bool => $a->lecturer_user_id !== $assignment->lecturer_user_id)
                ->sortBy('role')
                ->map(fn (ThesisSupervisorAssignment $a): string => sprintf(
                    '%s: %s',
                    $a->role === AdvisorType::Primary->value ? 'Pembimbing 1' : 'Pembimbing 2',
                    $a->lecturer?->name ?? '-',
                ))
                ->values()
                ->all() ?? [],
            'mentorshipCompleted' => $studentUserId === null ? null : ($completedByStudent[$studentUserId] ?? 0),
            'stageLabel' => $stage['label'],
            'stageDescription' => $stage['description'],
            'phase' => $project?->phase,
            'status' => ($profile?->is_active ?? true) ? 'Aktif' : 'Nonaktif',
            'lastUpdate' => ($latestActivity['supervisor:'.$assignment->id] ?? null)?->locale('id')->diffForHumans()
                ?? 'Belum ada aktivitas',
            'chatUrl' => $threadId === null ? null : "/dosen/pesan?thread={$threadId}",
            'whatsappUrl' => $studentSummary['whatsappUrl'] ?? null,
            'progress' => $project === null ? null : ($progressByProject[$project->id] ?? null),
            'attention' => $project === null ? [] : ($attentionByProject[$project->id] ?? []),
        ];
    }

    /**
     * @param  array<string, \Illuminate\Support\Carbon>  $latestActivity
     * @param  array<string, int>  $threadIdsByStudentAndType
     * @param  array<int, array<string, mixed>>  $progressByProject
     * @return array<string,mixed>
     */
    private function buildExaminerRow(
        ThesisDefenseExaminer $examiner,
        array $latestActivity,
        array $threadIdsByStudentAndType,
        array $progressByProject,
    ): array {
        $defense = $examiner->defense;
        $project = $defense?->project;
        $student = $project?->student;
        $profile = $student?->mahasiswaProfile;
        $studentUserId = $project?->student_user_id;
        $threadId = $studentUserId === null || $defense === null
            ? null
            : ($threadIdsByStudentAndType[$this->examThreadKey($studentUserId, $defense->type, $defense->id)] ?? null);

        $stage = $this->stageSummary($project, $student);
        $studentSummary = $this->userProfilePresenter->summary($student);
        $terminology = $project === null
            ? AcademicTerminology::neutral()
            : AcademicTerminology::forProject($project);
        $contextLabel = $defense?->type === 'sidang'
            ? $terminology['finalExam']
            : $terminology['proposalExamShort'];

        return [
            'nim' => $profile?->nim ?? '-',
            'studentUserId' => $student?->id,
            'projectId' => $project?->id,
            'name' => $student?->name ?? '-',
            'avatar' => $studentSummary['avatar'] ?? null,
            'profileUrl' => $studentSummary['profileUrl'] ?? null,
            'advisorType' => $this->defenseRoleLabel($examiner->role, $examiner->order_no),
            'relationType' => 'penguji',
            'roleLabel' => $this->defenseRoleLabel($examiner->role, $examiner->order_no),
            'contextLabel' => $contextLabel,
            'contextDescription' => $this->defenseStatusLabel($defense?->status, $defense?->result),
            'otherAdvisors' => $project?->activeSupervisorAssignments
                ->sortBy('role')
                ->map(fn (ThesisSupervisorAssignment $a): string => sprintf(
                    '%s: %s',
                    $a->role === AdvisorType::Primary->value ? 'Pembimbing 1' : 'Pembimbing 2',
                    $a->lecturer?->name ?? '-',
                ))
                ->values()
                ->all() ?? [],
            'mentorshipCompleted' => null,
            'stageLabel' => $stage['label'],
            'stageDescription' => $stage['description'],
            'phase' => $project?->phase,
            'status' => $this->defenseStatusLabel($defense?->status, $defense?->result),
            'lastUpdate' => ($latestActivity['examiner:'.$examiner->id] ?? null)?->locale('id')->diffForHumans()
                ?? 'Belum ada aktivitas',
            'chatUrl' => $threadId === null ? null : "/dosen/pesan?thread={$threadId}",
            'whatsappUrl' => $studentSummary['whatsappUrl'] ?? null,
            'progress' => $project === null ? null : ($progressByProject[$project->id] ?? null),
            'attention' => [],
        ];
    }

    /**
     * Keys active rows the way StudentActivityService::latest() keys relations, with
     * the project-level value that the progress snapshot shows.
     *
     * @param  EloquentCollection<int, ThesisSupervisorAssignment>  $assignments
     * @param  \Illuminate\Support\Collection<int, ThesisDefenseExaminer>  $examiners
     * @param  array<int, \Carbon\CarbonInterface>  $lastActivityByProject
     * @return array<string, \Carbon\CarbonInterface>
     */
    private function activityByRelation(EloquentCollection $assignments, \Illuminate\Support\Collection $examiners, array $lastActivityByProject): array
    {
        $latest = [];
        foreach ($assignments as $assignment) {
            if (isset($lastActivityByProject[(int) $assignment->project_id])) {
                $latest['supervisor:'.$assignment->id] = $lastActivityByProject[(int) $assignment->project_id];
            }
        }
        foreach ($examiners as $examiner) {
            $projectId = (int) $examiner->defense?->project_id;
            if (isset($lastActivityByProject[$projectId])) {
                $latest['examiner:'.$examiner->id] = $lastActivityByProject[$projectId];
            }
        }

        return $latest;
    }

    private function isActiveExaminerRecord(ThesisDefenseExaminer $examiner): bool
    {
        $defense = $examiner->defense;
        $project = $defense?->project;

        if (! $defense instanceof ThesisDefense || ! $project instanceof ThesisProject) {
            return false;
        }

        if ($project->state !== 'active') {
            return false;
        }

        return in_array($defense->status, ['draft', 'scheduled', 'awaiting_finalization'], true);
    }

    /**
     * @param  array<int, int>  $studentIds
     * @return array<string, int>
     */
    private function examThreadIdsByStudentAndType(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        return MentorshipChatThread::query()
            ->whereIn('student_user_id', $studentIds)
            ->whereIn('type', ['sempro', 'sidang'])
            ->orderBy('id')
            ->get(['id', 'student_user_id', 'type', 'context_id'])
            ->mapWithKeys(fn (MentorshipChatThread $thread): array => [
                $this->examThreadKey((int) $thread->student_user_id, $thread->type, (int) $thread->context_id) => $thread->id,
            ])
            ->all();
    }

    private function examThreadKey(int $studentUserId, string $type, int $defenseId): string
    {
        return "{$studentUserId}:{$type}:{$defenseId}";
    }

    private function defenseRoleLabel(string $role, ?int $orderNo): string
    {
        return match ($role) {
            'primary_supervisor' => 'Pembimbing 1',
            'secondary_supervisor' => 'Pembimbing 2',
            default => $orderNo === null ? 'Penguji' : "Penguji {$orderNo}",
        };
    }

    private function defenseStatusLabel(?string $status, ?string $result): string
    {
        if ($status === 'completed') {
            return match ($result) {
                'pass' => 'Lulus',
                'pass_with_revision' => 'Lulus',
                'fail' => 'Tidak lulus',
                default => 'Selesai',
            };
        }

        return match ($status) {
            'scheduled' => 'Terjadwal',
            'awaiting_finalization' => 'Menunggu finalisasi',
            'cancelled' => 'Dibatalkan',
            default => 'Dalam proses',
        };
    }

    /**
     * @return array{label: string, description: string}
     */
    private function stageSummary(?ThesisProject $project, ?User $student): array
    {
        if (! $project instanceof ThesisProject) {
            $terms = AcademicTerminology::forStudent($student);

            return [
                'label' => 'Belum Ada '.$terms['finalWork'],
                'description' => 'Mahasiswa belum memiliki '.$terms['finalWorkLower'].' aktif.',
            ];
        }

        $project->loadMissing('defenses');
        $terms = AcademicTerminology::forProject($project);

        $latestSidang = $project->defenses
            ->where('type', 'sidang')
            ->sortByDesc('attempt_no')
            ->first();

        if ($latestSidang instanceof ThesisDefense) {
            return match ($latestSidang->status) {
                'scheduled' => [
                    'label' => $terms['finalExam'].' Terjadwal',
                    'description' => 'Mahasiswa bersiap menuju '.$terms['finalExam'].'.',
                ],
                'awaiting_finalization' => [
                    'label' => 'Menunggu Hasil '.$terms['finalExam'],
                    'description' => 'Semua keputusan dosen sudah masuk dan menunggu finalisasi admin.',
                ],
                'completed' => [
                    'label' => match ($latestSidang->result) {
                        'pass_with_revision' => 'Revisi '.$terms['finalExam'],
                        'fail' => $terms['finalExam'].' Tidak Lulus',
                        default => $terms['finalExam'].' Selesai',
                    },
                    'description' => match ($latestSidang->result) {
                        'pass_with_revision' => 'Masih ada revisi '.$terms['finalExam'].' yang perlu dipantau.',
                        'fail' => 'Mahasiswa menunggu penjadwalan ulang '.$terms['finalExam'].' berikutnya.',
                        default => 'Tahap '.$terms['finalExam'].' sudah selesai.',
                    },
                ],
                default => [
                    'label' => 'Tahap '.$terms['finalExam'],
                    'description' => 'Mahasiswa sedang berada pada fase '.$terms['finalExam'].'.',
                ],
            };
        }

        $latestSempro = $project->defenses
            ->where('type', 'sempro')
            ->sortByDesc('attempt_no')
            ->first();

        if ($latestSempro instanceof ThesisDefense) {
            return match ($latestSempro->status) {
                'scheduled' => [
                    'label' => $terms['proposalExamShort'].' Terjadwal',
                    'description' => 'Mahasiswa sedang menuju pelaksanaan '.$terms['proposalExam'].'.',
                ],
                'awaiting_finalization' => [
                    'label' => 'Menunggu Hasil '.$terms['proposalExamShort'],
                    'description' => 'Semua keputusan dosen sudah masuk dan menunggu finalisasi admin.',
                ],
                'completed' => [
                    'label' => match ($latestSempro->result) {
                        'pass_with_revision' => 'Revisi '.$terms['proposalExamShort'],
                        'fail' => $terms['proposalExamShort'].' Tidak Lulus',
                        default => 'Penelitian Berjalan',
                    },
                    'description' => match ($latestSempro->result) {
                        'pass_with_revision' => 'Perlu menindaklanjuti revisi '.$terms['proposalExamShort'].'.',
                        'fail' => 'Mahasiswa menunggu penjadwalan ulang '.$terms['proposalExamShort'].' berikutnya.',
                        default => $terms['proposalExamShort'].' selesai, lanjut ke fase penelitian.',
                    },
                ],
                default => [
                    'label' => 'Tahap '.$terms['proposalExamShort'],
                    'description' => 'Tahap '.$terms['proposalExamShort'].' sedang diproses.',
                ],
            };
        }

        return match ($project->phase) {
            'title_review' => [
                'label' => AcademicTerminology::phaseLabel('title_review'),
                'description' => 'Menunggu persetujuan judul dan '.mb_strtolower($terms['proposalDocument']).'.',
            ],
            'research' => [
                'label' => 'Penelitian Berjalan',
                'description' => 'Bimbingan aktif dan penelitian sedang berlangsung.',
            ],
            default => [
                'label' => 'Dalam Proses',
                'description' => 'Tahap akademik mahasiswa sedang berjalan.',
            ],
        };
    }
}
