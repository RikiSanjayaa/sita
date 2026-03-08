<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Events\ScheduleUpdated;
use App\Http\Controllers\Controller;
use App\Models\MentorshipChatThreadParticipant;
use App\Models\MentorshipSchedule;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Services\RealtimeNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class JadwalBimbinganController extends Controller
{
    public function __construct(
        private readonly RealtimeNotificationService $realtimeNotificationService,
    ) {}

    public function index(Request $request): Response
    {
        $student = $request->user();
        abort_if($student === null, 401);

        $schedules = MentorshipSchedule::query()
            ->with('lecturer')
            ->where('student_user_id', $student->id)
            ->latest('created_at')
            ->get();

        $advisorAssignments = ThesisSupervisorAssignment::query()
            ->with('lecturer')
            ->whereHas('project', fn($query) => $query
                ->where('student_user_id', $student->id)
                ->where('state', 'active'))
            ->where('status', 'active')
            ->get();

        $advisors = $advisorAssignments
            ->map(fn(ThesisSupervisorAssignment $assignment): array => [
                'assignmentId' => $assignment->id,
                'lecturerUserId' => $assignment->lecturer_user_id,
                'lecturerName' => $assignment->lecturer?->name ?? '-',
                'advisorType' => $assignment->role,
            ])
            ->values()
            ->all();

        $advisorLecturerIds = $advisorAssignments
            ->pluck('lecturer_user_id')
            ->map(static fn($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        // Include penguji from active threads where student is a participant
        $studentThreadIds = MentorshipChatThreadParticipant::query()
            ->where('user_id', $student->id)
            ->pluck('thread_id');

        $examiners = MentorshipChatThreadParticipant::query()
            ->with('user')
            ->whereIn('thread_id', $studentThreadIds)
            ->where('role', 'examiner')
            ->get()
            ->map(fn(MentorshipChatThreadParticipant $participant): array => [
                'assignmentId' => null, // Examiners don't have mentorship assignment IDs
                'lecturerUserId' => $participant->user_id,
                'lecturerName' => $participant->user?->name ?? '-',
                'advisorType' => 'penguji',
            ])
            ->unique('lecturerUserId')
            ->values()
            ->all();

        $allLecturers = collect(array_merge($advisors, $examiners))
            ->unique('lecturerUserId')
            ->values()
            ->all();

        $upcomingMeetings = $schedules
            ->whereIn('status', ['pending', 'approved', 'rescheduled'])
            ->sortBy(fn (MentorshipSchedule $schedule) => $schedule->scheduled_for ?? $schedule->requested_for)
            ->map(function (MentorshipSchedule $schedule) use ($advisorLecturerIds): array {
                $relationType = in_array($schedule->lecturer_user_id, $advisorLecturerIds, true)
                    ? 'pembimbing'
                    : 'penguji';

                return [
                    'id' => $schedule->id,
                    'topic' => $schedule->topic,
                    'lecturer' => $schedule->lecturer?->name ?? '-',
                    'relationType' => $relationType,
                    'requestedAt' => $schedule->requested_for?->toIso8601String(),
                    'scheduledAt' => $schedule->scheduled_for?->toIso8601String(),
                    'location' => $schedule->location ?? '-',
                    'status' => $schedule->status,
                    'lecturerNote' => $schedule->lecturer_note,
                ];
            })
            ->values()
            ->all();

        $historyMeetings = $schedules
            ->whereIn('status', ['rejected', 'completed', 'cancelled'])
            ->map(function (MentorshipSchedule $schedule) use ($advisorLecturerIds): array {
                $relationType = in_array($schedule->lecturer_user_id, $advisorLecturerIds, true)
                    ? 'pembimbing'
                    : 'penguji';

                return [
                    'id' => $schedule->id,
                    'topic' => $schedule->topic,
                    'lecturer' => $schedule->lecturer?->name ?? '-',
                    'relationType' => $relationType,
                    'scheduledAt' => $schedule->scheduled_for?->toIso8601String()
                        ?? $schedule->requested_for?->toIso8601String(),
                    'location' => $schedule->location ?? '-',
                    'status' => $schedule->status,
                    'lecturerNote' => $schedule->lecturer_note,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('jadwal-bimbingan', [
            'hasDosbing' => ! empty($allLecturers),
            'advisors' => $allLecturers,
            'upcomingMeetings' => $upcomingMeetings,
            'historyMeetings' => $historyMeetings,
            'flashMessage' => $request->session()->get('success'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $student = $request->user();
        abort_if($student === null, 401);

        $data = $request->validate([
            'topic' => ['required', 'string', 'max:255'],
            'lecturer_user_id' => ['required', 'integer'],
            'requested_for' => ['required', 'date'],
            'meeting_type' => ['required', 'in:online,offline'],
            'student_note' => ['nullable', 'string', 'max:2000'],
            'is_recurring' => ['nullable', 'boolean'],
            'recurring_pattern' => ['nullable', 'required_if:is_recurring,true', 'in:weekly,biweekly,monthly'],
            'recurring_count' => ['nullable', 'required_if:is_recurring,true', 'integer', 'min:2', 'max:12'],
        ]);

        $assignments = ThesisSupervisorAssignment::query()
            ->whereHas('project', fn($query) => $query
                ->where('student_user_id', $student->id)
                ->where('state', 'active'))
            ->where('status', 'active')
            ->get();

        $studentThreadIds = MentorshipChatThreadParticipant::query()
            ->where('user_id', $student->id)
            ->pluck('thread_id');

        $examinerUserIds = MentorshipChatThreadParticipant::query()
            ->whereIn('thread_id', $studentThreadIds)
            ->where('role', 'examiner')
            ->pluck('user_id');

        $isAssigned = $assignments->contains('lecturer_user_id', (int) $data['lecturer_user_id']);
        $isExaminer = $examinerUserIds->contains((int) $data['lecturer_user_id']);

        if ($assignments->isEmpty() && $examinerUserIds->isEmpty()) {
            return back()->withErrors([
                'topic' => 'Belum ada dosen pembimbing atau penguji aktif. Hubungi admin.',
            ]);
        }

        if (! $isAssigned && ! $isExaminer) {
            return back()->withErrors([
                'lecturer_user_id' => 'Pilih dosen pembimbing atau penguji yang valid.',
            ]);
        }

        $isRecurring = ! empty($data['is_recurring']);
        $recurringCount = $isRecurring ? (int) $data['recurring_count'] : 1;
        $recurringPattern = $isRecurring ? $data['recurring_pattern'] : null;
        $recurringGroupId = $isRecurring ? Str::uuid()->toString() : null;

        $baseDate = new \DateTime($data['requested_for']);

        for ($i = 0; $i < $recurringCount; $i++) {
            $scheduleDate = clone $baseDate;

            if ($i > 0 && $recurringPattern !== null) {
                $scheduleDate = $this->calculateNextDate($baseDate, $recurringPattern, $i);
            }

            MentorshipSchedule::query()->create([
                'student_user_id' => $student->id,
                'lecturer_user_id' => (int) $data['lecturer_user_id'],
                'mentorship_assignment_id' => null,
                'topic' => trim($data['topic']),
                'status' => 'pending',
                'requested_for' => $scheduleDate->format('Y-m-d H:i:s'),
                'scheduled_for' => null,
                'location' => $data['meeting_type'] === 'online' ? 'Online (akan ditentukan dosen)' : 'Offline (akan ditentukan dosen)',
                'student_note' => $data['student_note'] ?? null,
                'lecturer_note' => null,
                'created_by_user_id' => $student->id,
                'is_recurring' => $isRecurring,
                'recurring_pattern' => $recurringPattern,
                'recurring_count' => $recurringCount,
                'recurring_group_id' => $recurringGroupId,
                'recurring_index' => $isRecurring ? $i + 1 : null,
            ]);
        }

        $this->broadcastScheduleUpdated((int) $data['lecturer_user_id']);
        $this->broadcastScheduleUpdated($student->id);

        $lecturer = User::query()->find((int) $data['lecturer_user_id']);

        if ($lecturer !== null) {
            $message = $isRecurring
                ? sprintf('%s mengajukan %d jadwal bimbingan berulang.', $student->name, $recurringCount)
                : sprintf('%s mengajukan jadwal bimbingan baru.', $student->name);

            $this->realtimeNotificationService->notifyUser($lecturer, 'jadwalBimbingan', [
                'title' => 'Permintaan jadwal bimbingan baru',
                'description' => $message,
                'url' => '/dosen/jadwal-bimbingan',
                'icon' => 'calendar-clock',
                'createdAt' => now()->toIso8601String(),
            ]);
        }

        $successMessage = $isRecurring
            ? sprintf('%d permintaan jadwal bimbingan berulang berhasil dikirim.', $recurringCount)
            : 'Permintaan jadwal bimbingan berhasil dikirim.';

        return redirect()
            ->route('mahasiswa.jadwal-bimbingan')
            ->with('success', $successMessage);
    }

    private function calculateNextDate(\DateTime $baseDate, string $pattern, int $occurrence): \DateTime
    {
        $nextDate = clone $baseDate;

        return match ($pattern) {
            'weekly' => $nextDate->modify("+{$occurrence} weeks"),
            'biweekly' => $nextDate->modify('+'.($occurrence * 2).' weeks'),
            'monthly' => $nextDate->modify("+{$occurrence} months"),
            default => $nextDate,
        };
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
