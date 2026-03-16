<?php

namespace App\Http\Controllers\Dosen;

use App\Enums\AdvisorType;
use App\Http\Controllers\Controller;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipDocument;
use App\Models\MentorshipSchedule;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\ThesisSupervisorAssignment;
use App\Services\DosenBimbinganService;
use App\Services\UserProfilePresenter;
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
        $studentIds = $assignments->pluck('project.student_user_id')->filter()->unique()->values();
        $threadIdsByStudent = MentorshipChatThread::query()
            ->whereIn('student_user_id', $studentIds)
            ->pluck('id', 'student_user_id');

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

        $rows = $assignments->map(function ($assignment) use ($latestDocumentByStudent, $latestScheduleByStudent, $threadIdsByStudent): array {
            $student = $assignment->project?->student;
            $profile = $student?->mahasiswaProfile;
            $project = $assignment->project;
            $studentUserId = $assignment->project?->student_user_id;
            $latestSchedule = $studentUserId === null ? null : $latestScheduleByStudent->get($studentUserId);
            $latestDocument = $studentUserId === null ? null : $latestDocumentByStudent->get($studentUserId);
            $threadId = $studentUserId === null ? null : $threadIdsByStudent->get($studentUserId);

            $isActive = $profile?->is_active ?? true;

            $stage = $this->stageSummary($project);
            $studentSummary = $this->userProfilePresenter->summary($student);

            return [
                'nim' => $profile?->nim ?? '-',
                'name' => $student?->name ?? '-',
                'avatar' => $studentSummary['avatar'] ?? null,
                'advisorType' => $assignment->role === AdvisorType::Primary->value ? 'Pembimbing 1' : 'Pembimbing 2',
                'otherAdvisors' => $project?->activeSupervisorAssignments
                    ->filter(fn(ThesisSupervisorAssignment $activeAssignment): bool => $activeAssignment->lecturer_user_id !== $assignment->lecturer_user_id)
                    ->sortBy('role')
                    ->map(fn(ThesisSupervisorAssignment $activeAssignment): string => sprintf(
                        '%s: %s',
                        $activeAssignment->role === AdvisorType::Primary->value ? 'Pembimbing 1' : 'Pembimbing 2',
                        $activeAssignment->lecturer?->name ?? '-',
                    ))
                    ->values()
                    ->all() ?? [],
                'stageLabel' => $stage['label'],
                'stageDescription' => $stage['description'],
                'status' => $isActive ? 'Aktif' : 'Nonaktif',
                'lastUpdate' => $latestDocument?->updated_at?->diffForHumans()
                    ?? $latestSchedule?->updated_at?->diffForHumans()
                    ?? 'Belum ada aktivitas',
                'chatUrl' => $threadId === null ? null : "/dosen/pesan-bimbingan?thread={$threadId}",
                'whatsappUrl' => $studentSummary['whatsappUrl'] ?? null,
            ];
        })->values()->all();

        return Inertia::render('dosen/mahasiswa-bimbingan', [
            'mahasiswaRows' => $rows,
            'activeCount' => count($rows),
            'capacityLimit' => $this->dosenBimbinganService->lecturerQuota($lecturer),
        ]);
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

        $latestSidang = $project->defenses
            ->where('type', 'sidang')
            ->sortByDesc('attempt_no')
            ->first();

        if ($latestSidang instanceof ThesisDefense) {
            return match ($latestSidang->status) {
                'scheduled' => [
                    'label' => 'Sidang Terjadwal',
                    'description' => 'Mahasiswa bersiap menuju sidang akhir.',
                ],
                'completed' => [
                    'label' => $latestSidang->result === 'pass_with_revision' ? 'Revisi Sidang' : 'Sidang Selesai',
                    'description' => $latestSidang->result === 'pass_with_revision'
                        ? 'Masih ada revisi sidang yang perlu dipantau.'
                        : 'Tahap sidang sudah selesai.',
                ],
                default => [
                    'label' => 'Tahap Sidang',
                    'description' => 'Mahasiswa sedang berada pada fase sidang.',
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
                    'label' => 'Sempro Terjadwal',
                    'description' => 'Mahasiswa sedang menuju pelaksanaan seminar proposal.',
                ],
                'completed' => [
                    'label' => $latestSempro->result === 'pass_with_revision' ? 'Revisi Sempro' : 'Penelitian Berjalan',
                    'description' => $latestSempro->result === 'pass_with_revision'
                        ? 'Perlu menindaklanjuti revisi sempro.'
                        : 'Sempro selesai, lanjut ke fase penelitian.',
                ],
                default => [
                    'label' => 'Tahap Sempro',
                    'description' => 'Tahap sempro sedang diproses.',
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
