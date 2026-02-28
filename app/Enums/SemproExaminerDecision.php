<?php

namespace App\Enums;

enum SemproExaminerDecision: string
{
    case Pending = 'pending';
    case NeedsRevision = 'needs_revision';
    case Approved = 'approved';

    public static function values(): array
    {
        return array_map(
            static fn (self $decision): string => $decision->value,
            self::cases(),
        );
    }
}
