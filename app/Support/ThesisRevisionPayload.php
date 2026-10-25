<?php

namespace App\Support;

use App\Models\ThesisDocument;
use App\Models\ThesisRevision;
use App\Models\ThesisRevisionItem;
use App\Models\ThesisRevisionRound;
use App\Services\ThesisRevisionRoundService;

/** Shared revision labels and manuscript links for the log and decision forms. */
class ThesisRevisionPayload
{
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
