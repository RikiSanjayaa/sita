<?php

namespace App\Http\Controllers\Dosen;

use App\Enums\AdvisorType;
use App\Http\Controllers\Controller;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipDocument;
use App\Models\MentorshipSchedule;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisProject;
use App\Models\ThesisSupervisorAssignment;
use App\Services\DosenBimbinganService;
use App\Services\UserProfilePresenter;
use App\Support\AcademicTerminology;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MahasiswaBimbinganController extends Controller
{
    public function __construct(
        private readonly DosenBimbinganService $dosenBimbinganService,
        private readonly UserProfilePresenter $userProfilePresenter,
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
            ->whereHas('defense.project.student')
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
            ->whereIn('student_user_id', $studentIds)
            ->pluck('id', 'student_user_id');

        $examThreadIdsByStudentAndType = $this->examThreadIdsByStudentAndType($studentIds->all());

        $latestScheduleByStudent = MentorshipSchedule::query()
            ->where('lecturer_user_id', $lecturer->id)
            ->whereIn('student_user_id', $studentIds)
            ->orderByDesc('updated_at')
            ->get()
            ->keyBy('student_user_id');

        $latestDocumentByStudent = MentorshipDocument::query()
            ->where('lecturer_user_id', $lecturer->id)
            ->whereIn('student_user_id', $studentIds)
            ->orderByDesc('updated_at')
            ->get()
            ->keyBy('student_user_id');

        $rows = collect($assignments->map(function ($assignment) use ($latestDocumentByStudent, $latestScheduleByStudent, $threadIdsByStudent): array {
            return $this->buildRow($assignment, $latestDocumentByStudent, $latestScheduleByStudent, $threadIdsByStudent);
        }))
            ->merge($activeExaminerRecords->map(function (ThesisDefenseExaminer $examiner) use ($examThreadIdsByStudentAndType, $latestDocumentByStudent, $latestScheduleByStudent): array {
                return $this->buildExaminerRow($examiner, $latestDocumentByStudent, $latestScheduleByStudent, $examThreadIdsByStudentAndType);
            }))
            ->sortBy([
                ['name', 'asc'],
                ['relationType', 'asc'],
                ['contextLabel', 'asc'],
            ])
            ->values()
            ->all();

        // Archived / history rows
        $archivedAssignments = $this->dosenBimbinganService->archivedAssignmentsWithStudent($lecturer);
        $archivedStudentIds = $archivedAssignments
            ->pluck('project.student_user_id')
            ->merge($historyExaminerRecords->pluck('defense.project.student_user_id'))
            ->filter()
            ->unique()
            ->values();

        $archivedThreadIds = MentorshipChatThread::query()
            ->whereIn('student_user_id', $archivedStudentIds)
            ->pluck('id', 'student_user_id');

        $archivedExamThreadIdsByStudentAndType = $this->examThreadIdsByStudentAndType($archivedStudentIds->all());

        $archivedLatestDoc = MentorshipDocument::query()
            ->where('lecturer_user_id', $lecturer->id)
            ->whereIn('student_user_id', $archivedStudentIds)
            ->orderByDesc('updated_at')
            ->get()
            ->keyBy('student_user_id');

        $archivedLatestSchedule = MentorshipSchedule::query()
            ->where('lecturer_user_id', $lecturer->id)
            ->whereIn('student_user_id', $archivedStudentIds)
            ->orderByDesc('updated_at')
            ->get()
            ->keyBy('student_user_id');

        $historyRows = collect($archivedAssignments->map(function ($assignment) use ($archivedLatestDoc, $archivedLatestSchedule, $archivedThreadIds): array {
            return $this->buildRow($assignment, $archivedLatestDoc, $archivedLatestSchedule, $archivedThreadIds);
        }))
            ->merge($historyExaminerRecords->map(function (ThesisDefenseExaminer $examiner) use ($archivedExamThreadIdsByStudentAndType, $archivedLatestDoc, $archivedLatestSchedule): array {
                return $this->buildExaminerRow($examiner, $archivedLatestDoc, $archivedLatestSchedule, $archivedExamThreadIdsByStudentAndType);
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
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<string,mixed>  $latestDocByStudent
     * @param  \Illuminate\Support\Collection<string,mixed>  $latestScheduleByStudent
     * @param  \Illuminate\Support\Collection<string,mixed>  $threadIdsByStudent
     * @return array<string,mixed>
     */
    private function buildRow(
        mixed $assignment,
        mixed $latestDocByStudent,
        mixed $latestScheduleByStudent,
        mixed $threadIdsByStudent,
    ): array {
        $student = $assignment->project?->student;
        $profile = $student?->mahasiswaProfile;
        $project = $assignment->project;
        $studentUserId = $assignment->project?->student_user_id;
        $latestSchedule = $studentUserId === null ? null : $latestScheduleByStudent->get($studentUserId);
        $latestDocument = $studentUserId === null ? null : $latestDocByStudent->get($studentUserId);
        $threadId = $studentUserId === null ? null : $threadIdsByStudent->get($studentUserId);

        $stage = $this->stageSummary($project);
        $studentSummary = $this->userProfilePresenter->summary($student);

        return [
            'nim' => $profile?->nim ?? '-',
            'studentUserId' => $student?->id,
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
            'stageLabel' => $stage['label'],
            'stageDescription' => $stage['description'],
            'status' => ($profile?->is_active ?? true) ? 'Aktif' : 'Nonaktif',
            'lastUpdate' => $latestDocument?->updated_at?->diffForHumans()
                ?? $latestSchedule?->updated_at?->diffForHumans()
                ?? 'Belum ada aktivitas',
            'chatUrl' => $threadId === null ? null : "/dosen/pesan?thread={$threadId}",
            'whatsappUrl' => $studentSummary['whatsappUrl'] ?? null,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<string,mixed>  $latestDocByStudent
     * @param  \Illuminate\Support\Collection<string,mixed>  $latestScheduleByStudent
     * @param  array<string, int>  $threadIdsByStudentAndType
     * @return array<string,mixed>
     */
    private function buildExaminerRow(
        ThesisDefenseExaminer $examiner,
        mixed $latestDocByStudent,
        mixed $latestScheduleByStudent,
        array $threadIdsByStudentAndType,
    ): array {
        $defense = $examiner->defense;
        $project = $defense?->project;
        $student = $project?->student;
        $profile = $student?->mahasiswaProfile;
        $studentUserId = $project?->student_user_id;
        $latestSchedule = $studentUserId === null ? null : $latestScheduleByStudent->get($studentUserId);
        $latestDocument = $studentUserId === null ? null : $latestDocByStudent->get($studentUserId);
        $threadId = $studentUserId === null || $defense === null
            ? null
            : ($threadIdsByStudentAndType[$this->examThreadKey($studentUserId, $defense->type)] ?? null);

        $stage = $this->stageSummary($project);
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
            'stageLabel' => $stage['label'],
            'stageDescription' => $stage['description'],
            'status' => $this->defenseStatusLabel($defense?->status, $defense?->result),
            'lastUpdate' => $examiner->decided_at?->diffForHumans()
                ?? $defense?->updated_at?->diffForHumans()
                ?? $latestDocument?->updated_at?->diffForHumans()
                ?? $latestSchedule?->updated_at?->diffForHumans()
                ?? 'Belum ada aktivitas',
            'chatUrl' => $threadId === null ? null : "/dosen/pesan?thread={$threadId}",
            'whatsappUrl' => $studentSummary['whatsappUrl'] ?? null,
        ];
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

        return $defense->status !== 'completed';
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
            ->get(['id', 'student_user_id', 'type'])
            ->mapWithKeys(fn (MentorshipChatThread $thread): array => [
                $this->examThreadKey((int) $thread->student_user_id, $thread->type) => $thread->id,
            ])
            ->all();
    }

    private function examThreadKey(int $studentUserId, string $type): string
    {
        return "{$studentUserId}:{$type}";
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
    private function stageSummary(?ThesisProject $project): array
    {
        if (! $project instanceof ThesisProject) {
            return [
                'label' => 'Belum Ada Proyek',
                'description' => 'Mahasiswa belum memiliki proyek tugas akhir aktif.',
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
                'label' => 'Review Judul',
                'description' => 'Menunggu persetujuan judul dan proposal.',
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
