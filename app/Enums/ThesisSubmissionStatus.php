<?php

namespace App\Enums;

enum ThesisSubmissionStatus: string
{
    case MenungguPersetujuan = 'menunggu_persetujuan';
    case SemproDijadwalkan = 'sempro_dijadwalkan';
    case RevisiSempro = 'revisi_sempro';
    case SemproSelesai = 'sempro_selesai';
    case PembimbingDitetapkan = 'pembimbing_ditetapkan';

    public static function values(): array
    {
        return array_map(
            static fn(self $status): string => $status->value,
            self::cases(),
        );
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::PembimbingDitetapkan,
        ], true);
    }
}
