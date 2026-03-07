<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Enums\AssignmentStatus;
use App\Events\ScheduleUpdated;
use App\Http\Controllers\Controller;
use App\Models\MentorshipAssignment;
use App\Models\MentorshipChatThreadParticipant;
use App\Models\MentorshipSchedule;
use App\Models\User;
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
        private readonly RealtimeNotificationService $realtimeNotificationService,
    ) {
    }

    public function index(Request $request): Response
    {
        $student = $request->user();
        abort_if($student === null, 401);

        $schedules = MentorshipSchedule::query()
            ->with('lecturer')
            ->where('student_user_id', $student->id)
            ->latest('created_at')
            ->get();

        $advisors = MentorshipAssignment::query()
            ->with('lecturer')
            ->where('student_user_id', $student->id)
            ->where('status', AssignmentStatus::Active->value)
            ->get()
            ->map(fn(MentorshipAssignment $assignment): array => [
                'assignmentId' => $assignment->id,
                'lecturerUserId' => $assignment->lecturer_user_id,
                'lecturerName' => $assignment->lecturer?->name ?? '-',
                'advisorType' => $assignment->advisor_type,
            ])
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
            ->sortBy(fn(MentorshipSchedule $schedule) => $schedule->scheduled_for ?? $schedule->requested_for)
            ->map(function (MentorshipSchedule $schedule): array {
                return [
                    'id' => $schedule->id,
                    'topic' => $schedule->topic,
                    'lecturer' => $schedule->lecturer?->name ?? '-',
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
            ->map(function (MentorshipSchedule $schedule): array {
                return [
                    'id' => $schedule->id,
                    'topic' => $schedule->topic,
                    'lecturer' => $schedule->lecturer?->name ?? '-',
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
            'hasDosbing' => !empty($allLecturers),
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
        ]);

        $assignments = MentorshipAssignment::query()
            ->where('student_user_id', $student->id)
            ->where('status', AssignmentStatus::Active->value)
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

        if (!$isAssigned && !$isExaminer) {
            return back()->withErrors([
                'lecturer_user_id' => 'Pilih dosen pembimbing atau penguji yang valid.',
            ]);
        }

        $selectedAssignment = $assignments->firstWhere('lecturer_user_id', (int) $data['lecturer_user_id']);

        MentorshipSchedule::query()->create([
            'student_user_id' => $student->id,
            'lecturer_user_id' => (int) $data['lecturer_user_id'],
            'mentorship_assignment_id' => $selectedAssignment?->id,
            'topic' => trim($data['topic']),
            'status' => 'pending',
            'requested_for' => $data['requested_for'],
            'scheduled_for' => null,
            'location' => $data['meeting_type'] === 'online' ? 'Online (akan ditentukan dosen)' : 'Offline (akan ditentukan dosen)',
            'student_note' => $data['student_note'] ?? null,
            'lecturer_note' => null,
            'created_by_user_id' => $student->id,
        ]);

        $this->broadcastScheduleUpdated((int) $data['lecturer_user_id']);
        $this->broadcastScheduleUpdated($student->id);

        $lecturer = User::query()->find((int) $data['lecturer_user_id']);

        if ($lecturer !== null) {
            $this->realtimeNotificationService->notifyUser($lecturer, 'jadwalBimbingan', [
                'title' => 'Permintaan jadwal bimbingan baru',
                'description' => sprintf('%s mengajukan jadwal bimbingan baru.', $student->name),
                'url' => '/dosen/jadwal-bimbingan',
                'icon' => 'calendar-clock',
                'createdAt' => now()->toIso8601String(),
            ]);
        }

        return redirect()
            ->route('mahasiswa.jadwal-bimbingan')
            ->with('success', 'Permintaan jadwal bimbingan berhasil dikirim.');
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
