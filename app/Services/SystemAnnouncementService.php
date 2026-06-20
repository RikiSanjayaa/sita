<?php

namespace App\Services;

use App\Enums\AppRole;
use App\Models\SystemAnnouncement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SystemAnnouncementService
{
    public function __construct(
        private readonly RealtimeNotificationService $realtimeNotificationService,
    ) {}

    public function publish(SystemAnnouncement $announcement): int
    {
        if ($announcement->notified_at !== null) {
            return 0;
        }

        if (! $announcement->isPublished()) {
            $announcement->forceFill([
                'status' => SystemAnnouncement::STATUS_PUBLISHED,
                'published_at' => $announcement->published_at ?? now(),
            ])->save();
        }

        $sentCount = 0;

        foreach ($this->recipients($announcement) as $recipient) {
            $preferences = $recipient->resolvedNotificationPreferences();

            if (($preferences['pengumumanSistem'] ?? true) !== true) {
                continue;
            }

            $this->realtimeNotificationService->notifyUser($recipient, 'pengumumanSistem', [
                'title' => $announcement->title,
                'description' => $announcement->body,
                'url' => $announcement->action_url,
                'icon' => 'megaphone',
                'createdAt' => ($announcement->published_at ?? now())->toIso8601String(),
                'announcementId' => $announcement->id,
            ]);

            $sentCount++;
        }

        $announcement->forceFill([
            'published_at' => $announcement->published_at ?? now(),
            'notified_at' => now(),
        ])->save();

        return $sentCount;
    }

    /**
     * @return Collection<int, User>
     */
    public function recipients(SystemAnnouncement $announcement): Collection
    {
        $roles = $announcement->normalizedTargetRoles();

        if ($roles === []) {
            return collect();
        }

        return User::query()
            ->where(function (Builder $query) use ($announcement, $roles): void {
                foreach ($roles as $role) {
                    $query->orWhere(fn(Builder $roleQuery): Builder => $this->applyRoleScope($roleQuery, $announcement, $role));
                }
            })
            ->get();
    }

    private function applyRoleScope(Builder $query, SystemAnnouncement $announcement, string $role): Builder
    {
        $query->whereHas('roles', function (Builder $roleQuery) use ($role): void {
            $roleQuery->where('name', $role);
        });

        $programStudiIds = $announcement->resolvedProgramStudiIds();

        if ($programStudiIds === []) {
            return $query;
        }

        return match ($role) {
            AppRole::Mahasiswa->value => $query->whereHas('mahasiswaProfile', function (Builder $profileQuery) use ($programStudiIds): void {
                $profileQuery->whereIn('program_studi_id', $programStudiIds);
            }),
            AppRole::Dosen->value => $query->whereHas('activeDosenProgramStudiAssignments', function (Builder $assignmentQuery) use ($programStudiIds): void {
                $assignmentQuery->whereIn('program_studi_id', $programStudiIds);
            }),
            AppRole::Admin->value => $query->whereHas('adminProfile', function (Builder $profileQuery) use ($programStudiIds): void {
                $profileQuery->whereIn('program_studi_id', $programStudiIds);
            }),
            AppRole::Kaprodi->value => $query->whereHas('kaprodiAssignment', function (Builder $assignmentQuery) use ($programStudiIds): void {
                $assignmentQuery->whereIn('program_studi_id', $programStudiIds);
            }),
            default => $query,
        };
    }
}
