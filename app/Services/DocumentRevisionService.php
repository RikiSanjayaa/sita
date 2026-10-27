<?php

namespace App\Services;

use App\Models\MentorshipDocument;
use App\Models\ThesisProject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DocumentRevisionService
{
    public static function displayStatus(MentorshipDocument $document): string
    {
        if ($document->status !== 'needs_revision' || empty($document->revision_items)) {
            return $document->status;
        }
        foreach ($document->revision_items as $item) {
            if ($item['resolved_at'] === null) {
                return 'needs_revision';
            }
        }

        return 'revision_completed';
    }

    public function canResolve(User $user, MentorshipDocument $document): bool
    {
        if (! $user->hasRole('mahasiswa') || $document->student_user_id !== $user->id
            || $user->mahasiswaProfile?->is_active === false) {
            return false;
        }
        $project = ThesisProject::query()->latestPerStudent()->where('student_user_id', $user->id)->first();

        return $project !== null && $project->state === 'active' && ! $project->isArchived()
            && ! $project->isMentorshipSuspended();
    }

    /** @param array<string, mixed> $data */
    public function review(MentorshipDocument $document, array $data): void
    {
        DB::transaction(function () use ($document, $data): void {
            $locked = MentorshipDocument::query()->lockForUpdate()->findOrFail($document->id);
            $items = $locked->revision_items ?? [];
            $notes = $data['revision_notes'] ?? null;
            if ($data['status'] === 'needs_revision') {
                $items = [];
                $requested = $data['revision_items'] ?? [];
                if ($requested === []) {
                    $previous = $locked->revision_items ?? [];
                    $requested = [['id' => count($previous) === 1 ? $previous[0]['id'] : null, 'description' => $notes]];
                }
                $previousById = array_column($locked->revision_items ?? [], null, 'id');
                foreach ($requested as $item) {
                    $previous = $previousById[$item['id'] ?? ''] ?? null;
                    $description = trim($item['description']);
                    $items[] = [
                        'id' => $previous['id'] ?? (string) Str::uuid(),
                        'description' => $description,
                        'resolved_at' => $previous !== null && $previous['description'] === $description
                            ? $previous['resolved_at'] : null,
                    ];
                }
                $notes = implode("\n", array_column($items, 'description'));
            }
            $locked->forceFill([
                'status' => $data['status'], 'revision_notes' => $notes,
                'revision_items' => $items, 'reviewed_at' => now(),
            ])->save();
        });
        $document->refresh();
    }

    public function resolve(User $student, MentorshipDocument $document, string $revisionId, bool $resolved): void
    {
        abort_unless($this->canResolve($student, $document), 403);
        DB::transaction(function () use ($document, $revisionId, $resolved): void {
            $locked = MentorshipDocument::query()->lockForUpdate()->findOrFail($document->id);
            abort_unless($locked->status === 'needs_revision', 409, 'Dokumen tidak memiliki permintaan revisi aktif.');
            $items = $locked->revision_items ?? [];
            $index = array_search($revisionId, array_column($items, 'id'), true);
            abort_if($index === false, 404);
            $items[$index]['resolved_at'] = $resolved
                ? ($items[$index]['resolved_at'] ?? now()->toIso8601String()) : null;
            $locked->forceFill(['revision_items' => $items])->save();
        });
    }
}
