<?php

namespace App\Services;

use App\Models\MentorshipDocument;
use App\Models\MentorshipSchedule;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisProject;
use App\Models\ThesisRevision;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Support\AcademicTerminology;
use App\Support\WitaDateTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Payloads for the dosen dashboard: what the lecturer has to do now, the agenda
 * for today and the next seven days, and supervised students sorted by need.
 *
 * Every count is one aggregate query; students flagged by StudentAttention come
 * from the shared progress services, never from per-student queries.
 */
class DosenDashboardService
{
    public const AGENDA_DAYS = 7;

    public const STUDENT_LIMIT = 6;

    public function __construct(
        private readonly DosenBimbinganService $dosenBimbinganService,
        private readonly DosenScheduleWorkspaceService $dosenScheduleWorkspaceService,
        private readonly StudentActivityService $studentActivityService,
        private readonly StudentProgressSnapshot $studentProgressSnapshot,
        private readonly StudentAttention $studentAttention,
        private readonly UserProfilePresenter $userProfilePresenter,
    ) {}

    /**
     * The supervised projects (current and active) with their last activity and attention reasons.
     *
     * @param  EloquentCollection<int, ThesisSupervisorAssignment>  $assignments  DosenBimbinganService::activeAssignmentsWithStudent()
     * @return array{projects: EloquentCollection<int, ThesisProject>, lastActivity: array<int, \Carbon\CarbonInterface>, attention: array<int, list<array{key: string, label: string}>>}
     */
    public function supervision(User $lecturer, EloquentCollection $assignments): array
    {
        $projects = new EloquentCollection(
            $assignments->pluck('project')->filter()->unique('id')->values()->all(),
        );
        $lastActivity = $this->studentProgressSnapshot->lastActivity($projects, $lecturer);

        return [
            'projects' => $projects,
            'lastActivity' => $lastActivity,
            'attention' => $this->studentAttention->forSupervisedProjects($projects, $lecturer, $lastActivity),
        ];
    }

