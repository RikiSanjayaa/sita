/**
 * Two-letter initials for avatar fallbacks. Academic titles are skipped so
 * "Dr. Budi Santoso, M.Kom." gives "BS" instead of "DM": postnominal degrees
 * follow the first comma, and prenominal titles end with a period.
 */
export function getInitials(fullName: string): string {
    const [name = ''] = fullName.split(',');
    const words = name.trim().split(/\s+/).filter(Boolean);
    const nameWords = words.filter((word) => !word.endsWith('.'));
    const parts = nameWords.length > 0 ? nameWords : words;

    if (parts.length === 0) {
        return '';
    }

    const first = parts[0].charAt(0);
    const last = parts.length > 1 ? parts[parts.length - 1].charAt(0) : '';

    return `${first}${last}`.toUpperCase();
}
