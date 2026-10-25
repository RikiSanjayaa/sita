import {
    expect,
    test as base,
    type BrowserContext,
    type Page,
} from '@playwright/test';

import { runArtisan } from './db';
import {
    newGoldenStudentContext,
    removeGoldenPathStudent,
} from './golden-path';
import { authStatePath } from './thesis-fixtures';

type RecoveryScenario = 'fresh' | 'research' | 'sempro' | 'sidang';
type LecturerKey = 'dosen1' | 'dosen2' | 'dosen3' | 'dosen4' | 'dosen5';
type RecoveryWorkspace = {
    student: Page;
    admin: Page;
    lecturer: (key: LecturerKey) => Promise<Page>;
};

// The shared SQLite suite has one worker. Each recovery owns a newly recreated
// rehearsal student; it never resets the other academic scenario students.
export const test = base.extend<{
    recoveryScenario: RecoveryScenario;
    recovery: RecoveryWorkspace;
}>({
    recoveryScenario: ['fresh', { option: true }],
    recovery: async ({ browser, recoveryScenario }, runTest) => {
        const contexts: BrowserContext[] = [];
        const lecturers: Partial<Record<LecturerKey, Page>> = {};
        try {
            runArtisan([
                'tinker',
                `--execute=app(Database\\Seeders\\RecoveryScenarioSeeder::class)->run('${recoveryScenario}');`,
            ]);
            const studentContext = await newGoldenStudentContext(browser);
            contexts.push(studentContext);
            const adminContext = await browser.newContext({
                storageState: authStatePath('superadmin'),
            });
            contexts.push(adminContext);
            const student = await studentContext.newPage();
            const admin = await adminContext.newPage();
            await runTest({
                student,
                admin,
                lecturer: async (key) => {
                    const existing = lecturers[key];
                    if (existing) return existing;
                    const context = await browser.newContext({
                        storageState: authStatePath(key),
                    });
                    contexts.push(context);
                    const page = await context.newPage();
                    lecturers[key] = page;
                    return page;
                },
            });
        } finally {
            try {
                await Promise.all(contexts.map((context) => context.close()));
            } finally {
                removeGoldenPathStudent();
            }
        }
    },
});

export async function openRecoveryDefense(
    page: Page,
    studentName: string,
    type: 'Sempro / Sinopsis' | 'Sidang Akhir',
    attempt: number,
): Promise<void> {
    await page.goto('/dosen/seminar-proposal');
    await page.waitForLoadState('networkidle');
    await page.getByRole('radio', { name: type, exact: true }).click();
    await page.waitForLoadState('networkidle');
    await page.getByPlaceholder(/cari nama/i).fill(studentName);
    const row = page
        .getByRole('row')
        .filter({ hasText: studentName })
        .filter({
            has: page
                .getByRole('cell')
                .filter({ hasText: new RegExp(`#${attempt}$`) }),
        });
    await expect(row).toBeVisible();
    await row.locator('td').first().click();
    await expect(page.getByRole('dialog')).toBeVisible();
}
