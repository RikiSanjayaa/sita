<?php

namespace App\Support\Filament;

use Illuminate\Support\Str;

class BadgeStyles
{
    /**
     * Raw backend status keys per domain mapped to semantic tones.
     * Keep in sync with resources/js/lib/status-styles.ts.
     *
     * @var array<string, array<string, string>>
     */
    public const DOMAIN_TONES = [
        'phase' => [
            'title_review' => 'neutral',
            'sempro' => 'info',
            'research' => 'warning',
            'sidang' => 'primary',
            'completed' => 'success',
            'cancelled' => 'danger',
        ],
        'state' => [
            'active' => 'success',
            'on_hold' => 'warning',
            'completed' => 'neutral',
            'cancelled' => 'danger',
        ],
        'defense' => [
            'draft' => 'neutral',
            'scheduled' => 'info',
            'awaiting_finalization' => 'warning',
            'completed' => 'success',
            'cancelled' => 'danger',
        ],
        'result' => [
            'pending' => 'warning',
            'pass' => 'success',
            'pass_with_revision' => 'warning',
            'fail' => 'danger',
        ],
        'schedule' => [
            'pending' => 'warning',
            'approved' => 'success',
            'scheduled' => 'info',
            'rescheduled' => 'info',
            'rejected' => 'danger',
            'completed' => 'neutral',
            'cancelled' => 'neutral',
        ],
        'document' => [
            'draft' => 'neutral',
            'submitted' => 'info',
            'needs_revision' => 'warning',
            'revision_completed' => 'info',
            'approved' => 'success',
            'rejected' => 'danger',
            'active' => 'success',
        ],
        'revision' => [
            'open' => 'warning',
            'submitted' => 'info',
            'resolved' => 'success',
        ],
        // UI-only follow-up ticket vocabulary; the backend has no ticket/deadline status column.
        'ticket' => [
            'open' => 'warning',
            'submitted' => 'info',
            'overdue' => 'danger',
            'resolved' => 'success',
            'closed' => 'neutral',
        ],
        // Curriculum framework flag on mahasiswa profiles (OBE vs legacy).
        'curriculum' => [
            'obe' => 'primary',
            'non_obe' => 'neutral',
        ],
        'active' => [
            'active' => 'success',
            'inactive' => 'neutral',
            'ended' => 'neutral',
            'true' => 'success',
            'false' => 'neutral',
        ],
    ];

    /**
     * Semantic tone (neutral|primary|success|warning|danger|info) for a raw status key.
     * Unknown domains, unknown keys and null are neutral.
     */
    public static function statusTone(string $domain, ?string $status): string
    {
        return self::DOMAIN_TONES[$domain][$status ?? ''] ?? 'neutral';
    }

    /**
     * Filament color name for a raw status key in a domain.
     */
    public static function domainStatusColor(string $domain, ?string $status): string
    {
        $tone = self::statusTone($domain, $status);

        return $tone === 'neutral' ? 'gray' : $tone;
    }

    public static function programStudiColor(?string $name): string
    {
        if (blank($name)) {
            return 'gray';
        }

        $palette = ['primary', 'info', 'success', 'warning', 'danger', 'gray'];

        return $palette[abs(crc32(Str::lower($name))) % count($palette)];
    }

    public static function programStudiIcon(): string
    {
        return 'heroicon-m-academic-cap';
    }

    public static function roleLabel(?string $role): string
    {
        return str($role)->replace('_', ' ')->headline()->toString();
    }

    public static function roleColor(?string $role): string
    {
        return match ($role) {
            'mahasiswa' => 'info',
            'dosen' => 'success',
            'kaprodi' => 'primary',
            'admin' => 'warning',
            'super_admin' => 'primary',
            'penguji' => 'danger',
            default => 'gray',
        };
    }

    public static function roleIcon(?string $role): string
    {
        return match ($role) {
            'mahasiswa' => 'heroicon-m-user',
            'dosen' => 'heroicon-m-user-group',
            'kaprodi' => 'heroicon-m-academic-cap',
            'admin' => 'heroicon-m-shield-check',
            'super_admin' => 'heroicon-m-star',
            'penguji' => 'heroicon-m-check-badge',
            default => 'heroicon-m-tag',
        };
    }

    public static function phaseColor(?string $phase): string
    {
        return self::domainStatusColor('phase', $phase);
    }

