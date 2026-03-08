<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatRead;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipChatThreadParticipant;
use App\Models\MentorshipSchedule;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Services\UserProfilePresenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly UserProfilePresenter $userProfilePresenter,
    ) {}

    public function __invoke(): Response
    {
        /** @var User|null $student */
        $student = request()->user();
        abort_if($student === null, 401);

        $project = $this->resolveProjectForStudent($student);
        $workflow = $project instanceof ThesisProject
            ? $this->resolveProjectWorkflow($project)
            : [
                'key' => 'not_started',
                'label' => 'Belum Memulai',
                'description' => 'Anda belum memiliki proyek tugas akhir aktif.',
            ];

        $advisorAssignments = $project?->activeSupervisorAssignments
            ? $project->activeSupervisorAssignments->sortBy('role')->values()
            : collect();

        $advisorProfiles = $advisorAssignments
            ->map(fn(ThesisSupervisorAssignment $assignment): ?array => $this->userProfilePresenter->summary($assignment->lecturer))
            ->filter()
            ->values()
            ->all();

        $advisorLecturerIds = $advisorAssignments
            ->pluck('lecturer_user_id')
            ->map(static fn($id): int => (int) $id)
            ->unique()
            ->values();

        $threadIds = MentorshipChatThread::query()
            ->where('student_user_id', $student->id)
            ->pluck('id');

        $unreadMessages = $this->countUnreadMessages($student->id, $threadIds);
        $examinerUserIds = $this->examinerUserIds($student->id);
        $upcomingActivities = $this->upcomingActivities(
            student: $student,
            advisorLecturerIds: $advisorLecturerIds,
        );

        return Inertia::render('dashboard', [
            'summary' => [
                'studentName' => $student->name,
                'programStudi' => $student->mahasiswaProfile?->programStudi?->name,
                'projectTitle' => $project?->latestTitle?->title_id,
                'workflow' => $workflow,
                'progress' => $this->workflowProgress($workflow['key']),
                'startedAt' => $project?->started_at?->locale('id')->translatedFormat('d F Y'),
                'advisors' => $advisorProfiles,
                'hasProject' => $project instanceof ThesisProject,
            ],
            'stats' => [
                [
                    'title' => 'Status Saat Ini',
                    'value' => $workflow['label'],
                    'description' => $workflow['description'],
                    'icon' => 'file-text',
                ],
                [
                    'title' => 'Dosen Terhubung',
                    'value' => (string) count($advisorProfiles),
                    'description' => 'Pembimbing aktif yang terhubung dengan proyek Anda.',
                    'icon' => 'users',
                ],
                [
                    'title' => 'Agenda Mendatang',
                    'value' => (string) count($upcomingActivities),
                    'description' => 'Bimbingan, sempro, dan sidang terdekat.',
                    'icon' => 'calendar-clock',
                ],
                [
                    'title' => 'Pesan Belum Dibaca',
                    'value' => (string) $unreadMessages,
                    'description' => 'Aktivitas chat terbaru dari pembimbing atau penguji.',
                    'icon' => 'message-square-text',
                ],
            ],
            'quickActionState' => [
                'canSubmitTitle' => ! ($project instanceof ThesisProject),
                'canScheduleMeeting' => $advisorLecturerIds->isNotEmpty() || $examinerUserIds->isNotEmpty(),
                'canUploadDocument' => $advisorLecturerIds->isNotEmpty() || $this->activeSemproDefenses($student->id)->isNotEmpty(),
                'hasThreads' => $threadIds->isNotEmpty(),
            ],
            'upcomingActivities' => $upcomingActivities,
            'timeline' => $this->progressTimeline($project, $workflow),
        ]);
    }

    private function resolveProjectForStudent(User $student): ?ThesisProject
    {
        $activeProject = $this->projectQueryForStudent($student->id)
            ->where('state', 'active')
            ->latest('started_at')
            ->first();

        if ($activeProject instanceof ThesisProject) {
            return $activeProject;
        }

        return $this->projectQueryForStudent($student->id)
            ->latest('started_at')
            ->first();
    }

    private function projectQueryForStudent(int $studentUserId)
    {
        return ThesisProject::query()
            ->where('student_user_id', $studentUserId)
            ->with([
                'programStudi',
                'latestTitle',
                'activeSupervisorAssignments.lecturer.roles',
                'activeSupervisorAssignments.lecturer.dosenProfile.programStudi',
                'defenses' => fn($query) => $query
                    ->with(['examiners.lecturer'])
                    ->orderBy('type')
                    ->orderBy('attempt_no'),
                'revisions',
            ]);
    }

    /**
     * @param  Collection<int, int>  $threadIds
     */
    private function countUnreadMessages(int $studentUserId, Collection $threadIds): int
    {
        if ($threadIds->isEmpty()) {
            return 0;
        }

        $readsByThread = MentorshipChatRead::query()
            ->where('user_id', $studentUserId)
            ->whereIn('mentorship_chat_thread_id', $threadIds)
            ->get()
            ->keyBy('mentorship_chat_thread_id');

        return $threadIds->sum(function (int $threadId) use ($readsByThread, $studentUserId): int {
            $lastReadAt = $readsByThread->get($threadId)?->last_read_at;

            return MentorshipChatMessage::query()
                ->where('mentorship_chat_thread_id', $threadId)
                ->where('sender_user_id', '!=', $studentUserId)
                ->where('message_type', '!=', 'document_event')
                ->when(
                    $lastReadAt !== null,
                    fn($query) => $query->where('created_at', '>', $lastReadAt),
                )
                ->count();
        });
    }

    /**
     * @return Collection<int, int>
     */
    private function examinerUserIds(int $studentUserId): Collection
    {
        $threadIds = MentorshipChatThreadParticipant::query()
            ->where('user_id', $studentUserId)
            ->pluck('thread_id');

        return MentorshipChatThreadParticipant::query()
            ->whereIn('thread_id', $threadIds)
            ->where('role', 'examiner')
            ->pluck('user_id')
            ->map(static fn($id): int => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * @param  Collection<int, int>  $advisorLecturerIds
     * @return array<int, array<string, mixed>>
     */
    private function upcomingActivities(User $student, Collection $advisorLecturerIds): array
    {
        $now = now();

        $schedules = MentorshipSchedule::query()
            ->with('lecturer')
            ->where('student_user_id', $student->id)
            ->whereIn('status', ['pending', 'approved', 'rescheduled'])
            ->get()
            ->filter(function (MentorshipSchedule $schedule) use ($now): bool {
                $at = $this->activityAt($schedule->scheduled_for ?? $schedule->requested_for);

                return $at?->greaterThanOrEqualTo($now->copy()->startOfDay()) ?? false;
            })
            ->map(function (MentorshipSchedule $schedule) use ($advisorLecturerIds): array {
                $at = $this->activityAt($schedule->scheduled_for ?? $schedule->requested_for);
                $relationType = $advisorLecturerIds->contains((int) $schedule->lecturer_user_id)
                    ? 'Bimbingan'
                    : 'Konsultasi Penguji';

                return [
                    'id' => 'schedule-' . $schedule->id,
                    'type' => 'meeting',
                    'sortAt' => $at,
                    'badge' => $relationType,
                    'title' => $schedule->topic,
                    'subtitle' => collect([
                        $schedule->lecturer?->name,
                        $schedule->location,
                    ])->filter()->implode(' · '),
                    'date' => $at?->locale('id')->translatedFormat('d M Y, H:i'),
                    'status' => $this->scheduleStatusLabel($schedule->status),
                    'href' => '/mahasiswa/jadwal-bimbingan',
                ];
            });

        $defenses = ThesisDefense::query()
            ->whereHas('project', fn($query) => $query->where('student_user_id', $student->id))
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '>=', $now->copy()->startOfDay())
            ->with(['examiners.lecturer'])
            ->get()
            ->map(function (ThesisDefense $defense): array {
                $badge = $defense->type === 'sidang' ? 'Sidang' : 'Sempro';
                $subtitle = collect([
                    $defense->location,
                    $defense->mode !== null ? strtoupper($defense->mode) : null,
                    $defense->examiners
                        ->pluck('lecturer.name')
                        ->filter()
                        ->implode(', '),
                ])->filter()->implode(' · ');

                return [
                    'id' => 'defense-' . $defense->id,
                    'type' => $defense->type,
                    'sortAt' => $defense->scheduled_for,
                    'badge' => $badge,
                    'title' => $defense->type === 'sidang'
                        ? 'Sidang skripsi terjadwal'
                        : 'Seminar proposal terjadwal',
                    'subtitle' => $subtitle,
                    'date' => $defense->scheduled_for?->locale('id')->translatedFormat('d M Y, H:i'),
                    'status' => 'Terjadwal',
                    'href' => '/mahasiswa/tugas-akhir',
                ];
            });

        return $schedules
            ->concat($defenses)
            ->sortBy('sortAt')
            ->take(6)
            ->values()
            ->map(function (array $activity): array {
                unset($activity['sortAt']);

                return $activity;
            })
            ->all();
    }

    /**
     * @return Collection<int, ThesisDefense>
     */
    private function activeSemproDefenses(int $studentUserId): Collection
    {
        return ThesisDefense::query()
            ->whereHas('project', fn($query) => $query->where('student_user_id', $studentUserId))
            ->where('type', 'sempro')
            ->where(function ($query): void {
                $query->where('status', 'scheduled')
                    ->orWhere(function ($nestedQuery): void {
                        $nestedQuery->where('status', 'completed')
                            ->where('result', 'pass_with_revision');
                    });
            })
            ->get();
    }

    /**
     * @param  array{key: string, label: string, description: string}  $workflow
     * @return array<int, array<string, mixed>>
     */
    private function progressTimeline(?ThesisProject $project, array $workflow): array
    {
        $latestSempro = $project?->defenses
            ->where('type', 'sempro')
            ->sortByDesc('attempt_no')
            ->first();

        $latestSidang = $project?->defenses
            ->where('type', 'sidang')
            ->sortByDesc('attempt_no')
            ->first();

        $hasAdvisors = $project?->activeSupervisorAssignments->isNotEmpty() ?? false;

        return [
            [
                'title' => 'Pengajuan Judul',
                'description' => $project instanceof ThesisProject
                    ? $workflow['key'] === 'title_review_pending'
                    ? $workflow['description']
                    : 'Judul dan proposal sudah masuk ke alur tugas akhir.'
                    : 'Mulai dari pengajuan judul dan proposal tugas akhir.',
                'date' => $project?->latestTitle?->submitted_at?->locale('id')->translatedFormat('d M Y')
                    ?? $project?->started_at?->locale('id')->translatedFormat('d M Y'),
                'status' => $project instanceof ThesisProject
                    ? ($workflow['key'] === 'title_review_pending' ? 'current' : 'done')
                    : 'upcoming',
            ],
            [
                'title' => 'Seminar Proposal',
                'description' => $latestSempro instanceof ThesisDefense
                    ? match ($latestSempro->status) {
                        'scheduled' => 'Sempro sudah dijadwalkan. Pastikan dokumen dan kesiapan presentasi sudah lengkap.',
                        'completed' => $latestSempro->result === 'pass_with_revision'
                            ? 'Sempro selesai dengan revisi. Tindak lanjuti catatan penguji.'
                            : 'Sempro sudah selesai. Lanjutkan ke tahap berikutnya.',
                        default => 'Tahap sempro sedang diproses.',
                    }
                    : 'Sempro akan muncul setelah tahap awal siap diproses.',
                'date' => $latestSempro?->scheduled_for?->locale('id')->translatedFormat('d M Y')
                    ?? $latestSempro?->decision_at?->locale('id')->translatedFormat('d M Y'),
                'status' => $latestSempro instanceof ThesisDefense
                    ? ($latestSempro->status === 'scheduled' ? 'current' : 'done')
                    : 'upcoming',
            ],
            [
                'title' => 'Bimbingan dan Penelitian',
                'description' => $hasAdvisors
                    ? 'Dosen pembimbing aktif sudah ditetapkan. Dadwalkan bimbingan dan siapkan dokumen.'
                    : 'Tahap penelitian akan aktif setelah pembimbing atau hasil sempro ditetapkan.',
                'date' => $project?->started_at?->locale('id')->translatedFormat('d M Y'),
                'status' => $hasAdvisors
                    ? (in_array($workflow['key'], ['research_in_progress', 'sempro_passed', 'sempro_revision'], true) ? 'current' : 'done')
                    : 'upcoming',
            ],
            [
                'title' => 'Sidang Akhir',
                'description' => $latestSidang instanceof ThesisDefense
                    ? match ($latestSidang->status) {
                        'scheduled' => 'Sidang sudah terjadwal. Pastikan dokumen akhir dan revisi sempro telah siap.',
                        'completed' => $latestSidang->result === 'pass_with_revision'
                            ? 'Sidang selesai dengan revisi.'
                            : 'Sidang akhir telah selesai.',
                        default => 'Tahap sidang sedang diproses.',
                    }
                    : 'Tahap sidang akan muncul setelah penelitian selesai.',
                'date' => $latestSidang?->scheduled_for?->locale('id')->translatedFormat('d M Y')
                    ?? $latestSidang?->decision_at?->locale('id')->translatedFormat('d M Y'),
                'status' => $latestSidang instanceof ThesisDefense
                    ? ($latestSidang->status === 'scheduled' ? 'current' : 'done')
                    : 'upcoming',
            ],
        ];
    }

    /**
     * @return array{key: string, label: string, description: string}
     */
    private function resolveProjectWorkflow(ThesisProject $project): array
    {
        $latestSidang = $project->defenses
            ->where('type', 'sidang')
            ->sortByDesc('attempt_no')
            ->first();

        $hasOpenRevisions = $project->revisions->whereIn('status', ['open', 'submitted'])->isNotEmpty();
        $key = 'title_review_pending';

        if ($latestSidang instanceof ThesisDefense) {
            if ($latestSidang->status === 'scheduled') {
                $key = 'sidang_scheduled';
            } elseif ($latestSidang->status === 'completed') {
                $key = match ($latestSidang->result) {
                    'pass' => 'completed',
                    'pass_with_revision' => $hasOpenRevisions ? 'sidang_revision' : 'completed',
                    'fail' => 'sidang_failed',
                    default => 'sidang_scheduled',
                };
            }

            return [
                'key' => $key,
                'label' => $this->workflowLabel($key),
                'description' => $this->workflowDescription($key),
            ];
        }

        $latestSempro = $project->defenses
            ->where('type', 'sempro')
            ->sortByDesc('attempt_no')
            ->first();

        if ($latestSempro instanceof ThesisDefense) {
            if ($latestSempro->status === 'scheduled') {
                $key = 'sempro_scheduled';
            } elseif ($latestSempro->status === 'completed' && $latestSempro->result === 'pass_with_revision') {
                $key = $hasOpenRevisions ? 'sempro_revision' : 'research_in_progress';
            } elseif ($latestSempro->status === 'completed' && $latestSempro->result === 'pass') {
                $key = $project->activeSupervisorAssignments->isNotEmpty()
                    ? 'research_in_progress'
                    : 'sempro_passed';
            }
        } elseif ($project->phase === 'title_review') {
            $key = 'title_review_pending';
        } elseif ($project->activeSupervisorAssignments->isNotEmpty()) {
            $key = 'research_in_progress';
        }

        return [
            'key' => $key,
            'label' => $this->workflowLabel($key),
            'description' => $this->workflowDescription($key),
        ];
    }

    private function workflowLabel(string $key): string
    {
        return match ($key) {
            'title_review_pending' => 'Menunggu Persetujuan',
            'sempro_scheduled' => 'Sempro Dijadwalkan',
            'sempro_revision' => 'Revisi Sempro',
            'sempro_passed' => 'Sempro Selesai',
            'research_in_progress' => 'Penelitian Berjalan',
            'sidang_scheduled' => 'Sidang Dijadwalkan',
            'sidang_revision' => 'Revisi Sidang',
            'completed' => 'Sidang Selesai',
            'sidang_failed' => 'Sidang Tidak Lulus',
            default => 'Belum Memulai',
        };
    }

    private function workflowDescription(string $key): string
    {
        return match ($key) {
            'title_review_pending' => 'Pengajuan judul dan proposal Anda sedang ditinjau admin.',
            'sempro_scheduled' => 'Sempro sudah dijadwalkan. Siapkan proposal dan presentasi terbaik Anda.',
            'sempro_revision' => 'Sempro selesai dengan revisi. Cek catatan penguji dan unggah dokumen perbaikan.',
            'sempro_passed' => 'Tahap sempro telah selesai. Menunggu penetapan pembimbing aktif atau progres berikutnya.',
            'research_in_progress' => 'Dosen pembimbing sudah ditetapkan. Lanjutkan penelitian, bimbingan, dan pengumpulan dokumen.',
            'sidang_scheduled' => 'Sidang skripsi sudah dijadwalkan. Pastikan dokumen akhir Anda lengkap.',
            'sidang_revision' => 'Sidang selesai dengan revisi. Tindak lanjuti masukan tim sidang.',
            'completed' => 'Tahap sidang skripsi telah selesai.',
            'sidang_failed' => 'Sidang belum lulus. Segera koordinasikan langkah berikutnya dengan admin dan pembimbing.',
            default => 'Mulai dari pengajuan judul dan proposal tugas akhir Anda.',
        };
    }

    private function workflowProgress(string $key): int
    {
        return match ($key) {
            'title_review_pending' => 20,
            'sempro_scheduled' => 40,
            'sempro_revision' => 55,
            'sempro_passed' => 60,
            'research_in_progress' => 75,
            'sidang_scheduled' => 90,
            'sidang_revision' => 95,
            'completed' => 100,
            'sidang_failed' => 90,
            default => 0,
        };
    }

    private function scheduleStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Menunggu konfirmasi',
            'approved' => 'Disetujui',
            'rescheduled' => 'Dijadwal ulang',
            default => 'Terjadwal',
        };
    }

    private function activityAt(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value);
    }
}