    /**
     * Items for "Perlu tindakan", most urgent first. Items with a zero count are left out.
     *
     * @param  array<int, list<array{key: string, label: string}>>  $attention
     * @return list<array{key: string, title: string, description: string, count: int, href: string, actionLabel: string}>
     */
    public function actionItems(User $lecturer, array $attention): array
    {
        $terms = AcademicTerminology::neutral();
        $examLabel = $terms['proposalExamShort'].' / '.$terms['finalExam'];
        $scheduleStudentIds = $this->scheduleStudentIds($lecturer);

        $schedules = MentorshipSchedule::query()
            ->where('lecturer_user_id', $lecturer->getKey())
            ->whereIn('student_user_id', $scheduleStudentIds);

        $pendingSchedules = (clone $schedules)->where('status', 'pending')->count();
        $overdueSchedules = (clone $schedules)
            ->whereIn('status', ['approved', 'rescheduled'])
            ->where('scheduled_for', '<', now())
            ->count();

        $submittedDocuments = MentorshipDocument::query()
            ->where('lecturer_user_id', $lecturer->getKey())
            ->whereIn('student_user_id', $this->dosenBimbinganService->activeStudentIds($lecturer))
            ->where('status', 'submitted')
            ->count();

        $defensesToGrade = ThesisDefenseExaminer::query()
            ->where('lecturer_user_id', $lecturer->getKey())
            ->where('decision', 'pending')
            ->whereHas('defense', fn (Builder $query) => $query
                ->where('status', 'scheduled')
                ->where('scheduled_for', '<', now())
                ->whereHas('project', fn (Builder $project) => $this->openProject($project)))
            ->count();

        // Read through the aggregate status so structured revision rounds (#15) keep working.
        $revisionsToVerify = ThesisRevision::query()
            ->where('requested_by_user_id', $lecturer->getKey())
            ->where('status', 'submitted')
            ->whereHas('project', fn (Builder $project) => $this->openProject($project))
            ->count();

        $inactive = collect($attention)
            ->filter(fn (array $reasons): bool => collect($reasons)->contains('key', StudentAttention::REASON_INACTIVE))
            ->count();

        $items = [
            ['key' => 'defenses_to_grade', 'count' => $defensesToGrade, 'title' => $examLabel.' menunggu penilaian Anda', 'description' => 'Ujian sudah berlangsung dan Anda belum memberi keputusan.', 'href' => '/dosen/seminar-proposal?status=pending', 'actionLabel' => 'Beri penilaian'],
            ['key' => 'revisions_to_verify', 'count' => $revisionsToVerify, 'title' => 'Revisi menunggu verifikasi Anda', 'description' => 'Mahasiswa sudah mengirim perbaikan dari revisi yang Anda minta.', 'href' => '/dosen/seminar-proposal?status=revisi', 'actionLabel' => 'Verifikasi revisi'],
            ['key' => 'pending_schedules', 'count' => $pendingSchedules, 'title' => 'Permintaan jadwal bimbingan menunggu keputusan', 'description' => 'Setujui, jadwalkan ulang, atau tolak permintaan mahasiswa.', 'href' => '/dosen/jadwal-bimbingan', 'actionLabel' => 'Putuskan jadwal'],
            ['key' => 'overdue_sessions', 'count' => $overdueSchedules, 'title' => 'Bimbingan sudah lewat, belum ditandai selesai', 'description' => 'Tandai selesai agar terhitung di minimal bimbingan.', 'href' => '/dosen/jadwal-bimbingan?upcomingFilter=lewat', 'actionLabel' => 'Tandai selesai'],
            ['key' => 'documents_to_review', 'count' => $submittedDocuments, 'title' => 'Dokumen menunggu review', 'description' => 'Dokumen mahasiswa yang belum Anda setujui atau minta direvisi.', 'href' => '/dosen/dokumen-revisi?status='.rawurlencode('Perlu Review'), 'actionLabel' => 'Review dokumen'],
            ['key' => 'inactive_students', 'count' => $inactive, 'title' => 'Mahasiswa bimbingan tidak aktif lebih dari '.StudentAttention::INACTIVE_DAYS.' hari', 'description' => 'Belum ada dokumen, pesan, atau bimbingan selesai dalam waktu itu.', 'href' => '/dosen/mahasiswa-bimbingan?activeAttention=inactive', 'actionLabel' => 'Lihat mahasiswa'],
        ];

        return collect($items)
            ->filter(fn (array $item): bool => $item['count'] > 0)
            ->values()
            ->all();
    }