    public static function phaseIcon(?string $phase): string
    {
        return match ($phase) {
            'title_review' => 'heroicon-m-document-text',
            'sempro' => 'heroicon-m-chat-bubble-left-right',
            'research' => 'heroicon-m-beaker',
            'sidang' => 'heroicon-m-building-library',
            'completed' => 'heroicon-m-check-circle',
            'cancelled' => 'heroicon-m-x-circle',
            default => 'heroicon-m-tag',
        };
    }

    public static function stateColor(?string $state): string
    {
        return self::domainStatusColor('state', $state);
    }

    public static function stateIcon(?string $state): string
    {
        return match ($state) {
            'active' => 'heroicon-m-bolt',
            'on_hold' => 'heroicon-m-pause-circle',
            'completed' => 'heroicon-m-check-badge',
            'cancelled' => 'heroicon-m-no-symbol',
            default => 'heroicon-m-tag',
        };
    }

    public static function activeStateLabel(?bool $state): string
    {
        return $state ? 'Aktif' : 'Nonaktif';
    }

    public static function activeStateColor(?bool $state): string
    {
        return $state ? 'success' : 'gray';
    }

    public static function activeStateIcon(?bool $state): string
    {
        return $state ? 'heroicon-m-check-circle' : 'heroicon-m-minus-circle';
    }

    public static function auditEventLabel(?string $eventType): string
    {
        return str($eventType)->replace('_', ' ')->headline()->toString();
    }

    public static function auditEventColor(?string $eventType): string
    {
        $event = Str::lower((string) $eventType);

        return match (true) {
            str_contains($event, 'login') => 'success',
            str_contains($event, 'logout') => 'gray',
            str_contains($event, 'password'), str_contains($event, 'auth'), str_contains($event, 'verify') => 'warning',
            str_contains($event, 'delete'), str_contains($event, 'remove'), str_contains($event, 'revoke') => 'danger',
            str_contains($event, 'create'), str_contains($event, 'register'), str_contains($event, 'import') => 'primary',
            default => 'info',
        };
    }

    public static function auditEventIcon(?string $eventType): string
    {
        $event = Str::lower((string) $eventType);

        return match (true) {
            str_contains($event, 'login') => 'heroicon-m-arrow-right-end-on-rectangle',
            str_contains($event, 'logout') => 'heroicon-m-arrow-left-start-on-rectangle',
            str_contains($event, 'password'), str_contains($event, 'auth'), str_contains($event, 'verify') => 'heroicon-m-lock-closed',
            str_contains($event, 'delete'), str_contains($event, 'remove'), str_contains($event, 'revoke') => 'heroicon-m-trash',
            str_contains($event, 'create'), str_contains($event, 'register'), str_contains($event, 'import') => 'heroicon-m-plus-circle',
            default => 'heroicon-m-shield-check',
        };
    }

    public static function thesisEventLabel(?string $eventType): string
    {
        return str($eventType)->replace('_', ' ')->headline()->toString();
    }

    public static function thesisEventColor(?string $eventType): string
    {
        $event = Str::lower((string) $eventType);

        return match (true) {
            str_contains($event, 'revision') => 'danger',
            str_contains($event, 'sempro') => 'info',
            str_contains($event, 'sidang') => 'primary',
            str_contains($event, 'supervisor') => 'warning',
            str_contains($event, 'title') => 'gray',
            str_contains($event, 'closed'), str_contains($event, 'completed'), str_contains($event, 'approved') => 'success',
            str_contains($event, 'created') => 'success',
            default => 'gray',
        };
    }

    public static function thesisEventIcon(?string $eventType): string
    {
        $event = Str::lower((string) $eventType);

        return match (true) {
            str_contains($event, 'revision') => 'heroicon-m-exclamation-circle',
            str_contains($event, 'sempro') => 'heroicon-m-chat-bubble-left-right',
            str_contains($event, 'sidang') => 'heroicon-m-building-library',
            str_contains($event, 'supervisor') => 'heroicon-m-user-group',
            str_contains($event, 'title') => 'heroicon-m-document-text',
            str_contains($event, 'closed'), str_contains($event, 'completed'), str_contains($event, 'approved') => 'heroicon-m-check-badge',
            str_contains($event, 'created') => 'heroicon-m-sparkles',
            default => 'heroicon-m-bolt',
        };
    }
}
