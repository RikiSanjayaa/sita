<?php

namespace App\Support;

use App\Models\ThesisDefense;
use App\Models\ThesisProjectEvent;
use App\Models\ThesisRevision;
use App\Models\ThesisRevisionItem;
use App\Models\ThesisRevisionRound;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class DefenseRevisionLog
{
    public const RELATIONS = [
        'examiners.lecturer',
        'revisions.requestedBy',
        'revisions.resolvedBy',
        'revisions.items.verifiedBy',
        'revisions.documents',
        'revisions.rounds.studentDocument',
        'project.events.actor',
    ];

    /**
     * Closed rounds retain their recorded items. Legacy rounds without a snapshot
     * explicitly show latest values rather than inventing historical responses.
     * List callers eager-load RELATIONS to avoid a query per exam.
     *
     * @return array<string, mixed>
     */
    public static function forDefense(ThesisDefense $defense, bool $forStudent = false): array
    {
        $defense->loadMissing(self::RELATIONS);
        $revisions = $defense->revisions->sortBy(fn (ThesisRevision $revision): array => [$revision->created_at?->getTimestamp() ?? 0, $revision->id]);

        if ($forStudent && ! $defense->examinersVisibleToStudent()) {
            $visibleIds = $defense->examinersForStudent()->pluck('lecturer_user_id');
            $revisions = $revisions->whereIn('requested_by_user_id', $visibleIds);
        }

        return [
            'examiners' => $revisions->groupBy('requested_by_user_id')
                ->map(fn (Collection $entries): array => [
                    'id' => $entries->first()->requested_by_user_id,
                    'name' => $entries->first()->requestedBy?->name ?? 'Penguji tidak tersedia',
                    'revisions' => $entries->map(fn (ThesisRevision $revision): array => self::revision($revision))->values()->all(),
                ])->values()->all(),
            'overrides' => $forStudent && ! $defense->examinersVisibleToStudent()
                ? []
                : self::overrides($defense),
        ];
    }

    /** @return array<string, mixed> */
    private static function revision(ThesisRevision $revision): array
    {
        $items = $revision->items->map(fn (ThesisRevisionItem $item): array => self::item([
            ...$item->attributesToArray(),
            'verified_by_name' => $item->verifiedBy?->name,
        ]));
        $annotations = collect(ThesisRevisionPayload::annotations($revision));
        $rounds = $revision->rounds->sortBy('round_no');

        return [
            'id' => $revision->id,
            'requestedByUserId' => $revision->requested_by_user_id,
            'status' => $revision->status,
            'statusLabel' => match ($revision->status) {
                'open' => 'Terbuka',
                'submitted' => 'Dikirim',
                'resolved' => 'Selesai',
                default => ucwords(str_replace('_', ' ', $revision->status)),
            },
            'notes' => $revision->notes,
            'requestedAt' => self::date($revision->created_at),
            'dueAt' => self::date($revision->due_at),
            'dueAtIso' => $revision->due_at?->toIso8601String(),
            'submittedAt' => self::date($revision->submitted_at),
            'resolvedAt' => self::date($revision->resolved_at),
            'resolvedBy' => $revision->resolvedBy?->name,
            'resolutionNotes' => $revision->resolution_notes,
            'items' => $items->values()->all(),
            'rounds' => $rounds->map(fn (ThesisRevisionRound $round): array => [
                'roundNo' => $round->round_no,
                'status' => $round->status,
                'statusLabel' => ThesisRevisionPayload::roundStatusLabel($round->status),
                'openedAt' => self::date($round->opened_at),
                'submittedAt' => self::date($round->student_submitted_at),
                'reviewedAt' => self::date($round->reviewed_at),
                'itemState' => $round->item_snapshot !== null ? 'recorded' : ($round->status === ThesisRevisionRound::STATUS_CLOSED ? 'latest' : 'current'),
                'itemStateLabel' => $round->item_snapshot !== null
                    ? 'Rekaman saat putaran ditutup.'
                    : ($round->status === ThesisRevisionRound::STATUS_CLOSED
                        ? 'Data lama: hanya status dan tanggapan terbaru yang tersedia.'
                        : 'Butir putaran aktif.'),
                'items' => $round->item_snapshot !== null
                    ? array_map(self::item(...), $round->item_snapshot)
                    : ($round->status === ThesisRevisionRound::STATUS_CLOSED
                        ? $items->where('roundOpened', $round->round_no)->values()->all()
                        : $items->where('roundOpened', '<=', $round->round_no)->values()->all()),
                'annotations' => $annotations->where('roundNo', $round->round_no)->values()->all(),
                'manuscript' => $round->studentDocument === null ? null : ThesisRevisionPayload::document($round->studentDocument),
            ])->values()->all(),
            'unassignedItems' => $items->whereNotIn('roundOpened', $rounds->pluck('round_no'))->values()->all(),
            'unassignedAnnotations' => $annotations->whereNotIn('roundNo', $rounds->pluck('round_no'))->values()->all(),
        ];
    }

    /** @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private static function item(array $item): array
    {
        return [
            'id' => $item['id'],
            'location' => $item['location'],
            'description' => $item['description'],
            'priority' => $item['priority'],
            'priorityLabel' => ThesisRevisionPayload::priorityLabel($item['priority']),
            'status' => $item['status'],
            'statusLabel' => ThesisRevisionPayload::itemStatusLabel($item['status']),
            'roundOpened' => $item['round_opened'],
            'studentResponse' => $item['student_response'],
            'studentResponseLocation' => $item['student_response_location'],
            'verificationComment' => $item['verification_comment'],
            'verifiedAt' => self::date($item['verified_at']),
            'verifiedBy' => $item['verified_by_name'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private static function overrides(ThesisDefense $defense): array
    {
        return $defense->project->events
            ->filter(function (ThesisProjectEvent $event) use ($defense): bool {
                if ($event->event_type !== 'revision_resolved') {
                    return false;
                }

                if (isset($event->payload['defense_id'])) {
                    return (int) $event->payload['defense_id'] === $defense->id
                        && ($event->payload['source'] ?? null) === 'admin_override';
                }

                // Older events have no defense id. Match the recorded resolver,
                // timestamp and resolution text rather than parsing stage labels.
                return $defense->revisions->contains(fn (ThesisRevision $revision): bool => $revision->resolved_by_user_id !== $revision->requested_by_user_id
                    && $revision->resolved_by_user_id === $event->actor_user_id
                    && $revision->resolved_at !== null
                    && $event->occurred_at !== null
                    && $revision->resolved_at->getTimestamp() === $event->occurred_at->getTimestamp()
                    && $revision->resolution_notes === $event->description
                );
            })
            ->sortBy(fn (ThesisProjectEvent $event): array => [$event->occurred_at?->getTimestamp() ?? 0, $event->id])
            ->map(fn (ThesisProjectEvent $event): array => [
                'id' => $event->id,
                'actor' => $event->actor?->name ?? 'Admin tidak tersedia',
                'description' => $event->description,
                'occurredAt' => self::date($event->occurred_at),
            ])->values()->all();
    }

    private static function date(CarbonInterface|string|null $value): ?string
    {
        return $value === null ? null : WitaDateTime::format(is_string($value) ? CarbonImmutable::parse($value) : $value);
    }
}
