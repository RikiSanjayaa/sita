import { expect, test, type Locator, type Page } from '@playwright/test';

import {
    createPublicMotionTopics,
    type PublicMotionTopics,
} from '../support/public-motion';

// Guest-visible page; this spec owns its fixture topics and never touches
// shared seed rows or accounts.
test.use({ storageState: { cookies: [], origins: [] } });

let fixture: PublicMotionTopics;

test.beforeAll(() => {
    fixture = createPublicMotionTopics();
});

test.afterAll(() => {
    fixture?.cleanup();
});

const triggers = (page: Page): Locator =>
    page.getByRole('button', { name: 'Buka detail topik' });

// Detail content is exposed to assistive technology only while its row is
// open: a closing row is aria-hidden/inert, which role queries exclude.
const exposedDetails = (page: Page): Locator =>
    page.getByRole('cell', { name: /Judul Internasional/ });

/** Running opacity/transform transitions anywhere on the page. */
async function runningMotion(page: Page): Promise<number> {
    return page.evaluate(
        () =>
            document
                .getAnimations()
                .filter(
                    (animation) =>
                        animation instanceof CSSTransition &&
                        ['opacity', 'transform'].includes(
                            animation.transitionProperty,
                        ) &&
                        animation.playState === 'running',
                ).length,
    );
}

/** Resolves once every transition currently on the page has settled. */
async function settleMotion(page: Page): Promise<void> {
    await page.evaluate(async () => {
        await Promise.allSettled(
            document.getAnimations().map((animation) => animation.finished),
        );
    });
}

async function openTopics(page: Page, query = ''): Promise<void> {
    await page.goto(
        `/topik?search=${encodeURIComponent(fixture.prefix)}${query}`,
    );
    await expect(triggers(page).first()).toBeVisible();
}

type HoldWindow = Window & {
    __holdExits?: boolean;
    __holdInstalled?: boolean;
    __row?: Element | null;
};

/**
 * Browser harness only: pauses each detail transition as it is created, so a
 * test can inspect or reverse a real exit deterministically. Shipped timing
 * is untouched.
 */
async function holdExits(page: Page): Promise<void> {
    await page.evaluate(() => {
        const w = window as HoldWindow;
        w.__holdExits = true;
        if (w.__holdInstalled) {
            return;
        }
        w.__holdInstalled = true;
        document.addEventListener(
            'transitionrun',
            (event) => {
                const target = event.target as Element;
                if (
                    w.__holdExits &&
                    target.closest('[id^="public-topic-details-"]')
                ) {
                    target.getAnimations().forEach((a) => a.pause());
                }
            },
            true,
        );
    });
}

/** Resumes every held transition and stops holding new ones. */
async function releaseExits(page: Page): Promise<void> {
    await page.evaluate(() => {
        (window as HoldWindow).__holdExits = false;
        document
            .getAnimations()
            .forEach((a) => a.playState === 'paused' && a.play());
    });
}

/** Paused transitions on the detail element itself (not the chevron). */
async function heldTransitions(page: Page, id: string): Promise<number> {
    return page.evaluate(
        (rowId) =>
            (document.getElementById(rowId)?.querySelector('td > div') ?? null)
                ?.getAnimations()
                .filter(
                    (a) =>
                        a instanceof CSSTransition && a.playState === 'paused',
                ).length ?? 0,
        id,
    );
}

for (const viewport of [
    { name: 'desktop', width: 1280, height: 900 },
    { name: 'phone', width: 390, height: 844 },
]) {
    for (const theme of ['light', 'dark'] as const) {
        test(`pointer opens and closes a topic readably on ${viewport.name} in ${theme}`, async ({
            page,
        }, testInfo) => {
            await page.setViewportSize({
                width: viewport.width,
                height: viewport.height,
            });
            await page.emulateMedia({ colorScheme: theme });
            await openTopics(page);

            const trigger = triggers(page).first();
            await trigger.click();

            await expect(trigger).toHaveAttribute('aria-expanded', 'true');
            await expect(trigger).toBeFocused();
            const controlsId = await trigger.getAttribute('aria-controls');
            expect(controlsId).toBeTruthy();
            await expect(page.locator(`[id="${controlsId}"]`)).toBeVisible();
            await expect(exposedDetails(page)).toHaveCount(1);
            await expect(exposedDetails(page)).toBeVisible();
            await settleMotion(page);
            await testInfo.attach(`open-${viewport.name}-${theme}`, {
                body: await page.screenshot(),
                contentType: 'image/png',
            });

            await trigger.click();
            await expect(trigger).toHaveAttribute('aria-expanded', 'false');
            await expect(trigger).toBeFocused();
            // No accessible ghost while exiting, and nothing left afterwards.
            await expect(exposedDetails(page)).toHaveCount(0);
            await expect(page.locator(`[id="${controlsId}"]`)).toHaveCount(0);
        });
    }
}

