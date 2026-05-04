<?php

namespace App\Services;

use App\Models\MentorshipChatThreadParticipant;
use App\Models\MentorshipSchedule;
use App\Models\ThesisDefenseExaminer;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class DosenScheduleWorkspaceService
{
    public function __construct(
        private readonly DosenBimbinganService $dosenBimbinganService,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function workspaceEvents(User $lecturer): array
    {
        return $this->mentorshipEvents($lecturer)
            ->concat($this->defenseEvents($lecturer))
            ->sortBy('start')
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public function relatedStudentIds(User $lecturer): array
    {
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

        return collect(array_merge($activeStudentIds, $examinerStudentIds))
            ->map(static fn($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function mentorshipEvents(User $lecturer): Collection
    {
        $activeStudentIds = $this->dosenBimbinganService->activeStudentIds($lecturer);
        $relatedStudentIds = $this->relatedStudentIds($lecturer);

        if ($relatedStudentIds === []) {
            return collect();
        }

        return MentorshipSchedule::query()
            ->with('student')
            ->where('lecturer_user_id', $lecturer->id)
            ->whereIn('student_user_id', $relatedStudentIds)
            ->whereNotNull('requested_for')
            ->get()
            ->map(function (MentorshipSchedule $schedule) use ($activeStudentIds): array {
                $startAt = $schedule->scheduled_for ?? $schedule->requested_for;
                $endAt = $startAt?->copy()->addHour();
                $relationType = in_array($schedule->student_user_id, $activeStudentIds, true)
                    ? 'Bimbingan'
                    : 'Konsultasi Penguji';

                return [
                    'id' => 'schedule-'.$schedule->id,
                    'category' => 'bimbingan',
                    'title' => $relationType,
                    'topic' => $schedule->topic,
                    'person' => $schedule->student?->name ?? '-',
                    'start' => $startAt?->toIso8601String(),
                    'end' => $endAt?->toIso8601String() ?? $startAt?->toIso8601String(),
                    'location' => $schedule->location ?? '-',
                    'status' => $schedule->status,
                    'personRole' => 'student',
                ];
            })
            ->filter(fn(array $event): bool => is_string($event['start']) && $event['start'] !== '');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function defenseEvents(User $lecturer): Collection
    {
        return ThesisDefenseExaminer::query()
            ->with(['defense.project.student'])
            ->where('lecturer_user_id', $lecturer->id)
            ->whereHas('defense', fn($query) => $query->whereNotNull('scheduled_for'))
            ->get()
            ->map(function (ThesisDefenseExaminer $examiner): ?array {
                $defense = $examiner->defense;
                $student = $defense?->project?->student;
                $startAt = $defense?->scheduled_for;

                if (! $startAt instanceof CarbonInterface) {
                    return null;
                }

                $label = $defense?->type === 'sidang' ? 'Sidang' : 'Sempro';
                $status = $defense?->status === 'scheduled'
                    ? 'scheduled'
                    : ($defense?->status ?? 'cancelled');

                return [
                    'id' => 'defense-'.$defense?->id,
                    'category' => 'ujian',
                    'title' => $label,
                    'topic' => $label,
                    'person' => $student?->name ?? '-',
                    'start' => $startAt?->toIso8601String(),
                    'end' => $startAt?->copy()->addHours(2)->toIso8601String(),
                    'location' => $defense?->location ?? '-',
                    'status' => $status,
                    'personRole' => 'student',
                ];
            })
            ->filter();
    }
}
