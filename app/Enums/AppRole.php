<?php

namespace App\Enums;

enum AppRole: string
{
    case Mahasiswa = 'mahasiswa';
    case Dosen = 'dosen';
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';
    case Penguji = 'penguji';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn(self $role): string => $role->value,
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
            self::SuperAdmin->value,
        ];
    }

    /**
     * Roles that should be directed to the Filament admin panel.
     *
     * @return array<int, self>
     */
    public static function adminRoles(): array
    {
        return [self::Admin, self::SuperAdmin];
    }

    public function isAdminRole(): bool
    {
        return in_array($this, self::adminRoles(), true);
    }

    public function dashboardRouteName(): string
    {
        return match ($this) {
            self::Mahasiswa => 'mahasiswa.dashboard',
            self::Dosen => 'dosen.dashboard',
            self::Admin, self::SuperAdmin => 'filament.admin.pages.dashboard',
            self::Penguji => 'mahasiswa.dashboard',
        };
    }
}
