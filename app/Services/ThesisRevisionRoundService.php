<?php

namespace App\Services;

use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisDocument;
use App\Models\ThesisRevision;
use App\Models\ThesisRevisionItem;
use App\Models\ThesisRevisionRound;
use Illuminate\Support\Collection;
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
     * The round the revision is currently in (the highest round number).
     */
    public function currentRound(ThesisRevision $revision): ?ThesisRevisionRound
    {
        return $revision->rounds()->reorder()->orderByDesc('round_no')->first();
    }

    /**
     * Items the student still has to answer in the open round.
     *
     * @return Collection<int, ThesisRevisionItem>
     */
    public function itemsAwaitingStudent(ThesisRevision $revision): Collection
    {
        return $revision->items()
            ->whereIn('status', [ThesisRevisionItem::STATUS_OPEN, ThesisRevisionItem::STATUS_REOPENED])
            ->get();
    }

    /**
     * Items the student answered and the examiner has yet to verify.
     *
     * @return Collection<int, ThesisRevisionItem>
     */
    public function itemsAwaitingReview(ThesisRevision $revision): Collection
    {
        return $revision->items()
            ->where('status', ThesisRevisionItem::STATUS_ADDRESSED)
            ->get();
    }

    /**
     * Record the student's answer for every open/reopened item, attach the
     * corrected manuscript to the round and move the round to awaiting review.
     *
     * @param  array<int, array{response: string, location?: ?string}>  $responses  keyed by item id
     */
    public function submitResponses(
        ThesisRevision $revision,
        ThesisRevisionRound $round,
        array $responses,
        ThesisDocument $manuscript,
    ): void {
        DB::transaction(function () use ($revision, $round, $responses, $manuscript): void {
            foreach ($this->itemsAwaitingStudent($revision) as $item) {
                $answer = $responses[$item->getKey()];

                $item->forceFill([
                    'status' => ThesisRevisionItem::STATUS_ADDRESSED,
                    'student_response' => trim($answer['response']),
                    'student_response_location' => filled($answer['location'] ?? null) ? trim($answer['location']) : null,
                ])->save();
            }

            $round->forceFill(['student_document_id' => $manuscript->getKey()])->save();

            $this->markSubmitted($revision, now());
        });
    }

    /**
     * Apply the examiner's verdict on the answered items of the awaiting-review
     * round. Accepted items stay accepted; rejected ones are reopened and brand
     * new ones are added, both carried into the next round. With nothing left
     * open the revision is closed instead.
     *
     * @param  array<int, array{decision: string, comment?: ?string}>  $verdicts  keyed by item id
     * @param  array<int, array{location?: ?string, description: string, priority?: ?string}>  $newItems
     * @return ThesisRevisionRound|null the next round, or null when the revision is fully accepted
     */
    public function applyVerdicts(
        ThesisRevision $revision,
        ThesisRevisionRound $round,
        array $verdicts,
        array $newItems,
        int $verifiedByUserId,
    ): ?ThesisRevisionRound {
        return DB::transaction(function () use ($revision, $round, $verdicts, $newItems, $verifiedByUserId): ?ThesisRevisionRound {
            $now = now();
            $reopened = false;

            foreach ($this->itemsAwaitingReview($revision) as $item) {
                $verdict = $verdicts[$item->getKey()];
                $accepted = $verdict['decision'] === ThesisRevisionItem::STATUS_ACCEPTED;

                $item->forceFill([
                    'status' => $accepted ? ThesisRevisionItem::STATUS_ACCEPTED : ThesisRevisionItem::STATUS_REOPENED,
                    'verification_comment' => filled($verdict['comment'] ?? null) ? trim($verdict['comment']) : null,
                    'verified_at' => $now,
                    'verified_by_user_id' => $verifiedByUserId,
                ])->save();

                $reopened = $reopened || ! $accepted;
            }

            $newItems = collect($newItems)
                ->filter(fn (array $item): bool => filled($item['description'] ?? null))
                ->values();

            if (! $reopened && $newItems->isEmpty()) {
                $this->close($revision, $verifiedByUserId);

                return null;
            }

            $round->forceFill([
                'status' => ThesisRevisionRound::STATUS_CLOSED,
                'reviewed_at' => $now,
                'item_snapshot' => $this->snapshotItems($revision, $round->round_no),
            ])->save();

            $next = $revision->rounds()->create([
                'round_no' => $round->round_no + 1,
                'opened_at' => $now,
                'status' => ThesisRevisionRound::STATUS_AWAITING_STUDENT,
            ]);

            $sortOrder = (int) $revision->items()->max('sort_order');

            foreach ($newItems as $item) {
                $revision->items()->create([
                    'round_opened' => $next->round_no,
                    'location' => filled($item['location'] ?? null) ? trim($item['location']) : null,
                    'description' => trim($item['description']),
                    'priority' => in_array($item['priority'] ?? null, ThesisRevisionItem::PRIORITIES, true)
                        ? $item['priority']
                        : null,
                    'status' => ThesisRevisionItem::STATUS_OPEN,
                    'sort_order' => ++$sortOrder,
                ]);
            }

            $this->syncStatus($revision);

            return $next;
        });
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

            foreach ($revision->rounds()->where('status', '!=', ThesisRevisionRound::STATUS_CLOSED)->get() as $round) {
                $round->forceFill([
                    'status' => ThesisRevisionRound::STATUS_CLOSED,
                    'reviewed_at' => $now,
                    'item_snapshot' => $this->snapshotItems($revision, $round->round_no),
                ])->save();
            }

            $this->syncStatus($revision);
        });
    }

    /** @return array<int, array<string, mixed>> */
    private function snapshotItems(ThesisRevision $revision, int $roundNo): array
    {
        return $revision->items()->where('round_opened', '<=', $roundNo)
            ->with('verifiedBy')
            ->get([
                'id', 'location', 'description', 'priority', 'status', 'round_opened',
                'student_response', 'student_response_location', 'verification_comment',
                'verified_at', 'verified_by_user_id',
            ])
            ->map(fn (ThesisRevisionItem $item): array => [
                ...$item->attributesToArray(),
                'verified_by_name' => $item->verifiedBy?->name,
            ])->all();
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
