import {
    expect,
    type Locator,
    type Page,
    type TestInfo,
} from '@playwright/test';

import { runArtisan, seedPlaywrightScenarios } from './db';

export function seedDocumentDetailScenario(): void {
    seedPlaywrightScenarios();
    // The shared seeder rebuilds projects, but this student's mentorship rows
    // are student-scoped and survive it. Reset only this dedicated demo user.
    runArtisan([
        'tinker',
        `--execute=$student = App\\Models\\User::query()->where('email', 'e2e-pembimbing@sita.test')->firstOrFail(); App\\Models\\MentorshipChatThread::query()->where('student_user_id', $student->id)->delete(); App\\Models\\MentorshipDocument::query()->where('student_user_id', $student->id)->get()->each(function ($document) { if ($document->storage_path) { Illuminate\\Support\\Facades\\Storage::disk($document->storage_disk)->delete($document->storage_path); } $document->delete(); });`,
    ]);
}

export async function openDocumentDetail(
    page: Page,
    row: Locator,
    prefix: string,
): Promise<string> {
    const link = row.locator(`a[href*="${prefix}/"]`).first();
    const href = await link.getAttribute('href');
    expect(new URL(href!, page.url()).pathname).toMatch(
        new RegExp(`^${prefix}/\\d+$`),
    );
    await link.click();
    await expect(page).toHaveURL(new RegExp(`${prefix}/\\d+$`));
    await expect(
        page.getByRole('heading', { name: 'Detail dokumen', exact: true }),
    ).toBeVisible();
    return href!;
}

export async function submitDocumentReview(
    page: Page,
    status: 'needs_revision' | 'approved',
    notes: string,
): Promise<void> {
    await page.getByRole('textbox', { name: 'Catatan dosen' }).fill(notes);
    await Promise.all([
        page.waitForResponse(
            (response) =>
                response.request().method() === 'POST' &&
                /\/dosen\/dokumen-revisi\/\d+\/review$/.test(response.url()),
        ),
        page
            .getByRole('button', {
                name: status === 'approved' ? 'Setujui' : 'Minta revisi',
                exact: true,
            })
            .click(),
    ]);
    await expect(
        page.getByRole('textbox', { name: 'Catatan dosen' }),
    ).toHaveValue(notes);
    await expect(
        page
            .getByText(status === 'approved' ? 'Disetujui' : 'Perlu Revisi', {
                exact: true,
            })
            .first(),
    ).toBeVisible();
}

export async function attachDocumentStates(
    page: Page,
    testInfo: TestInfo,
    name: string,
): Promise<void> {
    const viewport = page.viewportSize();
    try {
        for (const width of [1280, 390]) {
            for (const theme of ['light', 'dark'] as const) {
                await page.setViewportSize({ width, height: 844 });
                await page.emulateMedia({
                    colorScheme: theme,
                });
                await page.evaluate((appearance) => {
                    localStorage.setItem('appearance', appearance);
                    document.documentElement.classList.toggle(
                        'dark',
                        appearance === 'dark',
                    );
                }, theme);
                await page.evaluate(() => document.fonts.ready);
                await page.evaluate(() => window.scrollTo(0, 0));
                // A visible sheet can still be sliding in. Finish natural finite
                // motion before recording it, including theme/viewport changes.
                await page.evaluate(async () => {
                    await Promise.all(
                        document
                            .getAnimations()
                            .filter(
                                (animation) =>
                                    animation.playState === 'running' &&
                                    animation.effect?.getTiming().iterations !==
                                        Infinity,
                            )
                            .map((animation) =>
                                animation.finished.catch(() => {}),
                            ),
                    );
                    await new Promise<void>((resolve) =>
                        requestAnimationFrame(() =>
                            requestAnimationFrame(() => resolve()),
                        ),
                    );
                });
                const sheet = page.locator(
                    '[data-slot="sheet-content"][data-state="open"]',
                );
                if (await sheet.count()) {
                    await expect
                        .poll(() =>
                            sheet.evaluate((element) => {
                                const box = element.getBoundingClientRect();
                                return (
                                    box.x >= -1 &&
                                    box.right <= innerWidth + 1 &&
                                    Math.abs(box.right - innerWidth) <= 1
                                );
                            }),
                        )
                        .toBe(true);
                }
                const screenshotPath = testInfo.outputPath(
                    `${name}-${width}-${theme}.png`,
                );
                await page.screenshot({
                    path: screenshotPath,
                    fullPage: (await page.getByRole('dialog').count()) === 0,
                    caret: 'hide',
                });
                await testInfo.attach(`${name}-${width}-${theme}`, {
                    path: screenshotPath,
                    contentType: 'image/png',
                });
                expect(
                    await page.evaluate(
                        () => document.documentElement.scrollWidth,
                    ),
                ).toBeLessThanOrEqual(width);
            }
        }
    } finally {
        if (viewport) await page.setViewportSize(viewport);
        await page.emulateMedia({ colorScheme: 'light' });
        await page.evaluate(() => {
            localStorage.setItem('appearance', 'light');
            document.documentElement.classList.remove('dark');
        });
    }
}
