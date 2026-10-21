import { type Locator } from '@playwright/test';

export function toDateTimeLocalString(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

export function daysFromNow(days: number): Date {
    return new Date(Date.now() + days * 24 * 60 * 60 * 1000);
}

/**
 * Opens the shared DateRangePicker inside `scope` and picks one day. Day
 * buttons are named like "Kamis, 15 Oktober 2026"; the picker shows two months
 * starting at the current one, so pass a date in the current or next month and
 * not an overflow day of either.
 */
export async function pickCalendarDay(
    scope: Locator,
    date: Date,
): Promise<void> {
    const month = new Intl.DateTimeFormat('id-ID', { month: 'long' }).format(
        date,
    );

    await scope.getByRole('button', { name: 'Pilih rentang tanggal' }).click();
    await scope
        .getByRole('button', {
            name: new RegExp(
                `\\b${date.getDate()} ${month} ${date.getFullYear()}`,
                'i',
            ),
        })
        .click();
}