test('switching topics keeps the outgoing detail hidden from assistive technology during its exit', async ({
    page,
}) => {
    await openTopics(page);
    const list = triggers(page);

    await list.nth(0).click();
    await expect(exposedDetails(page)).toHaveCount(1);
    await settleMotion(page);
    const firstId = (await list.nth(0).getAttribute('aria-controls')) ?? '';
    const firstRow = page.locator(`[id="${firstId}"]`);

    await holdExits(page);
    await list.nth(1).click();

    // A's real exit transition exists and is frozen mid-flight.
    await expect.poll(() => heldTransitions(page, firstId)).toBeGreaterThan(0);
    await expect(firstRow).toHaveCount(1);
    await expect(firstRow).toHaveAttribute('aria-hidden', 'true');
    await expect(firstRow).toHaveJSProperty('inert', true);
    await expect(list.nth(0)).toHaveAttribute('aria-expanded', 'false');
    await expect(list.nth(1)).toHaveAttribute('aria-expanded', 'true');
    // Only B is exposed while A is still mounted.
    await expect(exposedDetails(page)).toHaveCount(1);
    const secondId = (await list.nth(1).getAttribute('aria-controls')) ?? '';
    await expect(page.locator(`[id="${secondId}"]`)).toBeVisible();
    await expect(page.locator(`[id="${secondId}"]`)).not.toHaveAttribute(
        'aria-hidden',
        'true',
    );

    await releaseExits(page);
    await expect(firstRow).toHaveCount(0);
    await expect(exposedDetails(page)).toHaveCount(1);
});

test('reopening during a real exit keeps the same row element open', async ({
    page,
}) => {
    await openTopics(page);
    const trigger = triggers(page).first();
    await trigger.click();
    await settleMotion(page);
    const id = (await trigger.getAttribute('aria-controls')) ?? '';
    const row = page.locator(`[id="${id}"]`);
    await page.evaluate((rowId) => {
        (window as HoldWindow).__row = document.getElementById(rowId);
    }, id);

    await holdExits(page);
    await trigger.click();
    // The exit exists and has not finished when we reverse it.
    await expect.poll(() => heldTransitions(page, id)).toBeGreaterThan(0);
    await expect(row).toHaveAttribute('aria-hidden', 'true');
    await expect(exposedDetails(page)).toHaveCount(0);

    // Reverse while the exit is still held, then let the retarget play out.
    await trigger.click();
    await releaseExits(page);
    await settleMotion(page);

    await expect(trigger).toHaveAttribute('aria-expanded', 'true');
    await expect(row).not.toHaveAttribute('aria-hidden', 'true');
    await expect(row).toHaveJSProperty('inert', false);
    await expect(exposedDetails(page)).toHaveCount(1);
    await expect(exposedDetails(page)).toBeVisible();
    // Same DOM element: the exit was retargeted, not replaced.
    expect(
        await page.evaluate(
            (rowId) =>
                document.getElementById(rowId) === (window as HoldWindow).__row,
            id,
        ),
    ).toBe(true);

    // And it still closes normally afterwards.
    await trigger.click();
    await expect(exposedDetails(page)).toHaveCount(0);
    await expect(row).toHaveCount(0);
});

test('keyboard activation changes state without motion, even after a pointer click', async ({
    page,
}) => {
    await openTopics(page);
    const trigger = triggers(page).first();

    // Pointer first, to catch stale input-mode state.
    await trigger.click();
    await settleMotion(page);
    await trigger.click();
    await settleMotion(page);
    await expect(exposedDetails(page)).toHaveCount(0);

    for (const key of ['Enter', 'Space']) {
        await trigger.focus();
        await page.keyboard.press(key);
        await expect(trigger).toHaveAttribute('aria-expanded', 'true');
        await expect(exposedDetails(page)).toBeVisible();
        expect(await runningMotion(page)).toBe(0);

        await page.keyboard.press(key);
        await expect(trigger).toHaveAttribute('aria-expanded', 'false');
        await expect(exposedDetails(page)).toHaveCount(0);
        // Closed rows are removed at once, not after an exit.
        await expect(page.locator('[id^="public-topic-details-"]')).toHaveCount(
            0,
        );
        expect(await runningMotion(page)).toBe(0);
    }
});

