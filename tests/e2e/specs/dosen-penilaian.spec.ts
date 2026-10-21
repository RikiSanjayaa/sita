import { expect, test, type Page } from '@playwright/test';

import { daysFromNow, toDateTimeLocalString } from '../support/dates';
import { runArtisan, seedPlaywrightScenarios } from '../support/db';
import {
    authStatePath,
    playwrightAccounts,
    thesisScenarios,
} from '../support/thesis-fixtures';

// Examiner grading in the dosen portal (dosen/seminar-proposal) and revision
// resolution. admin-sempro.spec.ts drives most decisions through tinker; this
// spec drives them through the UI. It uses dedicated students (two sempro
// examiners, one scheduled sidang panel), reset in beforeAll.
const sempro = thesisScenarios.semproTwoExaminers;
const sidang = thesisScenarios.sidangWithPanel;
const sinopsis = thesisScenarios.sinopsisObe;

async function openDefenseSheet(
    page: Page,
    studentName: string,
    listLabel: 'Sempro / Sinopsis' | 'Sidang Akhir',
): Promise<void> {
    await page.goto('/dosen/seminar-proposal');
    await page.waitForLoadState('networkidle');
    await page.getByRole('button', { name: listLabel, exact: true }).click();
    await page.waitForLoadState('networkidle');
    await page.getByPlaceholder(/cari nama/i).fill(studentName);

    // The calendar grid also has rows naming the student; only the exam list shows the attempt ("#1").
    const row = page
        .getByRole('row')
        .filter({ hasText: studentName })
        .filter({ hasText: /#\d+/ })
        .first();
    await expect(row).toBeVisible();
    // Click the first cell: the whole row opens the sheet, but the centre of
    // the row can land on a nested link or on a not yet hydrated element.
    await expect(async () => {
        await row.locator('td').first().click();
        await expect(page.getByRole('dialog')).toBeVisible({ timeout: 2_000 });
    }).toPass({ timeout: 15_000 });
}

async function submitDecision(
    page: Page,
    {
        decisionLabel,
        score,
        decisionNotes,
        revisionNotes,
    }: {
        decisionLabel: RegExp;
        score?: string;
        decisionNotes: string;
        revisionNotes?: string;
    },
): Promise<void> {
    await page.getByRole('button', { name: 'Input Keputusan Saya' }).click();
    await page.getByRole('button', { name: decisionLabel }).click();

    if (score === undefined) {
        // Non-OBE sempro is decision-only: the score field is not rendered.
        await expect(page.getByLabel(/^Nilai/)).toHaveCount(0);
    } else {
        await page.getByLabel(/^Nilai/).fill(score);
    }

    await page.getByLabel('Catatan Keputusan').fill(decisionNotes);

    if (revisionNotes !== undefined) {
        await page.getByLabel(/^Catatan Revisi/).fill(revisionNotes);
    }

    await Promise.all([
        page.waitForResponse(
            (response) =>
                response.request().method() === 'POST' &&
                /\/dosen\/seminar-proposal\/\d+\/decision$/.test(
                    response.url(),
                ),
        ),
        page.getByRole('button', { name: 'Submit Keputusan' }).click(),
    ]);
}

function finalizeSempro(studentEmail: string, revisionDueAt: string): void {
    runArtisan([
        'tinker',
        `--execute=$project = App\\Models\\ThesisProject::query()->whereHas("student", fn($q) => $q->where("email", "${studentEmail}"))->where("state", "active")->latest("started_at")->firstOrFail(); $admin = App\\Models\\User::query()->where("email", "admin@sita.test")->firstOrFail(); app(App\\Services\\ThesisProjectAdminService::class)->finalizeSempro(project: $project, decidedBy: $admin->id, result: "pass_with_revision", notes: "Lulus dengan revisi metodologi.", revisionDueAt: "${revisionDueAt}");`,
    ]);
}

test.describe('Dosen penilaian ujian', () => {
    test.describe.configure({ mode: 'serial' });

    test.beforeAll(() => {
        seedPlaywrightScenarios();
    });

    test('two examiners grade a sempro in the UI, admin finalizes, and the examiner resolves the revision', async ({
        browser,
    }) => {
        const examinerOneContext = await browser.newContext({
            storageState: authStatePath(sempro.examinerKeys[0]),
        });
        const examinerTwoContext = await browser.newContext({
            storageState: authStatePath(sempro.examinerKeys[1]),
        });
        const studentContext = await browser.newContext({
            storageState: authStatePath(sempro.studentKey),
        });

        try {
            const examinerOnePage = await examinerOneContext.newPage();
            const examinerTwoPage = await examinerTwoContext.newPage();
            const studentPage = await studentContext.newPage();

            await openDefenseSheet(
                examinerOnePage,
                sempro.studentName,
                'Sempro / Sinopsis',
            );
            await submitDecision(examinerOnePage, {
                decisionLabel: /^Lulus$/,
                decisionNotes: 'Proposal layak lanjut setelah revisi.',
                revisionNotes: 'Perjelas metrik evaluasi pada bab tiga.',
            });

            // The decision is final for the examiner: no second submission.
            await openDefenseSheet(
                examinerOnePage,
                sempro.studentName,
                'Sempro / Sinopsis',
            );
            await expect(
                examinerOnePage.getByRole('button', {
                    name: 'Input Keputusan Saya',
                }),
            ).toHaveCount(0);

            await openDefenseSheet(
                examinerTwoPage,
                sempro.studentName,
                'Sempro / Sinopsis',
            );
            await submitDecision(examinerTwoPage, {
                decisionLabel: /^Lulus$/,
                decisionNotes: 'Topik sudah matang.',
            });

            await examinerTwoPage.goto('/dosen/seminar-proposal');
            await examinerTwoPage
                .getByRole('button', { name: 'Sempro / Sinopsis', exact: true })
                .click();
            await examinerTwoPage
                .getByPlaceholder(/cari nama/i)
                .fill(sempro.studentName);
            await expect(
                examinerTwoPage
                    .getByRole('row')
                    .filter({ hasText: sempro.studentName })
                    .filter({ hasText: /#\d+/ })
                    .first(),
            ).toContainText('Menunggu Finalisasi');

            finalizeSempro(
                playwrightAccounts[sempro.studentKey].email,
                toDateTimeLocalString(daysFromNow(5)),
            );

            await studentPage.goto('/mahasiswa/tugas-akhir');
            await expect(
                studentPage.getByText('Revisi Sempro').first(),
            ).toBeVisible();

            // Only the examiner who asked for the revision can close it.
            await openDefenseSheet(
                examinerOnePage,
                sempro.studentName,
                'Sempro / Sinopsis',
            );
            await Promise.all([
                examinerOnePage.waitForResponse(
                    (response) =>
                        response.request().method() === 'POST' &&
                        /\/dosen\/seminar-proposal\/revisions\/\d+\/resolve$/.test(
                            response.url(),
                        ),
                ),
                examinerOnePage
                    .getByRole('button', { name: 'Tandai Revisi Selesai' })
                    .click(),
            ]);

            await studentPage.goto('/mahasiswa/tugas-akhir');
            await expect(
                studentPage.getByText('Sempro Selesai').first(),
            ).toBeVisible();
        } finally {
            await examinerOneContext.close();
            await examinerTwoContext.close();
            await studentContext.close();
        }
    });

    test('an OBE student takes Ujian Sinopsis: the examiner must give a score and a comment', async ({
        browser,
    }) => {
        const examinerContext = await browser.newContext({
            storageState: authStatePath(sinopsis.examinerKeys[0]),
        });
        const studentContext = await browser.newContext({
            storageState: authStatePath(sinopsis.studentKey),
        });

        try {
            const examinerPage = await examinerContext.newPage();
            const studentPage = await studentContext.newPage();

            await openDefenseSheet(
                examinerPage,
                sinopsis.studentName,
                'Sempro / Sinopsis',
            );
            await expect(
                examinerPage.getByRole('dialog').getByText('Sinopsis').first(),
            ).toBeVisible();

            await examinerPage
                .getByRole('button', { name: 'Input Keputusan Saya' })
                .click();
            await examinerPage.getByRole('button', { name: /^Lulus$/ }).click();

            // Score and comment are both required before the form can be sent.
            const submitButton = examinerPage.getByRole('button', {
                name: 'Submit Keputusan',
            });
            await expect(submitButton).toBeDisabled();
            await examinerPage.getByLabel(/^Nilai/).fill('90');
            await expect(submitButton).toBeDisabled();
            await examinerPage
                .getByLabel(/^Catatan Keputusan/)
                .fill('Sinopsis jelas dan terukur.');
            await expect(submitButton).toBeEnabled();

            await Promise.all([
                examinerPage.waitForResponse(
                    (response) =>
                        response.request().method() === 'POST' &&
                        /\/dosen\/seminar-proposal\/\d+\/decision$/.test(
                            response.url(),
                        ),
                ),
                submitButton.click(),
            ]);

            await studentPage.goto('/mahasiswa/tugas-akhir');
            await expect(
                studentPage.getByText(/Ujian Sinopsis|Sinopsis/).first(),
            ).toBeVisible();
            await expect(studentPage.getByText('Seminar Proposal')).toHaveCount(
                0,
            );
        } finally {
            await examinerContext.close();
            await studentContext.close();
        }
    });

    test('a sidang panel member grades with a conditional pass, and a dosen outside the panel does not see the exam', async ({
        browser,
    }) => {
        const panelContext = await browser.newContext({
            storageState: authStatePath(sidang.panelKeys[0]),
        });
        const outsiderContext = await browser.newContext({
            storageState: authStatePath('dosen5'),
        });

        try {
            const panelPage = await panelContext.newPage();
            const outsiderPage = await outsiderContext.newPage();

            await openDefenseSheet(
                panelPage,
                sidang.studentName,
                'Sidang Akhir',
            );
            await submitDecision(panelPage, {
                decisionLabel: /^Lulus dengan Syarat$/,
                score: '78',
                decisionNotes: 'Naskah layak dengan perbaikan.',
                revisionNotes: 'Lengkapi pembahasan keterbatasan penelitian.',
            });

            const row = panelPage
                .getByRole('row')
                .filter({ hasText: sidang.studentName })
                .filter({ hasText: /#\d+/ })
                .first();
            await expect(row).toContainText('78');

            await outsiderPage.goto('/dosen/seminar-proposal');
            await outsiderPage
                .getByRole('button', { name: 'Sidang Akhir', exact: true })
                .click();
            await outsiderPage
                .getByPlaceholder(/cari nama/i)
                .fill(sidang.studentName);
            await expect(
                outsiderPage
                    .getByRole('row')
                    .filter({ hasText: sidang.studentName })
                    .filter({ hasText: /#\d+/ }),
            ).toHaveCount(0);
        } finally {
            await panelContext.close();
            await outsiderContext.close();
        }
    });
});
