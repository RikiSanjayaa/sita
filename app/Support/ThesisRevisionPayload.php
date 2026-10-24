<?php

namespace App\Support;

use App\Models\ThesisDocument;
use App\Models\ThesisRevision;
use App\Models\ThesisRevisionItem;
use App\Models\ThesisRevisionRound;
use App\Services\ThesisRevisionRoundService;

/**
 * Inertia payload for the structured part of a ThesisRevision (items and the
 * examiner's annotated manuscripts). Expects `items` and `documents` loaded.
 */
class ThesisRevisionPayload
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function items(ThesisRevision $revision): array
    {
        return $revision->items
            ->map(fn (ThesisRevisionItem $item): array => [
                'id' => $item->id,
                'location' => $item->location,
                'description' => $item->description,
                'priority' => $item->priority,
                'priorityLabel' => self::priorityLabel($item->priority),
                'status' => $item->status,
                'statusLabel' => self::itemStatusLabel($item->status),
                'roundOpened' => $item->round_opened,
                'studentResponse' => $item->student_response,
                'studentResponseLocation' => $item->student_response_location,
                'verificationComment' => $item->verification_comment,
            ])
            ->values()
            ->all();
    }

    /**
     * Round history of a revision, oldest first. Expects `rounds.studentDocument` loaded.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function rounds(ThesisRevision $revision): array
    {
        return $revision->rounds
            ->map(fn (ThesisRevisionRound $round): array => [
                'roundNo' => $round->round_no,
                'status' => $round->status,
                'statusLabel' => self::roundStatusLabel($round->status),
                'openedAt' => $round->opened_at?->locale('id')->translatedFormat('d F Y, H:i'),
                'submittedAt' => $round->student_submitted_at?->locale('id')->translatedFormat('d F Y, H:i'),
                'reviewedAt' => $round->reviewed_at?->locale('id')->translatedFormat('d F Y, H:i'),
                'manuscript' => $round->studentDocument instanceof ThesisDocument
                    ? self::document($round->studentDocument)
                    : null,
            ])
            ->values()
            ->all();
    }

    public static function currentRoundStatus(ThesisRevision $revision): ?string
    {
        return $revision->rounds->sortByDesc('round_no')->first()?->status;
    }

    public static function roundStatusLabel(string $status): string
    {
        return match ($status) {
            ThesisRevisionRound::STATUS_AWAITING_STUDENT => 'Menunggu perbaikan mahasiswa',
            ThesisRevisionRound::STATUS_AWAITING_REVIEW => 'Menunggu verifikasi penguji',
            ThesisRevisionRound::STATUS_CLOSED => 'Selesai',
            default => ucwords(str_replace('_', ' ', $status)),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function annotations(ThesisRevision $revision): array
    {
        return $revision->documents
            ->where('kind', ThesisRevisionRoundService::ANNOTATION_KIND)
            ->sortBy('version_no')
            ->map(fn (ThesisDocument $document): array => self::document($document))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function document(ThesisDocument $document): array
    {
        return [
            'id' => $document->id,
            'roundNo' => $document->version_no,
            'fileName' => $document->file_name,
            'viewUrl' => route('files.thesis-documents.download', ['document' => $document->id, 'inline' => 1]),
            'downloadUrl' => route('files.thesis-documents.download', ['document' => $document->id]),
        ];
    }

    public static function priorityLabel(?string $priority): ?string
    {
        return match ($priority) {
            'major' => 'Mayor',
            'minor' => 'Minor',
            default => null,
        };
    }

    public static function itemStatusLabel(string $status): string
    {
        return match ($status) {
            ThesisRevisionItem::STATUS_OPEN => 'Belum dikerjakan',
            ThesisRevisionItem::STATUS_ADDRESSED => 'Sudah diperbaiki',
            ThesisRevisionItem::STATUS_ACCEPTED => 'Diterima',
            ThesisRevisionItem::STATUS_REOPENED => 'Dibuka kembali',
            default => ucwords(str_replace('_', ' ', $status)),
        };
    }
}