test.describe('reduced motion', () => {
    test.use({ contextOptions: { reducedMotion: 'reduce' } });

    test('pointer and keyboard changes are immediate and readable', async ({
        page,
    }) => {
        await openTopics(page);
        const trigger = triggers(page).first();

        await trigger.click();
        await expect(exposedDetails(page)).toBeVisible();
        expect(await runningMotion(page)).toBe(0);
        await expect(exposedDetails(page)).toContainText('Judul Internasional');

        await trigger.click();
        await expect(page.locator('[id^="public-topic-details-"]')).toHaveCount(
            0,
        );

        await trigger.focus();
        await page.keyboard.press('Enter');
        await expect(exposedDetails(page)).toBeVisible();
        await page.keyboard.press('Enter');
        await expect(page.locator('[id^="public-topic-details-"]')).toHaveCount(
            0,
        );
    });
});

test('switching to reduced motion during an exit leaves no stale row', async ({
    page,
}) => {
    await openTopics(page);
    const trigger = triggers(page).first();
    await trigger.click();
    await settleMotion(page);

    await trigger.click();
    await page.emulateMedia({ reducedMotion: 'reduce' });

    await expect(page.locator('[id^="public-topic-details-"]')).toHaveCount(0);
    await expect(exposedDetails(page)).toHaveCount(0);
});

test('search, filters and pagination leave no stale details and keep their URLs', async ({
    page,
}) => {
    await openTopics(page);
    const search = page.getByPlaceholder(
        'Cari judul, ringkasan, mahasiswa, atau NIM...',
    );
    await expect(triggers(page)).toHaveCount(10);

    // Search to an empty result while a detail is exiting, then clear it.
    await triggers(page).first().click();
    await triggers(page).first().click();
    await search.fill(`tidak-ada-${fixture.prefix}`);
    await expect(
        page.getByText('Tidak ada topik', { exact: true }),
    ).toBeVisible();
    await expect(exposedDetails(page)).toHaveCount(0);

    await search.fill(fixture.prefix);
    await expect(triggers(page)).toHaveCount(10);
    await expect(search).toHaveValue(fixture.prefix);
    await expect(exposedDetails(page)).toHaveCount(0);
    await expect(page).toHaveURL(
        new RegExp(`search=${encodeURIComponent(fixture.prefix)}`),
    );

    // Program filter closes an open detail.
    await triggers(page).first().click();
    await expect(exposedDetails(page)).toHaveCount(1);
    await page.getByRole('combobox', { name: 'Program studi' }).click();
    await page.getByRole('option', { name: fixture.programName }).click();
    await expect(page).toHaveURL(
        new RegExp(`program=${encodeURIComponent(fixture.programSlug)}`),
    );
    await expect(exposedDetails(page)).toHaveCount(0);
    await expect(triggers(page)).toHaveCount(10);

    // Year filter: six topics were held this year.
    const year = String(new Date().getFullYear());
    await triggers(page).first().click();
    await page.getByRole('radio', { name: new RegExp(`^${year}`) }).click();
    await expect(page).toHaveURL(new RegExp(`year=${year}`));
    await expect(triggers(page)).toHaveCount(6);
    await expect(exposedDetails(page)).toHaveCount(0);
    await expect(search).toHaveValue(fixture.prefix);

    // Pagination across the 12 owned topics.
    await page.getByRole('radio', { name: /^Semua/ }).click();
    await expect(page).not.toHaveURL(/year=/);
    await expect(triggers(page)).toHaveCount(10);
    await triggers(page).first().click();
    await expect(exposedDetails(page)).toHaveCount(1);
    await page.getByRole('button', { name: 'Berikutnya' }).first().click();
    await expect(page).toHaveURL(/page=2/);
    await expect(triggers(page)).toHaveCount(2);
    await expect(exposedDetails(page)).toHaveCount(0);
    await expect(search).toHaveValue(fixture.prefix);

    await triggers(page).first().click();
    await expect(exposedDetails(page)).toHaveCount(1);
    await page
        .getByRole('combobox', { name: 'Per halaman' })
        .first()
        .selectOption('15');
    await expect(page).toHaveURL(/per_page=15/);
    await expect(triggers(page)).toHaveCount(12);
    await expect(exposedDetails(page)).toHaveCount(0);
    await expect(search).toHaveValue(fixture.prefix);
});

test('details of different lengths open and close without leaving a row behind', async ({
    page,
}) => {
    await openTopics(page);
    const list = triggers(page);

    for (const index of [0, 1, 2, 3]) {
        await list.nth(index).click();
        await expect(exposedDetails(page)).toBeVisible();
        await list.nth(index).click();
        await expect(page.locator('[id^="public-topic-details-"]')).toHaveCount(
            0,
        );
    }
});
