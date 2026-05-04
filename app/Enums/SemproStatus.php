<?php

namespace App\Enums;

enum SemproStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case RevisionOpen = 'revision_open';
    case Approved = 'approved';

    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases(),
        );
    }
}
