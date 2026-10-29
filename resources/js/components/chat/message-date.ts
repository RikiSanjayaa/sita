const fullDateFormatter = new Intl.DateTimeFormat('id-ID', {
    timeZone: 'Asia/Makassar',
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

export function messageDay(value: string | Date): string {
    const instant =
        typeof value === 'string' ? Date.parse(value) : value.getTime();
    // WITA is UTC+08:00; ISO keys must not depend on a display locale.
    return new Date(instant + 8 * 60 * 60 * 1_000).toISOString().slice(0, 10);
}

export function messageDateLabel(day: string, today: string): string {
    if (day === today) return 'Hari ini';
    const midnight = new Date(`${today}T00:00:00+08:00`);
    if (day === messageDay(new Date(midnight.getTime() - 86_400_000))) {
        return 'Kemarin';
    }
    return fullDateFormatter.format(new Date(`${day}T00:00:00+08:00`));
}
