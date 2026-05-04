<?php

namespace App\Services;

use App\Models\MentorshipSchedule;
use App\Models\ThesisDefense;
use App\Models\ThesisRevision;
use App\Models\User;
use App\Notifications\RealtimeNotification;
use App\Support\WitaDateTime;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class ReminderDeadlineNotificationService
{
    private const WINDOW_DAY = 'day';

    private const WINDOW_HOUR = 'hour';

    public function __construct(
        private readonly RealtimeNotificationService $realtimeNotificationService,
    ) {}

    public function sendUpcomingReminders(?CarbonInterface $now = null): int
    {
        $reference = CarbonImmutable::instance($now ?? now());
        $sentCount = 0;

        foreach ($this->reminderWindows($reference) as $window => [$start, $end]) {
            $sentCount += $this->sendMentorshipScheduleReminders($window, $start, $end);
            $sentCount += $this->sendDefenseReminders($window, $start, $end);
            $sentCount += $this->sendRevisionReminders($window, $start, $end);
        }

        return $sentCount;
    }

    /**
     * @return array<string, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function reminderWindows(CarbonImmutable $now): array
    {
        return [
            self::WINDOW_DAY => [$now->addHours(23), $now->addDay()],
            self::WINDOW_HOUR => [$now, $now->addHour()],
        ];
    }

    private function sendMentorshipScheduleReminders(
        string $window,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): int {
        $count = 0;

        $schedules = MentorshipSchedule::query()
            ->with(['student', 'lecturer'])
            ->whereIn('status', ['approved', 'rescheduled'])
            ->whereNotNull('scheduled_for')
            ->whereBetween('scheduled_for', [$start, $end])
            ->get();

        foreach ($schedules as $schedule) {
            if (! $schedule->student instanceof User || ! $schedule->lecturer instanceof User || $schedule->scheduled_for === null) {
                continue;
            }

            $formattedDate = WitaDateTime::translated($schedule->scheduled_for, 'd M Y, H:i');
            $windowLabel = $this->windowLabel($window);

            $count += $this->sendReminder(
                $schedule->student,
                $this->reminderKey('mentorship_schedule', $schedule->id, $window),
                [
                    'title' => 'Reminder jadwal bimbingan',
                    'description' => sprintf(
                        'Jadwal bimbingan "%s" bersama %s %s pada %s.',
                        $schedule->topic,
                        $schedule->lecturer->name,
                        $windowLabel,
                        $formattedDate,
                    ),
                    'url' => '/mahasiswa/jadwal-bimbingan',
                    'icon' => 'calendar-clock',
                    'createdAt' => now()->toIso8601String(),
                ],
            );

            $count += $this->sendReminder(
                $schedule->lecturer,
                $this->reminderKey('mentorship_schedule', $schedule->id, $window),
                [
                    'title' => 'Reminder jadwal bimbingan',
                    'description' => sprintf(
                        'Jadwal bimbingan dengan %s %s pada %s.',
                        $schedule->student->name,
                        $windowLabel,
                        $formattedDate,
                    ),
                    'url' => '/dosen/jadwal-bimbingan',
                    'icon' => 'calendar-clock',
                    'createdAt' => now()->toIso8601String(),
                ],
            );
        }

        return $count;
    }

    private function sendDefenseReminders(
        string $window,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): int {
        $count = 0;

        $defenses = ThesisDefense::query()
            ->with(['project.student', 'examiners.lecturer'])
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_for')
            ->whereBetween('scheduled_for', [$start, $end])
            ->get();

        foreach ($defenses as $defense) {
            if (! $defense->project?->student instanceof User || $defense->scheduled_for === null) {
                continue;
            }

            $typeLabel = $defense->type === 'sidang' ? 'sidang' : 'sempro';
            $formattedDate = WitaDateTime::translated($defense->scheduled_for, 'd M Y, H:i');
            $windowLabel = $this->windowLabel($window);

            $count += $this->sendReminder(
                $defense->project->student,
                $this->reminderKey('thesis_defense', $defense->id, $window),
                [
                    'title' => sprintf('Reminder %s', $typeLabel),
                    'description' => sprintf(
                        '%s Anda %s pada %s di %s.',
                        ucfirst($typeLabel),
                        $windowLabel,
                        $formattedDate,
                        $defense->location ?? 'lokasi yang ditentukan',
                    ),
                    'url' => '/tugas-akhir',
                    'icon' => 'calendar-clock',
                    'createdAt' => now()->toIso8601String(),
                ],
            );

            $recipients = $defense->examiners
                ->map(fn ($examiner): ?User => $examiner->lecturer)
                ->filter(fn (?User $user): bool => $user instanceof User)
                ->unique('id')
                ->values();

            foreach ($recipients as $recipient) {
                $count += $this->sendReminder(
                    $recipient,
                    $this->reminderKey('thesis_defense', $defense->id, $window),
                    [
                        'title' => sprintf('Reminder %s', $typeLabel),
                        'description' => sprintf(
                            '%s %s untuk %s %s pada %s di %s.',
                            ucfirst($typeLabel),
                            $windowLabel,
                            $defense->project->student->name,
                            $window === self::WINDOW_HOUR ? 'dimulai' : 'berlangsung',
                            $formattedDate,
                            $defense->location ?? 'lokasi yang ditentukan',
                        ),
                        'url' => '/dosen/seminar-proposal',
                        'icon' => 'calendar-clock',
                        'createdAt' => now()->toIso8601String(),
                    ],
                );
            }
        }

        return $count;
    }

    private function sendRevisionReminders(
        string $window,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): int {
        $count = 0;

        $revisions = ThesisRevision::query()
            ->with(['project.student', 'defense'])
            ->where('status', 'open')
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$start, $end])
            ->get();

        foreach ($revisions as $revision) {
            if (! $revision->project?->student instanceof User || $revision->due_at === null) {
                continue;
            }

            $formattedDate = WitaDateTime::translated($revision->due_at, 'd M Y, H:i');
            $windowLabel = $this->windowLabel($window);
            $revisionContext = match ($revision->defense?->type) {
                'sidang' => 'revisi sidang',
                'sempro' => 'revisi sempro',
                default => 'revisi tugas akhir',
            };

            $count += $this->sendReminder(
                $revision->project->student,
                $this->reminderKey('thesis_revision', $revision->id, $window),
                [
                    'title' => 'Reminder deadline revisi',
                    'description' => sprintf(
                        'Deadline %s Anda %s pada %s.',
                        $revisionContext,
                        $windowLabel,
                        $formattedDate,
                    ),
                    'url' => '/tugas-akhir',
                    'icon' => 'timer',
                    'createdAt' => now()->toIso8601String(),
                ],
            );
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendReminder(User $recipient, string $reminderKey, array $payload): int
    {
        if ($this->hasReminderBeenSent($recipient, $reminderKey)) {
            return 0;
        }

        $preferences = $recipient->resolvedNotificationPreferences();

        if (($preferences['reminderDeadline'] ?? true) !== true) {
            return 0;
        }

        $this->realtimeNotificationService->notifyUser($recipient, 'reminderDeadline', [
            ...$payload,
            'reminderKey' => $reminderKey,
        ]);

        return 1;
    }

    private function hasReminderBeenSent(User $recipient, string $reminderKey): bool
    {
        return $recipient->notifications()
            ->where('type', RealtimeNotification::class)
            ->where('data->reminderKey', $reminderKey)
            ->exists();
    }

    private function reminderKey(string $type, int $id, string $window): string
    {
        return sprintf('%s:%d:%s', $type, $id, $window);
    }

    private function windowLabel(string $window): string
    {
        return match ($window) {
            self::WINDOW_DAY => 'akan berlangsung dalam 1 hari',
            self::WINDOW_HOUR => 'akan dimulai dalam 1 jam',
            default => 'segera berlangsung',
        };
    }
}
