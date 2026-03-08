<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipDocument;
use App\Models\MentorshipSchedule;
use App\Models\ThesisDefenseExaminer;
use App\Services\DosenBimbinganService;
use App\Services\DosenScheduleWorkspaceService;
use App\Services\UserProfilePresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DosenBimbinganService $dosenBimbinganService,
        private readonly DosenScheduleWorkspaceService $dosenScheduleWorkspaceService,
        private readonly UserProfilePresenter $userProfilePresenter,
    ) {}

    public function __invoke(Request $request): Response
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);

        $studentIds = $this->dosenBimbinganService->activeStudentIds($lecturer);
        $threadIds = MentorshipChatThread::query()
            ->whereIn('student_user_id', $studentIds)
            ->pluck('id')
            ->all();

        $pendingSchedules = MentorshipSchedule::query()
            ->where('lecturer_user_id', $lecturer->id)
            ->where('status', 'pending')
            ->count();

        $pendingDocuments = MentorshipDocument::query()
            ->where('lecturer_user_id', $lecturer->id)
            ->whereIn('status', ['submitted', 'needs_revision'])
            ->count();

        $upcomingDefenseCount = ThesisDefenseExaminer::query()
            ->where('lecturer_user_id', $lecturer->id)
            ->whereHas('defense', fn($query) => $query
                ->where('status', 'scheduled')
                ->whereNotNull('scheduled_for'))
            ->count();

        $unreadMessages = MentorshipChatMessage::query()
            ->whereIn('mentorship_chat_thread_id', $threadIds)
            ->where('sender_user_id', '!=', $lecturer->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $activeStudentCount = count($studentIds);
        $capacityLimit = $this->dosenBimbinganService->lecturerQuota($lecturer);

        $summaryKey = 'monitoring_stable';

        if ($pendingSchedules > 0 || $pendingDocuments > 0) {
            $summaryKey = 'needs_attention';
        } elseif ($upcomingDefenseCount > 0) {
            $summaryKey = 'defense_preparation';
        } elseif ($activeStudentCount === 0) {
            $summaryKey = 'no_active_students';
        }

        $activeStudents = $this->dosenBimbinganService
            ->activeAssignmentsWithStudent($lecturer)
            ->map(fn($assignment) => $assignment->project?->student)
            ->filter()
            ->unique('id')
            ->values();

        $workspaceEvents = collect($this->dosenScheduleWorkspaceService->workspaceEvents($lecturer));

        $upcomingActivities = $workspaceEvents
            ->filter(function (array $event): bool {
                $start = isset($event['start']) && is_string($event['start'])
                    ? Carbon::parse($event['start'])
                    : null;

                return $start?->greaterThanOrEqualTo(now()->startOfDay()) ?? false;
            })
            ->sortBy('start')
            ->take(6)
            ->map(function (array $event): array {
                $start = Carbon::parse((string) $event['start']);

                return [
                    'id' => (string) $event['id'],
                    'badge' => $event['category'] === 'ujian' ? 'Sempro / Sidang' : 'Bimbingan',
                    'title' => (string) $event['topic'],
                    'subtitle' => collect([
                        $event['person'] ?? null,
                        $event['location'] ?? null,
                    ])->filter()->implode(' · '),
                    'date' => $start->locale('id')->translatedFormat('d M Y, H:i'),
                    'href' => $event['category'] === 'ujian'
                        ? '/dosen/seminar-proposal'
                        : '/dosen/jadwal-bimbingan',
                ];
            })
            ->values()
            ->all();

        return Inertia::render('dosen/dashboard', [
            'summary' => [
                'lecturerName' => $lecturer->name,
                'programStudi' => $lecturer->dosenProfile?->programStudi?->name,
                'concentration' => $lecturer->dosenProfile?->concentration,
                'quotaLabel' => sprintf('%d/%d mahasiswa', $activeStudentCount, $capacityLimit),
                'status' => [
                    'label' => match ($summaryKey) {
                        'needs_attention' => 'Perlu Tindak Lanjut',
                        'defense_preparation' => 'Fokus Persiapan Ujian',
                        'no_active_students' => 'Belum Ada Mahasiswa Aktif',
                        default => 'Monitoring Bimbingan',
                    },
                    'description' => match ($summaryKey) {
                        'needs_attention' => 'Masih ada jadwal, dokumen, atau pesan yang menunggu respons Anda.',
                        'defense_preparation' => 'Agenda ujian sudah dekat. Pastikan penilaian dan kesiapan sempro terpantau.',
                        'no_active_students' => 'Saat ini belum ada mahasiswa aktif dalam kuota bimbingan Anda.',
                        default => 'Ritme bimbingan berjalan stabil. Pantau agenda dan kebutuhan mahasiswa secara berkala.',
                    },
                ],
                'metrics' => [
                    ['label' => 'Jadwal Pending', 'value' => (string) $pendingSchedules],
                    ['label' => 'Dokumen Review', 'value' => (string) $pendingDocuments],
                    ['label' => 'Pesan Belum Dibaca', 'value' => (string) $unreadMessages],
                    ['label' => 'Ujian Terjadwal', 'value' => (string) $upcomingDefenseCount],
                ],
            ],
            'upcomingActivities' => $upcomingActivities,
            'activeStudents' => $activeStudents
                ->take(4)
                ->map(fn($student) => $this->userProfilePresenter->summary($student))
                ->filter()
                ->values()
                ->all(),
        ]);
    }
}
