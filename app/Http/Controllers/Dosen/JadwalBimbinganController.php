<?php

namespace App\Http\Controllers\Dosen;

use App\Events\ScheduleUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dosen\StoreJadwalBimbinganRequest;
use App\Models\MentorshipChatThreadParticipant;
use App\Models\MentorshipSchedule;
use App\Models\User;
use App\Services\DosenBimbinganService;
use App\Services\DosenScheduleWorkspaceService;
use App\Services\MentorshipScheduleService;
use App\Services\MentorshipTicketService;
use App\Services\RealtimeNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class JadwalBimbinganController extends Controller
{
    public function __construct(
        private readonly DosenBimbinganService $dosenBimbinganService,
        private readonly DosenScheduleWorkspaceService $dosenScheduleWorkspaceService,
        private readonly MentorshipScheduleService $mentorshipScheduleService,
        private readonly RealtimeNotificationService $realtimeNotificationService,
    ) {}

    public function index(Request $request): Response
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);

        $activeStudentIds = $this->dosenBimbinganService->activeStudentIds($lecturer);

        $examinerThreadIds = MentorshipChatThreadParticipant::query()
            ->where('user_id', $lecturer->id)
            ->where('role', 'examiner')
            ->pluck('thread_id');

        $examinerStudentIds = MentorshipChatThreadParticipant::query()
            ->whereIn('thread_id', $examinerThreadIds)
            ->where('role', 'student')
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();

        $allStudentIds = collect(array_merge($activeStudentIds, $examinerStudentIds))
            ->unique()
            ->values()
            ->all();

        $schedules = MentorshipSchedule::query()
            ->with('student')
            ->where('lecturer_user_id', $lecturer->id)
            ->whereIn('student_user_id', $allStudentIds)
            ->latest('updated_at')
            ->get();

        $pendingRequests = $schedules
            ->where('status', 'pending')
            ->take(50)
            ->map(function (MentorshipSchedule $item) use ($activeStudentIds): array {
                $relationType = in_array($item->student_user_id, $activeStudentIds, true)
                    ? 'pembimbing'
                    : 'penguji';

                return [
                    'id' => $item->id,
                    'mahasiswa' => $item->student?->name ?? '-',
                    'topic' => $item->topic,
                    'relationType' => $relationType,
                    'requestedAt' => $item->requested_for?->toIso8601String(),
                    'requestedForInput' => $item->requested_for?->format('Y-m-d\TH:i'),
                    'studentNote' => $item->student_note,
                    'location' => $item->location,
                    'status' => $item->status,
                    'isRecurring' => $item->is_recurring,
                    'recurringGroupId' => $item->recurring_group_id,
                    'recurringIndex' => $item->recurring_index,
                    'recurringCount' => $item->recurring_count,
                ];
            })
            ->values()
            ->all();

        $upcomingSchedules = $schedules
            ->whereIn('status', ['approved', 'rescheduled'])
            ->sortBy('scheduled_for')
            ->take(50)
            ->map(function (MentorshipSchedule $item) use ($activeStudentIds): array {
                $relationType = in_array($item->student_user_id, $activeStudentIds, true)
                    ? 'pembimbing'
                    : 'penguji';

                return [
                    'id' => $item->id,
                    'mahasiswa' => $item->student?->name ?? '-',
                    'topic' => $item->topic,
                    'relationType' => $relationType,
                    'date' => $item->scheduled_for?->format('Y-m-d') ?? '-',
                    'time' => $item->scheduled_for?->format('H:i') ?? '-',
                    'location' => $item->location ?? '-',
                    'status' => $item->status,
                    'lecturerNote' => $item->lecturer_note,
                    'isOverdue' => $item->scheduled_for?->isPast() ?? false,
                ];
            })
            ->values()
            ->all();

        $historySchedules = $schedules
            ->whereIn('status', ['rejected', 'completed', 'cancelled'])
            ->take(50)
            ->map(function (MentorshipSchedule $item) use ($activeStudentIds): array {
                $relationType = in_array($item->student_user_id, $activeStudentIds, true)
                    ? 'pembimbing'
                    : 'penguji';

                return [
                    'id' => $item->id,
                    'mahasiswa' => $item->student?->name ?? '-',
                    'topic' => $item->topic,
                    'relationType' => $relationType,
                    'date' => $item->scheduled_for?->toIso8601String()
                        ?? $item->requested_for?->toIso8601String(),
                    'time' => $item->scheduled_for?->toIso8601String()
                        ?? $item->requested_for?->toIso8601String(),
                    'location' => $item->location ?? '-',
                    'status' => $item->status,
                    'lecturerNote' => $item->lecturer_note,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('dosen/jadwal-bimbingan', [
            'pendingRequests' => $pendingRequests,
            'upcomingSchedules' => $upcomingSchedules,
            'historySchedules' => $historySchedules,
            'supervisedStudents' => $this->mentorshipScheduleService->supervisedStudents($lecturer),
            'workspaceEvents' => $this->dosenScheduleWorkspaceService->workspaceEvents($lecturer),
            'flashMessage' => $request->session()->get('success'),
        ]);
    }

    public function store(StoreJadwalBimbinganRequest $request, MentorshipTicketService $tickets): RedirectResponse
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);

        $data = $request->validated();
        $student = User::query()->findOrFail((int) $data['student_user_id']);

        $suspension = $tickets->suspensionMessageForStudent($student->id, addressStudent: false);

        if ($suspension !== null) {
            return back()->withErrors(['ticket' => $suspension]);
        }

        $isRecurring = ! empty($data['is_recurring']);
        $recurringCount = $isRecurring ? (int) $data['recurring_count'] : 1;
        $recurringPattern = $isRecurring ? $data['recurring_pattern'] : null;

        $this->mentorshipScheduleService->createSeries(
            attributes: [
                'student_user_id' => $student->id,
                'lecturer_user_id' => $lecturer->id,
                'mentorship_assignment_id' => null,
                'topic' => trim($data['topic']),
                'status' => 'approved',
                'location' => trim($data['location']),
                'lecturer_note' => filled($data['lecturer_note'] ?? null) ? trim($data['lecturer_note']) : null,
                'created_by_user_id' => $lecturer->id,
            ],
            firstOccurrence: new \DateTime($data['requested_for']),
            scheduleOccurrences: true,
            recurringPattern: $recurringPattern,
            recurringCount: $recurringCount,
        );

        $this->broadcastScheduleUpdated($lecturer->id);
        $this->broadcastScheduleUpdated($student->id);

        $message = $isRecurring
            ? sprintf('%s membuat %d jadwal bimbingan berulang untuk Anda.', $lecturer->name, $recurringCount)
            : sprintf('%s membuat jadwal bimbingan baru untuk Anda.', $lecturer->name);

        $this->realtimeNotificationService->notifyUser($student, 'konfirmasiBimbingan', [
            'title' => 'Jadwal bimbingan baru dari dosen',
            'description' => $message,
            'url' => '/mahasiswa/jadwal-bimbingan',
            'icon' => 'calendar-clock',
            'createdAt' => now()->toIso8601String(),
        ]);

        $successMessage = $isRecurring
            ? sprintf('%d jadwal bimbingan berulang berhasil dibuat.', $recurringCount)
            : 'Jadwal bimbingan berhasil dibuat.';

        return back()->with('success', $successMessage);
    }

    public function decide(Request $request, MentorshipSchedule $schedule, MentorshipTicketService $tickets): RedirectResponse
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);
        abort_unless($schedule->lecturer_user_id === $lecturer->id, 403);

        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject,reschedule,complete,cancel'],
            'scheduled_for' => ['nullable', 'date', 'after_or_equal:now'],
            'location' => ['nullable', 'string', 'max:255'],
            'lecturer_note' => ['nullable', 'string'],
        ]);

        if (in_array($data['decision'], ['approve', 'reschedule'], true)) {
            $suspension = $tickets->suspensionMessageForStudent($schedule->student_user_id, addressStudent: false);

            if ($suspension !== null) {
                return back()->withErrors(['decision' => $suspension]);
            }
        }

        if ($data['decision'] === 'reschedule' && empty($data['scheduled_for'])) {
            return back()->withErrors([
                'scheduled_for' => 'Tanggal dan jam baru wajib diisi saat menjadwalkan ulang.',
            ]);
        }

        if (in_array($data['decision'], ['approve', 'reject', 'reschedule'], true) && blank($data['lecturer_note'] ?? null)) {
            return back()->withErrors([
                'lecturer_note' => 'Feedback dosen wajib diisi saat konfirmasi, menolak, atau menjadwalkan ulang.',
            ]);
        }

        $status = match ($data['decision']) {
            'approve' => 'approved',
            'reject' => 'rejected',
            'reschedule' => 'rescheduled',
            'complete' => 'completed',
            default => 'cancelled',
        };

        $isRejectDecision = $data['decision'] === 'reject';

        $schedule->forceFill([
            'status' => $status,
            'scheduled_for' => $isRejectDecision
                ? null
                : ($data['scheduled_for'] ?? $schedule->scheduled_for ?? $schedule->requested_for),
            'location' => $data['location'] ?? $schedule->location,
            'lecturer_note' => isset($data['lecturer_note']) ? trim((string) $data['lecturer_note']) : $schedule->lecturer_note,
        ])->save();

        $this->broadcastScheduleUpdated($schedule->lecturer_user_id);
        $this->broadcastScheduleUpdated($schedule->student_user_id);

        if ($schedule->student !== null) {
            $this->realtimeNotificationService->notifyUser($schedule->student, 'konfirmasiBimbingan', [
                'title' => 'Status jadwal bimbingan diperbarui',
                'description' => sprintf('Jadwal "%s" %s.', $schedule->topic, $status),
                'url' => '/mahasiswa/jadwal-bimbingan',
                'icon' => 'calendar-clock',
                'createdAt' => now()->toIso8601String(),
            ]);
        }

        return back()->with('success', 'Keputusan jadwal berhasil disimpan.');
    }

    public function decideRecurringGroup(Request $request, string $groupId, MentorshipTicketService $tickets): RedirectResponse
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);

        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'location' => ['nullable', 'string', 'max:255'],
            'lecturer_note' => ['nullable', 'string'],
        ]);

        if ($data['decision'] === 'approve') {
            $studentUserId = MentorshipSchedule::query()
                ->where('recurring_group_id', $groupId)
                ->where('lecturer_user_id', $lecturer->id)
                ->value('student_user_id');
            $suspension = $studentUserId === null ? null : $tickets->suspensionMessageForStudent((int) $studentUserId, addressStudent: false);

            if ($suspension !== null) {
                return back()->withErrors(['decision' => $suspension]);
            }
        }

        if (blank($data['lecturer_note'] ?? null)) {
            return back()->withErrors([
                'lecturer_note' => 'Feedback dosen wajib diisi.',
            ]);
        }

        $schedules = MentorshipSchedule::query()
            ->where('recurring_group_id', $groupId)
            ->where('lecturer_user_id', $lecturer->id)
            ->where('status', 'pending')
            ->get();

        if ($schedules->isEmpty()) {
            return back()->withErrors([
                'recurring_group' => 'Tidak ada jadwal ditemukan dalam grup ini.',
            ]);
        }

        $status = $data['decision'] === 'approve' ? 'approved' : 'rejected';
        $location = $data['location'] ?? 'Google Meet';
        $lecturerNote = trim((string) $data['lecturer_note']);

        foreach ($schedules as $schedule) {
            $schedule->forceFill([
                'status' => $status,
                'scheduled_for' => $schedule->requested_for,
                'location' => $location,
                'lecturer_note' => $lecturerNote,
            ])->save();

            $this->broadcastScheduleUpdated($schedule->lecturer_user_id);
            $this->broadcastScheduleUpdated($schedule->student_user_id);

            if ($schedule->student !== null) {
                $this->realtimeNotificationService->notifyUser($schedule->student, 'konfirmasiBimbingan', [
                    'title' => 'Status jadwal bimbingan diperbarui',
                    'description' => sprintf('Jadwal "%s" %s.', $schedule->topic, $status),
                    'url' => '/mahasiswa/jadwal-bimbingan',
                    'icon' => 'calendar-clock',
                    'createdAt' => now()->toIso8601String(),
                ]);
            }
        }

        $message = $data['decision'] === 'approve'
            ? sprintf('%d jadwal berhasil dikonfirmasi.', $schedules->count())
            : sprintf('%d jadwal berhasil ditolak.', $schedules->count());

        return back()->with('success', $message);
    }

    private function broadcastScheduleUpdated(int $userId): void
    {
        try {
            broadcast(new ScheduleUpdated($userId))->toOthers();
        } catch (Throwable $exception) {
            Log::warning('Schedule realtime update skipped because realtime server is unavailable.', [
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
