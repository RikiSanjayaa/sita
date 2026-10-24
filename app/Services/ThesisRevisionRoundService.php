<?php

namespace App\Services;

use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisDocument;
use App\Models\ThesisRevision;
use App\Models\ThesisRevisionItem;
use App\Models\ThesisRevisionRound;
use Illuminate\Support\Facades\DB;

/**
 * Owns the round/item lifecycle of a ThesisRevision and keeps the aggregate
 * `ThesisRevision.status` (open / submitted / resolved) in step with it, so the
 * existing readers of that column stay correct.
 */
class ThesisRevisionRoundService
{
    public const ANNOTATION_KIND = 'revision_annotation';

    /**
     * Replace whatever rounds/items a revision has with a fresh round 1.
     * Used when an exam is (re)finalised. Without items the revision `notes`
     * become the single item, so every revision always has at least one.
     *
     * @param  array<int, array{location?: ?string, description?: ?string, priority?: ?string}>  $items
     */
    public function openInitialRound(ThesisRevision $revision, array $items): ThesisRevisionRound
    {
        return DB::transaction(function () use ($revision, $items): ThesisRevisionRound {
            $revision->items()->delete();
            $revision->rounds()->delete();

            $round = $revision->rounds()->create([
                'round_no' => 1,
                'opened_at' => now(),
                'status' => ThesisRevisionRound::STATUS_AWAITING_STUDENT,
            ]);

            $rows = collect($items)
                ->filter(fn (array $item): bool => filled($item['description'] ?? null))
                ->values();

            if ($rows->isEmpty()) {
                $rows = collect([['description' => $revision->notes]]);
            }

            foreach ($rows as $index => $item) {
                $revision->items()->create([
                    'round_opened' => $round->round_no,
                    'location' => filled($item['location'] ?? null) ? $item['location'] : null,
                    'description' => $item['description'],
                    'priority' => in_array($item['priority'] ?? null, ThesisRevisionItem::PRIORITIES, true)
                        ? $item['priority']
                        : null,
                    'status' => ThesisRevisionItem::STATUS_OPEN,
                    'sort_order' => $index,
                ]);
            }

            $this->syncStatus($revision);

            return $round;
        });
    }

    /**
     * Attach the annotated PDF an examiner uploaded with their decision to the
     * revision, as the version for the given round.
     */
    public function attachAnnotation(ThesisRevision $revision, ThesisDefenseExaminer $examiner, int $roundNo = 1): void
    {
        ThesisDocument::query()
            ->where('defense_id', $examiner->defense_id)
            ->where('kind', self::ANNOTATION_KIND)
            ->where('uploaded_by_user_id', $examiner->lecturer_user_id)
            ->whereNull('revision_id')
            ->update([
                'revision_id' => $revision->getKey(),
                'version_no' => $roundNo,
            ]);
    }

    /**
     * The student sent a corrected manuscript: the open round now waits for the
     * examiner.
     */
    public function markSubmitted(ThesisRevision $revision, \DateTimeInterface $submittedAt): void
    {
        $revision->rounds()
            ->where('status', ThesisRevisionRound::STATUS_AWAITING_STUDENT)
            ->update([
                'status' => ThesisRevisionRound::STATUS_AWAITING_REVIEW,
                'student_submitted_at' => $submittedAt,
                'updated_at' => now(),
            ]);

        $this->syncStatus($revision);
    }

    /**
     * The revision is signed off (by the examiner or an admin): every round
     * closes and every item is accepted.
     */
    public function close(ThesisRevision $revision, int $verifiedByUserId): void
    {
        DB::transaction(function () use ($revision, $verifiedByUserId): void {
            $now = now();

            $revision->items()
                ->where('status', '!=', ThesisRevisionItem::STATUS_ACCEPTED)
                ->update([
                    'status' => ThesisRevisionItem::STATUS_ACCEPTED,
                    'verified_at' => $now,
                    'verified_by_user_id' => $verifiedByUserId,
                    'updated_at' => $now,
                ]);

            $revision->rounds()
                ->where('status', '!=', ThesisRevisionRound::STATUS_CLOSED)
                ->update([
                    'status' => ThesisRevisionRound::STATUS_CLOSED,
                    'reviewed_at' => $now,
                    'updated_at' => $now,
                ]);

            $this->syncStatus($revision);
        });
    }

    /**
     * Derive `ThesisRevision.status` from the latest round. Revisions without
     * rounds keep whatever status they have.
     */
    public function syncStatus(ThesisRevision $revision): void
    {
        $round = $revision->rounds()->reorder()->orderByDesc('round_no')->first();

        if (! $round instanceof ThesisRevisionRound) {
            return;
        }

        $status = match ($round->status) {
            ThesisRevisionRound::STATUS_CLOSED => 'resolved',
            ThesisRevisionRound::STATUS_AWAITING_REVIEW => 'submitted',
            default => 'open',
        };

        if ($revision->status !== $status) {
            $revision->forceFill(['status' => $status])->save();
        }
    }
}
