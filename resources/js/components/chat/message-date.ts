const dayFormatter = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Makassar',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
});
const fullDateFormatter = new Intl.DateTimeFormat('id-ID', {
    timeZone: 'Asia/Makassar',
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

export function messageDay(value: string | Date): string {
    return dayFormatter.format(
        typeof value === 'string' ? new Date(value) : value,
    );
}

export function messageDateLabel(day: string, today: string): string {
    if (day === today) return 'Hari ini';
    const midnight = new Date(`${today}T00:00:00+08:00`);
    if (day === messageDay(new Date(midnight.getTime() - 86_400_000))) {
        return 'Kemarin';
    }
    return fullDateFormatter.format(new Date(`${day}T00:00:00+08:00`));
}
