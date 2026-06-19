<?php

namespace App\Enums;

enum DegreeLevel: string
{
    case D3 = 'd3';
    case S1 = 's1';
    case S2 = 's2';

    public function label(): string
    {
        return strtoupper($this->value);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn(self $level): string => $level->value,
            self::cases(),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(static fn(self $level): array => [$level->value => $level->label()])
            ->all();
    }
}