    /**
     * Bimbingan and ujian from the start of today through the next AGENDA_DAYS days, grouped by day.
     *
     * @return list<array{label: string, isToday: bool, events: list<array{id: string, kind: string, title: string, subtitle: string, time: string, href: string}>}>
     */
    public function agenda(User $lecturer): array
    {
        $today = CarbonImmutable::now(WitaDateTime::TIMEZONE)->startOfDay();
        $until = $today->addDays(self::AGENDA_DAYS)->endOfDay();

        return collect($this->dosenScheduleWorkspaceService->workspaceEvents($lecturer))
            ->filter(fn (array $event): bool => match ($event['category']) {
                'ujian' => $event['status'] === 'scheduled',
                default => in_array($event['status'], ['approved', 'rescheduled'], true),
            })
            ->map(function (array $event): array {
                $event['startAt'] = CarbonImmutable::parse((string) $event['start'])->setTimezone(WitaDateTime::TIMEZONE);

                return $event;
            })
            ->filter(fn (array $event): bool => $event['startAt']->betweenIncluded($today, $until))
            ->sortBy('startAt')
            ->groupBy(fn (array $event): string => $event['startAt']->format('Y-m-d'))
            ->map(function (Collection $events, string $day) use ($today): array {
                $date = CarbonImmutable::parse($day, WitaDateTime::TIMEZONE)->locale('id');
                $isToday = $date->isSameDay($today);

                return [
                    'label' => $isToday
                        ? 'Hari ini, '.$date->translatedFormat('j F')
                        : ($date->isSameDay($today->addDay()) ? 'Besok, '.$date->translatedFormat('j F') : $date->translatedFormat('l, j F')),
                    'isToday' => $isToday,
                    'events' => $events->map(fn (array $event): array => [
                        'id' => (string) $event['id'],
                        'kind' => $event['category'] === 'ujian' ? $event['title'] : 'Bimbingan',
                        'title' => (string) $event['topic'],
                        'subtitle' => collect([$event['person'] ?? null, $event['location'] ?? null])
                            ->filter(fn ($part): bool => filled($part) && $part !== '-')
                            ->implode(' · '),
                        'time' => $event['startAt']->format('H.i'),
                        'href' => $event['category'] === 'ujian' ? '/dosen/seminar-proposal' : '/dosen/jadwal-bimbingan',
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Supervised students, those with attention reasons first (most reasons, then
     * longest quiet), capped at STUDENT_LIMIT. Cards carry the shared progress snapshot.
     *
     * @param  array{projects: EloquentCollection<int, ThesisProject>, lastActivity: array<int, \Carbon\CarbonInterface>, attention: array<int, list<array{key: string, label: string}>>}  $supervision
     * @return list<array<string, mixed>>
     */
    public function studentCards(User $lecturer, array $supervision): array
    {
        $top = $supervision['projects']
            ->sortBy([
                fn (ThesisProject $a, ThesisProject $b): int => count($supervision['attention'][$b->getKey()] ?? []) <=> count($supervision['attention'][$a->getKey()] ?? []),
                fn (ThesisProject $a, ThesisProject $b): int => ($supervision['lastActivity'][$a->getKey()] ?? $a->started_at ?? Carbon::now())->getTimestamp()
                    <=> ($supervision['lastActivity'][$b->getKey()] ?? $b->started_at ?? Carbon::now())->getTimestamp(),
                fn (ThesisProject $a, ThesisProject $b): int => strcmp((string) $a->student?->name, (string) $b->student?->name),
            ])
            ->take(self::STUDENT_LIMIT)
            ->values();

        if ($top->isEmpty()) {
            return [];
        }

        $topProjects = new EloquentCollection($top->all());
        $progress = $this->studentProgressSnapshot->forProjects($topProjects, $lecturer, lastActivity: $supervision['lastActivity']);
        (new EloquentCollection($top->pluck('student')->filter()->all()))->loadMissing([
            'roles',
            'adminProfile.programStudi',
            'dosenProfile.programStudi',
            'expertiseFields',
            'activeDosenProgramStudiAssignments.programStudi',
            'kaprodiAssignment.programStudi',
            'mahasiswaProfile.programStudi',
        ]);

        return $top
            ->map(function (ThesisProject $project) use ($progress, $supervision): ?array {
                $summary = $this->userProfilePresenter->summary($project->student);

                if ($summary === null) {
                    return null;
                }

                return [
                    ...$summary,
                    'progress' => $progress[(int) $project->getKey()] ?? null,
                    'attention' => array_column($supervision['attention'][(int) $project->getKey()] ?? [], 'label'),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Mahasiswa the lecturer exchanges bimbingan schedules with: supervised students and the
     * students the lecturer examines, as Jadwal Bimbingan lists them, minus archived projects.
     *
     * @return list<int>
     */
    private function scheduleStudentIds(User $lecturer): array
    {
        $related = $this->dosenScheduleWorkspaceService->relatedStudentIds($lecturer);

        if ($related === []) {
            return [];
        }

        $archived = ThesisProject::query()
            ->current()
            ->whereNotNull('archived_at')
            ->whereIn('student_user_id', $related)
            ->pluck('student_user_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return array_values(array_diff($related, $archived));
    }

    /**
     * A project that still needs work: current and not archived.
     *
     * @param  Builder<ThesisProject>  $query
     */
    private function openProject(Builder $query): void
    {
        $query->current()->whereNull('archived_at');
    }
}
