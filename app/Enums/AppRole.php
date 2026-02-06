<?php

namespace App\Enums;

enum AppRole: string
{
    case Mahasiswa = 'mahasiswa';
    case Dosen = 'dosen';
    case Admin = 'admin';
    case Penguji = 'penguji';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $role): string => $role->value,
            self::cases(),
        );
    }

    /**
     * @return array<int, string>
     */
    public static function uiValues(): array
    {
        return [
            self::Mahasiswa->value,
            self::Dosen->value,
            self::Admin->value,
        ];
    }

    public function dashboardRouteName(): string
    {
        return match ($this) {
            self::Mahasiswa => 'mahasiswa.dashboard',
            self::Dosen => 'dosen.dashboard',
            self::Admin => 'admin.dashboard',
            self::Penguji => 'mahasiswa.dashboard',
        };
    }
}
