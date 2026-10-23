<?php

namespace App\Services;

use App\Enums\AppRole;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipDocument;
use App\Models\MentorshipSchedule;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Support\AcademicTerminology;
use App\Support\WitaDateTime;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Progress snapshot for student thesis projects, shared by profile cards, the
 * dosen and kaprodi lists, and the Progres Mahasiswa page.
 *
 * Every call runs a fixed number of queries no matter how many projects it gets.
 */
class StudentProgressSnapshot
{
    public function __construct(private readonly MentorshipProgressService $mentorshipProgressService) {}

    /**
     * Snapshot for each project, keyed by project id.
     *
     * Detail (supervision counts, last activity, inactivity, status) is included only
     * when $detailForAll is true or the viewer may see it for that project. Everyone
     * else gets the phase only.
     *
     * @param  Collection<int, ThesisProject>  $projects
     * @return array<int, array{phase: ?string, phaseLabel: string, detail: ?array{statusLabel: ?string, supervisors: list<array{name: string, roleLabel: string, count: int}>, lastActivityAt: ?string, isInactive: bool, ticket: array{suspended: bool, validUntil: ?string}}}>
     */
    public function forProjects(Collection $projects, ?User $viewer, bool $detailForAll = false): array
    {
        if ($projects->isEmpty()) {
            return [];
        }

        $viewer?->loadMissing('roles');

        $projects->loadMissing([
            'student.mahasiswaProfile',
            'activeSupervisorAssignments.lecturer',
            'defenses.examiners',
        ]);

        $detailProjects = $projects
            ->filter(fn (ThesisProject $project): bool => $detailForAll || $this->canViewDetail($project, $viewer))
            ->values();

        $activity = $this->lastActivityByStudent($detailProjects);
        $supervisionCounts = $this->supervisionCounts($detailProjects);
        $detailIds = $detailProjects->mapWithKeys(fn (ThesisProject $project): array => [(int) $project->getKey() => true]);
        $result = [];
        foreach ($projects as $project) {
            $phase = $project->phase;
            $snapshot = [
                'phase' => $phase,
                'phaseLabel' => AcademicTerminology::phaseLabel($phase),
                'detail' => null,
            ];

            if (isset($detailIds[(int) $project->getKey()])) {
                $snapshot['detail'] = $this->detail($project, $activity, $supervisionCounts);
            }

            $result[(int) $project->getKey()] = $snapshot;
        }

        return $result;
    }

    /**
     * Whether the viewer may see supervision counts, activity and inactivity for a project.
     * Same access rule as the full-title check in UserProfilePresenter.
     */
    public function canViewDetail(ThesisProject $project, ?User $viewer): bool
    {
        if (! $viewer instanceof User) {
            return false;
        }

        if ((int) $project->student_user_id === (int) $viewer->getKey()
            || $viewer->hasAnyRole([AppRole::Admin, AppRole::SuperAdmin])
            || ($viewer->kaprodiAssignment?->program_studi_id !== null
                && $viewer->kaprodiAssignment->program_studi_id === $project->program_studi_id)) {
            return true;
        }

        if ($project->activeSupervisorAssignments->contains('lecturer_user_id', $viewer->getKey())) {
            return true;
        }

        return $project->defenses->contains(
            fn (ThesisDefense $defense): bool => $defense->examiners->contains('lecturer_user_id', $viewer->getKey()),
        );
    }

    /**
     * @param  array{completed: array<int, CarbonInterface>, chat: array<int, CarbonInterface>, documents: array<int, CarbonInterface>}  $activity
     * @param  array<string, int>  $supervisionCounts
     * @return array{statusLabel: ?string, supervisors: list<array{name: string, roleLabel: string, count: int}>, lastActivityAt: ?string, isInactive: bool, ticket: array{suspended: bool, validUntil: ?string}}
     */
    private function detail(ThesisProject $project, array $activity, array $supervisionCounts): array
    {
        $studentId = (int) $project->student_user_id;
        $candidates = array_filter([
            $activity['completed'][$studentId] ?? null,
            $activity['chat'][$studentId] ?? null,
            $activity['documents'][$studentId] ?? null,
        ]);
        $lastActivity = $candidates === [] ? null : max($candidates);

        $reference = $lastActivity ?? $project->started_at;
        $thresholdDays = (int) config('sita.inactive_after_days', 30);
        $isInactive = $reference instanceof CarbonInterface
            && $reference->lt(now()->subDays($thresholdDays));

        $supervisors = $project->activeSupervisorAssignments
            ->sortBy('role')
            ->map(fn (ThesisSupervisorAssignment $assignment): array => [
                'name' => $assignment->lecturer?->name ?? '-',
                'roleLabel' => $assignment->role === 'primary' ? 'Pembimbing 1' : 'Pembimbing 2',
                'count' => $supervisionCounts[$this->pairKey($project, $assignment)] ?? 0,
            ])
            ->values()
            ->all();

        return [
            'statusLabel' => $this->statusLabel($project),
            'supervisors' => $supervisors,
            'lastActivityAt' => $lastActivity?->format('d M Y'),
            'isInactive' => $isInactive,
            'ticket' => [
                'suspended' => $project->isMentorshipSuspended(),
                'validUntil' => $project->mentorship_valid_until?->format('Y-m-d'),
            ],
        ];
    }

