<?php

/*
 * Guards the colour schemes in resources/css/app.css (issue #50): every scheme, in
 * light and dark, must meet the WCAG contrast contract and keep its primary hue away
 * from the status hues. The CSS is parsed as written, so a new scheme is covered as
 * soon as its blocks exist. DESIGN.md lists the pairs and how to add a scheme.
 */

const THEME_LIGHT_ROOT = ":root,\n[data-theme='ubg-blue']";

const THEME_TEXT_PAIRS = [
    ['foreground', 'background'],
    ['foreground', 'card'],
    ['foreground', 'muted'],
    ['card-foreground', 'card'],
    ['popover-foreground', 'popover'],
    ['secondary-foreground', 'secondary'],
    ['muted-foreground', 'background'],
    ['muted-foreground', 'card'],
    ['muted-foreground', 'muted'],
    ['primary', 'background'],
    ['primary', 'card'],
    ['primary-foreground', 'primary'],
    ['primary-muted-foreground', 'primary-muted'],
    ['accent-foreground', 'accent'],
    ['sidebar-foreground', 'sidebar'],
    ['muted-foreground', 'sidebar'],
    ['sidebar-primary', 'sidebar'],
    ['sidebar-primary-foreground', 'sidebar-primary'],
    ['sidebar-accent-foreground', 'sidebar-accent'],
];

const THEME_UI_PAIRS = [
    ['ring', 'background'],
    ['ring', 'card'],
    ['input', 'background'],
    ['input', 'card'],
    ['sidebar-ring', 'sidebar'],
];

const THEME_STATUS_TOKENS = ['success', 'warning', 'danger', 'info'];

function themeCss(): string
{
    return file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');
}

/** @return array<string, string> custom property => value for the block with this exact selector */
function themeBlock(string $selector): array
{
    $pattern = '/^'.preg_quote($selector, '/').' \{\n(.*?)^\}/ms';

    expect(preg_match($pattern, themeCss(), $match))->toBe(1, "Missing CSS block `{$selector}`.");

    preg_match_all('/^\s*--([a-z0-9-]+):\s*(.+?);$/m', $match[1], $declarations, PREG_SET_ORDER);

    $tokens = [];
    foreach ($declarations as [, $name, $value]) {
        $tokens[$name] = $value;
    }

    return $tokens;
}

/** @return list<string> */
function themeIdsInCss(): array
{
    preg_match_all("/^\[data-theme='([a-z-]+)'\] \{$/m", themeCss(), $matches);

    return array_values(array_unique(array_merge(['ubg-blue'], $matches[1])));
}

/** @return array{0: string, 1: array<string, string>} */
function themeTokens(string $id, string $mode): array
{
    if ($id === 'ubg-blue') {
        return $mode === 'light'
            ? [':root', themeBlock(THEME_LIGHT_ROOT)]
            : ['.dark', themeBlock('.dark')];
    }

    $selector = $mode === 'light' ? "[data-theme='{$id}']" : ".dark[data-theme='{$id}']";

    return [$selector, themeBlock($selector)];
}

/** @return array{0: float, 1: float, 2: float} */
function themeLinearRgb(string $hex): array
{
    expect($hex)->toMatch('/^#[0-9a-f]{6}$/i', "Scheme colours must be six-digit hex, got `{$hex}`.");

    return array_map(
        static function (string $pair): float {
            $channel = hexdec($pair) / 255;

            return $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
        },
        str_split(ltrim($hex, '#'), 2),
    );
}

function themeContrast(string $first, string $second): float
{
    $luminance = static function (string $hex): float {
        [$r, $g, $b] = themeLinearRgb($hex);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    };

    $lighter = max($luminance($first), $luminance($second));
    $darker = min($luminance($first), $luminance($second));

    return ($lighter + 0.05) / ($darker + 0.05);
}

/** @return array{0: float, 1: float} OKLCH hue in degrees and chroma */
function themeHueChroma(string $hex): array
{
    [$r, $g, $b] = themeLinearRgb($hex);

    $l = (0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b) ** (1 / 3);
    $m = (0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b) ** (1 / 3);
    $s = (0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b) ** (1 / 3);

    $a = 1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s;
    $bb = 0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s;

    return [fmod(rad2deg(atan2($bb, $a)) + 360, 360), hypot($a, $bb)];
}

/** @return array<string, array{0: string, 1: string}> */
function themeModes(bool $withDefault = true): array
{
    $cases = [];

    foreach (themeIdsInCss() as $id) {
        if (! $withDefault && $id === 'ubg-blue') {
            continue;
        }

        foreach (['light', 'dark'] as $mode) {
            $cases["{$id} {$mode}"] = [$id, $mode];
        }
    }

    return $cases;
}

it('meets 4.5:1 for every text pair', function (string $id, string $mode): void {
    [$selector, $tokens] = themeTokens($id, $mode);

    foreach (THEME_TEXT_PAIRS as [$foreground, $background]) {
        $ratio = themeContrast($tokens[$foreground], $tokens[$background]);

        expect($ratio)->toBeGreaterThanOrEqual(
            4.5,
            sprintf('%s: --%s on --%s is %.2f:1, needs 4.5:1.', $selector, $foreground, $background, $ratio),
        );
    }
})->with(themeModes());

it('meets 3:1 for ring and input borders', function (string $id, string $mode): void {
    [$selector, $tokens] = themeTokens($id, $mode);

    foreach (THEME_UI_PAIRS as [$foreground, $background]) {
        $ratio = themeContrast($tokens[$foreground], $tokens[$background]);

        expect($ratio)->toBeGreaterThanOrEqual(
            3.0,
            sprintf('%s: --%s on --%s is %.2f:1, needs 3:1.', $selector, $foreground, $background, $ratio),
        );
    }
})->with(themeModes());

it('keeps every non-blue primary away from the status hues', function (string $id, string $mode): void {
    [$selector, $tokens] = themeTokens($id, $mode);

    $statusHues = [];
    foreach (['light' => THEME_LIGHT_ROOT, 'dark' => '.dark'] as $statusMode => $statusSelector) {
        $statusBlock = themeBlock($statusSelector);

        foreach (THEME_STATUS_TOKENS as $status) {
            expect($statusBlock[$status])->toMatch('/^oklch\([\d.]+ [\d.]+ ([\d.]+)\)$/');
            preg_match('/^oklch\([\d.]+ [\d.]+ ([\d.]+)\)$/', $statusBlock[$status], $hue);
            $statusHues["{$status} {$statusMode}"] = (float) $hue[1];
        }
    }

    foreach (['primary', 'primary-muted-foreground'] as $token) {
        [$hue, $chroma] = themeHueChroma($tokens[$token]);

        if ($chroma < 0.02) {
            continue; // Nearly neutral: cannot be mistaken for a status colour.
        }

        foreach ($statusHues as $status => $statusHue) {
            $distance = abs($hue - $statusHue);
            $distance = min($distance, 360 - $distance);

            expect($distance)->toBeGreaterThanOrEqual(
                40.0,
                sprintf('%s: --%s hue %.0f is only %.0f degrees from %s (%.0f).', $selector, $token, $hue, $distance, $status, $statusHue),
            );
        }
    }
})->with(themeModes(withDefault: false));
