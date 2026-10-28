import {
    expect,
    type Browser,
    type BrowserContext,
    type Locator,
    type Page,
    type TestInfo,
} from '@playwright/test';

import { runArtisan } from './db';

// Keep in sync with GoldenPathScenarioSeeder.
export const goldenPathStudent = {
    email: 'e2e-golden@sita.test',
    password: 'password',
    name: 'Gita Alur Emas',
} as const;

/**
 * Rebuilds the fresh student of golden-path.spec.ts: no thesis project, no
 * chat, no mentorship data. Nothing else is touched, so other specs keep their
 * own state.
 */
export function seedGoldenPathStudent(baseURL?: string): void {
    runArtisan(
        ['db:seed', '--class=GoldenPathScenarioSeeder', '--force'],
        baseURL,
    );
}

/**
 * Deletes the golden path student with its profile, thesis data, chat, mentorship
 * rows, notifications and uploaded files. Call it in afterAll so a pass or a
 * failure leaves no extra user behind for the rest of the suite.
 */
export function removeGoldenPathStudent(baseURL?: string): void {
    runArtisan(
        ['db:seed', '--class=GoldenPathCleanupSeeder', '--force'],
        baseURL,
    );
}

/**
 * The golden path student does not exist when the setup project captures the
 * shared sessions, so this context signs in through the login form like a real
 * user would.
 */
export async function newGoldenStudentContext(
    browser: Browser,
): Promise<BrowserContext> {
    const context = await browser.newContext();
    const page = await context.newPage();

    await page.goto('/login');
    await page.getByLabel('Email').fill(goldenPathStudent.email);
    await page
        .getByLabel('Kata sandi', { exact: true })
        .fill(goldenPathStudent.password);
    await page.getByRole('button', { name: 'Masuk' }).click();
    await expect(page).toHaveURL(/\/mahasiswa\/dashboard$/);
    await page.close();

    return context;
}

/**
 * Screenshot of one stage, attached to the report so a CI run (or a manual
 * rehearsal) shows what each role saw. Doubles as source material for the
 * student guide.
 */
export async function attachStageScreenshot(
    testInfo: TestInfo,
    page: Page,
    name: string,
): Promise<void> {
    await testInfo.attach(name, {
        body: await page.screenshot({ fullPage: true, animations: 'disabled' }),
        contentType: 'image/png',
    });
}

/**
 * Picks a Filament v5 select option by its value inside the open modal.
 */
export async function pickFilamentSelect(
    modal: Locator,
    fieldIndex: number,
    value: string,
): Promise<void> {
    await modal.locator('.fi-select-input-btn').nth(fieldIndex).click();
    await modal
        .locator(`.fi-select-input-option[data-value="${value}"]`)
        .click();
}

/**
 * Opens the thesis project of a student in the Filament admin panel from the
 * project table, the way an admin would.
 */
export async function openAdminProject(
    page: Page,
    studentName: string,
): Promise<void> {
    await page.goto('/admin/thesis-projects');

    const searchInput = page.getByRole('searchbox', {
        name: 'Search',
        exact: true,
    });
    await searchInput.fill(studentName);
    await searchInput.press('Enter');

    await page
        .locator('tr')
        .filter({ hasText: studentName })
        .first()
        .getByRole('link')
        .last()
        .click();

    await expect(page.getByText(studentName).first()).toBeVisible();
}

/**
 * Runs one entry of the "Aksi Workflow" menu on the admin project page and
 * returns the action modal.
 */
export async function openWorkflowAction(
    page: Page,
    label: RegExp | string,
): Promise<Locator> {
    await page.getByRole('button', { name: 'Aksi Workflow' }).click();
    await page.getByRole('menuitem', { name: label, exact: true }).click();

    const modal = page.locator('.fi-modal-window:visible').last();
    await expect(modal).toBeVisible();

    return modal;
}
