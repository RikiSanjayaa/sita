<?php

namespace App\Services;

use App\Models\ThesisProject;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Why a supervised mahasiswa needs the lecturer's attention: no activity for
 * INACTIVE_DAYS, a lapsed masa aktif bimbingan, or fewer completed bimbingan
 * than the minimum. Shared by the dosen dashboard and Mahasiswa Dosen so both
 * flag the same students.
 *
 * Constant query count however many projects it gets.
 */
class StudentAttention
{
    public const INACTIVE_DAYS = 30;

    public const REASON_INACTIVE = 'inactive';

    public const REASON_TICKET = 'ticket';

    public const REASON_MINIMUM = 'minimum';

    public function __construct(private readonly MentorshipProgressService $mentorshipProgressService) {}

    /**
     * Reasons per project id, only for projects with at least one. Each reason is a
     * key and the label shown to the lecturer.
     *
     * @param  Collection<int, ThesisProject>  $projects  projects supervised by $lecturer
     * @param  array<int, CarbonInterface>  $lastActivity  StudentActivityService::latestByProject() result
     * @return array<int, list<array{key: string, label: string}>>
     */
    public function forSupervisedProjects(Collection $projects, User $lecturer, array $lastActivity): array
    {
        if ($projects->isEmpty()) {
            return [];
        }

        $progress = $this->mentorshipProgressService->forProjects($projects);
        $cutoff = now()->subDays(self::INACTIVE_DAYS);
        $result = [];

        foreach ($projects as $project) {
            $projectId = (int) $project->getKey();
            $reasons = [];

            // A project without any activity yet counts from the day it started.
            $reference = $lastActivity[$projectId] ?? $project->started_at ?? $project->created_at;
            if ($reference instanceof CarbonInterface && $reference->lt($cutoff)) {
                $reasons[] = [
                    'key' => self::REASON_INACTIVE,
                    'label' => isset($lastActivity[$projectId])
                        ? 'Terakhir aktif '.$lastActivity[$projectId]->copy()->locale('id')->diffForHumans()
                        : 'Belum ada aktivitas',
                ];
            }

            if ($project->isMentorshipSuspended()) {
                $reasons[] = ['key' => self::REASON_TICKET, 'label' => 'Masa aktif bimbingan habis'];
            }

            foreach ($progress[$projectId] ?? [] as $row) {
                if ($row['lecturer']['id'] === (int) $lecturer->getKey() && ! $row['met']) {
                    $reasons[] = [
                        'key' => self::REASON_MINIMUM,
                        'label' => sprintf('Bimbingan %d dari minimal %d', $row['completed'], $row['minimum']),
                    ];
                }
            }

            if ($reasons !== []) {
                $result[$projectId] = $reasons;
            }
        }

        return $result;
    }
}