    /**
     * Latest student-side activity per student: a completed bimbingan session, a chat
     * message the student sent, or a document the student uploaded. Login time is ignored.
     *
     * @param  Collection<int, ThesisProject>  $projects
     * @return array{completed: array<int, CarbonInterface>, chat: array<int, CarbonInterface>, documents: array<int, CarbonInterface>}
     */
    private function lastActivityByStudent(Collection $projects): array
    {
        $studentIds = $projects->pluck('student_user_id')->map(fn ($id): int => (int) $id)->unique()->values()->all();
        if ($studentIds === []) {
            return ['completed' => [], 'chat' => [], 'documents' => []];
        }

        $completed = [];
        MentorshipSchedule::query()
            ->where('status', 'completed')
            ->whereIn('student_user_id', $studentIds)
            ->get(['student_user_id', 'scheduled_for', 'requested_for'])
            ->each(function (MentorshipSchedule $schedule) use (&$completed): void {
                $at = $schedule->scheduled_for ?? $schedule->requested_for;
                if ($at === null) {
                    return;
                }
                $studentId = (int) $schedule->student_user_id;
                if (! isset($completed[$studentId]) || $at->gt($completed[$studentId])) {
                    $completed[$studentId] = $at;
                }
            });

        $chat = MentorshipChatMessage::query()
            ->selectRaw('sender_user_id, max(coalesce(sent_at, created_at)) as last_at')
            ->whereIn('sender_user_id', $studentIds)
            ->groupBy('sender_user_id')
            ->get()
            ->mapWithKeys(fn (MentorshipChatMessage $row): array => [
                (int) $row->sender_user_id => Carbon::parse($row->getAttribute('last_at')),
            ])
            ->all();

        $documents = MentorshipDocument::query()
            ->selectRaw('student_user_id, max(created_at) as last_at')
            ->whereIn('student_user_id', $studentIds)
            ->whereColumn('uploaded_by_user_id', 'student_user_id')
            ->groupBy('student_user_id')
            ->get()
            ->mapWithKeys(fn (MentorshipDocument $row): array => [
                (int) $row->student_user_id => Carbon::parse($row->getAttribute('last_at')),
            ])
            ->all();

        return ['completed' => $completed, 'chat' => $chat, 'documents' => $documents];
    }

    /**
     * Completed sessions per active supervisor pair, keyed by pairKey(), in one query.
     *
     * @param  Collection<int, ThesisProject>  $projects
     * @return array<string, int>
     */
    private function supervisionCounts(Collection $projects): array
    {
        $pairs = [];
        foreach ($projects as $project) {
            foreach ($project->activeSupervisorAssignments as $assignment) {
                $pairs[] = [
                    'key' => $this->pairKey($project, $assignment),
                    'student_user_id' => (int) $project->student_user_id,
                    'lecturer_user_id' => (int) $assignment->lecturer_user_id,
                    'started_at' => $assignment->started_at,
                ];
            }
        }

        return $this->mentorshipProgressService->completedCountsForPairs($pairs);
    }

    private function pairKey(ThesisProject $project, ThesisSupervisorAssignment $assignment): string
    {
        return $project->getKey().':'.$assignment->lecturer_user_id;
    }

    /**
     * Short status line for the card: an upcoming defense date, or a revision required
     * after a defense. Null when neither applies.
     */
    private function statusLabel(ThesisProject $project): ?string
    {
        $terms = AcademicTerminology::forProject($project);

        $upcoming = $project->defenses
            ->filter(fn (ThesisDefense $defense): bool => $defense->status === 'scheduled'
                && $defense->scheduled_for !== null
                && $defense->scheduled_for->gte(now()))
            ->sortBy('scheduled_for')
            ->first();

        if ($upcoming instanceof ThesisDefense) {
            return AcademicTerminology::defenseLabel($terms, $upcoming->type, true)
                .' dijadwalkan '.WitaDateTime::format($upcoming->scheduled_for, 'd M Y', false);
        }

        $revision = $project->defenses
            ->filter(fn (ThesisDefense $defense): bool => $defense->result === 'pass_with_revision')
            ->sortByDesc('scheduled_for')
            ->first();

        if ($revision instanceof ThesisDefense) {
            return 'Revisi '.AcademicTerminology::defenseLabel($terms, $revision->type, true);
        }

        return null;
    }
}
