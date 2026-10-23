<?php

namespace App\Services;

use App\Enums\AppRole;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Support\AcademicTerminology;
use App\Support\WitaDateTime;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Progress snapshot for student thesis projects, shared by profile cards, the
 * dosen and kaprodi lists, and the Progres Mahasiswa page.
 *
 * Every call runs a fixed number of queries no matter how many projects it gets.
 */
class StudentProgressSnapshot
{
    public function __construct(
        private readonly MentorshipProgressService $mentorshipProgressService,
        private readonly StudentActivityService $studentActivityService,
    ) {}

    /**
     * Last activity per project id, for the projects whose detail the viewer may see.
     * This is the only last-activity rule: StudentActivityService::latestByProject().
     * Pass the result to forProjects() to avoid computing it twice.
     *
     * @param  Collection<int, ThesisProject>  $projects
     * @return array<int, CarbonInterface>
     */
    public function lastActivity(Collection $projects, ?User $viewer, bool $detailForAll = false): array
    {
        return $this->studentActivityService->latestByProject($this->detailProjects($projects, $viewer, $detailForAll));
    }

    /**
     * Snapshot for each project, keyed by project id.
     *
     * Detail (supervision counts, last activity, inactivity, status) is included only
     * when $detailForAll is true or the viewer may see it for that project. Everyone
     * else gets the phase only.
     *
     * @param  Collection<int, ThesisProject>  $projects
     * @param  array<int, CarbonInterface>|null  $lastActivity  Precomputed lastActivity() result; computed here when null.
     * @return array<int, array{phase: ?string, phaseLabel: string, detail: ?array{statusLabel: ?string, supervisors: list<array{name: string, roleLabel: string, count: int}>, lastActivityAt: ?string, isInactive: bool, ticket: array{suspended: bool, validUntil: ?string}}}>
     */
    public function forProjects(Collection $projects, ?User $viewer, bool $detailForAll = false, ?array $lastActivity = null): array
    {
        if ($projects->isEmpty()) {
            return [];
        }

        $detailProjects = $this->detailProjects($projects, $viewer, $detailForAll);
        $lastActivity ??= $this->studentActivityService->latestByProject($detailProjects);
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
                $snapshot['detail'] = $this->detail($project, $lastActivity[(int) $project->getKey()] ?? null, $supervisionCounts);
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
     * Loads what canViewDetail() reads, then keeps the projects the viewer may see in detail.
     *
     * @param  Collection<int, ThesisProject>  $projects
     * @return Collection<int, ThesisProject>
     */
    private function detailProjects(Collection $projects, ?User $viewer, bool $detailForAll): Collection
    {
        $viewer?->loadMissing('roles');

        $projects->loadMissing([
            'student.mahasiswaProfile',
            'activeSupervisorAssignments.lecturer',
            'defenses.examiners',
        ]);

        return $projects
            ->filter(fn (ThesisProject $project): bool => $detailForAll || $this->canViewDetail($project, $viewer))
            ->values();
    }

    /**
     * @param  array<string, int>  $supervisionCounts
     * @return array{statusLabel: ?string, supervisors: list<array{name: string, roleLabel: string, count: int}>, lastActivityAt: ?string, isInactive: bool, ticket: array{suspended: bool, validUntil: ?string}}
     */
    private function detail(ThesisProject $project, ?CarbonInterface $lastActivity, array $supervisionCounts): array
    {
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
