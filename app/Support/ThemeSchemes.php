<?php

namespace App\Support;

/**
 * Colour scheme ids accepted from the `sita_theme_preset` cookie.
 *
 * Each id has a `[data-theme]` block in resources/css/app.css and an entry in
 * resources/js/hooks/use-theme-scheme.ts.
 */
final class ThemeSchemes
{
    public const COOKIE = 'sita_theme_preset';

    public const DEFAULT = 'ubg-blue';

    /** @var list<string> */
    public const IDS = ['ubg-blue', 'indigo', 'graphite', 'aubergine', 'plum', 'olive', 'sepia'];

    public static function resolve(mixed $value): string
    {
        return is_string($value) && in_array($value, self::IDS, true) ? $value : self::DEFAULT;
    }
}
