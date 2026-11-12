/**
 * Keep `<meta name="theme-color">` (browser toolbar and the status bar of the
 * installed app) equal to `--background`, the colour the portal and public
 * headers use. Call it after the `dark` class or `data-theme` changes.
 *
 * app.blade.php renders exactly one such meta without `media`: Firefox uses the
 * first match and would otherwise follow the OS instead of the app appearance.
 * The manifest has no `theme_color` for the same reason (Firefox prefers it).
 */
export function syncThemeColor(): void {
    if (typeof document === 'undefined') return;

    const meta = document.querySelector<HTMLMetaElement>(
        'meta[name="theme-color"]',
    );
    const background = getComputedStyle(document.documentElement)
        .getPropertyValue('--background')
        .trim();

    if (meta !== null && background !== '' && meta.content !== background) {
        meta.content = background;
    }
}
