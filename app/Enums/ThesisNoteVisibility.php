<?php

namespace App\Enums;

enum ThesisNoteVisibility: string
{
    case Internal = 'internal';
    case Student = 'student';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Internal dosen',
            self::Student => 'Untuk mahasiswa',
        };
    }
}
