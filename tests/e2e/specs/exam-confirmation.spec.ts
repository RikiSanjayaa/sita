import { execFileSync } from 'node:child_process';
import fs from 'node:fs/promises';
import path from 'node:path';

import { expect, test, type Page } from '@playwright/test';

import {
    playwrightEnvironment,
    repositoryRoot,
    seedPlaywrightScenarios,
} from '../support/db';
import {
    openAdminProject,
    openWorkflowAction,
    pickFilamentSelect,
} from '../support/golden-path';
import {
    authStatePath,
    playwrightAccounts,
    thesisScenarios,
} from '../support/thesis-fixtures';

function php(code: string): string {
    return execFileSync('php', ['artisan', 'tinker', '--execute', code], {
        cwd: repositoryRoot,
        env: playwrightEnvironment(),
        encoding: 'utf8',
    }).trim();
}

const scenarios = [
    {
        key: 'sempro',
        scenario: thesisScenarios.semproTwoExaminers,
        type: 'sempro',
        label: 'Sempro',
        actor: 'dosen3',
        score: false,
    },
    {
        key: 'sinopsis',
        scenario: thesisScenarios.sinopsisObe,
        type: 'sempro',
        label: 'Sinopsis',
        actor: 'dosen3',
        score: true,
    },
    {
        key: 'sidang',
        scenario: thesisScenarios.sidangWithPanel,
        type: 'sidang',
        label: 'Sidang Skripsi',
        actor: 'dosen1',
        score: true,
    },
] as const;

function defenseQuery(email: string, type: string): string {
    return `App\\Models\\ThesisDefense::whereHas("project.student", fn($q) => $q->where("email", "${email}"))->where("type", "${type}")->latest("attempt_no")`;
}

async function capture(page: Page, state: string): Promise<void> {
    const directory = process.env.EXAM_CONFIRM_SCREENSHOTS;
    if (!directory) return;
    await fs.mkdir(directory, { recursive: true });
    for (const width of [1280, 390]) {
        for (const theme of ['light', 'dark']) {
            await page.setViewportSize({ width, height: 900 });
            await page.evaluate((appearance) => {
                document.documentElement.classList.toggle(
                    'dark',
                    appearance === 'dark',
                );
                localStorage.setItem('theme', appearance);
            }, theme);
            await page.evaluate(async () => {
                await document.fonts.ready;
                await Promise.all(
                    document
                        .getAnimations()
                        .filter(
                            (animation) =>
                                animation.effect?.getComputedTiming()
                                    .iterations !== Infinity,
                        )
                        .map((animation) => animation.finished.catch(() => {})),
                );
            });
            if (
                state.includes('individual-error') ||
                state.includes('status-error')
            ) {
                await page
                    .getByRole('alert')
                    .evaluate((element) =>
                        element.scrollIntoView({ block: 'center' }),
                    );
                await expect(page.getByRole('alert')).toBeInViewport();
            }
            await expect(page.locator('body')).toBeVisible();
            expect(
                await page.evaluate(
                    () => document.documentElement.scrollWidth <= innerWidth,
                ),
            ).toBe(true);
            await page.screenshot({
                path: path.join(directory, `${state}-${width}-${theme}.png`),
            });
        }
    }
    await page.setViewportSize({ width: 1280, height: 900 });
}

async function openDecision(page: Page, student: string): Promise<void> {
    await page.goto('/dosen/seminar-proposal');
    await page.getByPlaceholder(/cari nama/i).fill(student);
    await page
        .getByRole('row')
        .filter({ hasText: student })
        .filter({ hasText: /#1/ })
        .locator('td')
        .first()
        .click();
    await page
        .getByRole('button', { name: 'Input Keputusan Saya', exact: true })
        .click();
}

test.describe('Exam confirmation', () => {
    test.setTimeout(process.env.EXAM_CONFIRM_SCREENSHOTS ? 900_000 : 300_000);
    test.beforeEach(() => seedPlaywrightScenarios());

    for (const item of scenarios.flatMap((scenario) => [
        scenario,
        {
            ...scenario,
            key: `${scenario.key}-kaprodi`,
            actor: 'kaprodi' as const,
        },
    ])) {
        test(`${item.key}: individual cancel, validation, processing and confirmed persistence`, async ({
            browser,
        }) => {
            if (item.actor === 'kaprodi') {
                const email =
                    playwrightAccounts[item.scenario.studentKey].email;
                php(
                    `$d = ${defenseQuery(email, item.type)}->firstOrFail(); $d->examiners()->firstOrFail()->update(["lecturer_user_id" => App\\Models\\User::where("email", "kaprodi@sita.test")->firstOrFail()->id, "role" => "examiner"]);`,
                );
            }
            const context = await browser.newContext({
                storageState: authStatePath(item.actor),
            });
            const page = await context.newPage();
            const email = playwrightAccounts[item.scenario.studentKey].email;
            const query = defenseQuery(email, item.type);
            let requests = 0;
            page.on('request', (request) => {
                if (
                    /\/dosen\/seminar-proposal\/\d+\/decision$/.test(
                        request.url(),
                    ) &&
                    request.method() === 'POST'
                )
                    requests++;
            });
            try {
                await openDecision(page, item.scenario.studentName);
                await page
                    .getByRole('button', {
                        name:
                            item.type === 'sidang'
                                ? 'Lulus dengan Syarat'
                                : 'Lulus',
                        exact: true,
                    })
                    .click();
                if (item.score) await page.getByLabel(/^Nilai/).fill('82.5');
                else await expect(page.getByLabel(/^Nilai/)).toHaveCount(0);
                await page
                    .getByLabel('Catatan Keputusan')
                    .fill('Penilaian individual untuk konfirmasi.');
                await page
                    .getByLabel(/^Catatan Revisi/)
                    .fill('Perjelas metode evaluasi.');
                await page
                    .getByRole('button', { name: 'Tambah Butir', exact: true })
                    .click();
                await page.getByLabel('Butir 1: lokasi').fill('Bab 3');
                await page
                    .getByRole('textbox', { name: 'Uraian', exact: true })
                    .fill('Tambahkan pembanding hasil.');
                await page.getByLabel(/naskah beranotasi/i).setInputFiles({
                    name: 'catatan-penguji.pdf',
                    mimeType: 'application/pdf',
                    buffer: Buffer.from('%PDF-1.4\n%%EOF\n'),
                });
                await page
                    .getByRole('button', { name: 'Submit Keputusan' })
                    .click();
                const confirmation = page.getByRole('dialog', {
                    name: 'Konfirmasi penilaian individual',
                });
                await expect(confirmation).toContainText(
                    item.scenario.studentName,
                );
                await expect(confirmation).toContainText(`${item.label} #1`);
                await expect(confirmation).toContainText(
                    'Penilaian individual untuk konfirmasi.',
                );
                await expect(confirmation).toContainText(
                    'Bab 3: Tambahkan pembanding hasil.',
                );
                await expect(confirmation).toContainText('catatan-penguji.pdf');
                if (item.score)
                    await expect(confirmation).toContainText('82.5');
                else
                    await expect(
                        confirmation.getByText('Nilai', { exact: true }),
                    ).toHaveCount(0);
                await capture(
                    page,
                    `after-${item.key}-individual-confirmation`,
                );
                await confirmation
                    .getByRole('button', { name: 'Batal', exact: true })
                    .click();
                await expect(confirmation).toBeHidden();
                await expect(page.getByLabel('Catatan Keputusan')).toHaveValue(
                    'Penilaian individual untuk konfirmasi.',
                );
                await expect(
                    page.getByRole('textbox', { name: 'Uraian', exact: true }),
                ).toHaveValue('Tambahkan pembanding hasil.');
                expect(
                    await page
                        .getByLabel(/naskah beranotasi/i)
                        .evaluate(
                            (input: HTMLInputElement) => input.files?.[0]?.name,
                        ),
                ).toBe('catatan-penguji.pdf');
                expect(requests).toBe(0);
                expect(
                    php(
                        `echo ${query}->firstOrFail()->examiners()->whereHas("lecturer", fn($q) => $q->where("email", "${playwrightAccounts[item.actor].email}"))->firstOrFail()->decision;`,
                    ),
                ).toBe('pending');
                await capture(page, `after-${item.key}-individual-cancel`);

                await page
                    .getByLabel('Catatan Keputusan')
                    .fill('x'.repeat(2001));
                await page
                    .getByRole('button', { name: 'Submit Keputusan' })
                    .click();
                await page
                    .getByRole('button', {
                        name: 'Konfirmasi penilaian',
                        exact: true,
                    })
                    .click();
                await expect(page.getByRole('alert')).toContainText(/2000/);
                await expect(page.getByLabel('Catatan Keputusan')).toHaveValue(
                    'x'.repeat(2001),
                );
                await expect(
                    page.getByRole('textbox', { name: 'Uraian', exact: true }),
                ).toHaveValue('Tambahkan pembanding hasil.');
                expect(
                    await page
                        .getByLabel(/naskah beranotasi/i)
                        .evaluate(
                            (input: HTMLInputElement) => input.files?.[0]?.name,
                        ),
                ).toBe('catatan-penguji.pdf');
                await capture(page, `after-${item.key}-individual-error`);
                await page
                    .getByLabel('Catatan Keputusan')
                    .fill('Penilaian individual untuk konfirmasi.');
                let release: () => void = () => {};
                const held = new Promise<void>((resolve) => {
                    release = resolve;
                });
                await page.route(
                    '**/dosen/seminar-proposal/*/decision',
                    async (route) => {
                        await held;
                        await route.continue();
                    },
                );
                await page
                    .getByRole('button', { name: 'Submit Keputusan' })
                    .click();
                const confirm = page.getByRole('button', {
                    name: 'Konfirmasi penilaian',
                    exact: true,
                });
                await confirm.click({ clickCount: 2 });
                await expect(
                    page
                        .getByRole('button', {
                            name: 'Menyimpan...',
                            exact: true,
                        })
                        .last(),
                ).toBeDisabled();
                expect(requests).toBe(2); // One failed validation and one held request.
                await capture(page, `after-${item.key}-individual-processing`);
                release();
                await expect(confirmation).toBeHidden({ timeout: 90_000 });
                await expect(
                    page.getByRole('button', {
                        name: 'Input Keputusan Saya',
                        exact: true,
                    }),
                ).toHaveCount(0);
                const stored = JSON.parse(
                    php(
                        `$d = ${query}->firstOrFail(); $e = $d->examiners()->whereHas("lecturer", fn($q) => $q->where("email", "${playwrightAccounts[item.actor].email}"))->firstOrFail(); echo json_encode([$e->decision, $e->score, $e->notes, $d->status]);`,
                    ),
                );
                expect(stored).toEqual([
                    'pass_with_revision',
                    item.score ? '82.50' : null,
                    'Penilaian individual untuk konfirmasi.',
                    'scheduled',
                ]);
                await capture(page, `after-${item.key}-individual-saved`);
            } finally {
                await context.close();
            }
        });
    }

    for (const item of scenarios) {
        test(`${item.key}: final result cancel, validation, service error and confirm`, async ({
            browser,
        }) => {
            const email = playwrightAccounts[item.scenario.studentKey].email;
            const query = defenseQuery(email, item.type);
            // Starting condition only: all individual decisions already submitted.
            php(
                `$d = ${query}->firstOrFail(); foreach ($d->examiners as $e) { app(App\\Services\\ThesisDefenseExaminerDecisionService::class)->submit($e->lecturer, $d->fresh(), ["decision" => "pass", "score" => ${item.score ? '84' : 'null'}, "decision_notes" => "Keputusan fixture."]); }`,
            );
            const context = await browser.newContext({
                storageState: authStatePath(
                    item.key === 'sinopsis' ? 'superadmin' : 'admin',
                ),
            });
            const page = await context.newPage();
            try {
                await openAdminProject(page, item.scenario.studentName);
                const form = await openWorkflowAction(
                    page,
                    item.type === 'sempro'
                        ? /^Catat Hasil/
                        : /^Selesaikan Sidang/,
                );
                await pickFilamentSelect(form, 0, 'pass_with_revision');
                await form
                    .getByRole('button', { name: 'Tinjau hasil akhir' })
                    .click();
                expect(
                    await form
                        .locator('textarea')
                        .last()
                        .evaluate(
                            (input: HTMLTextAreaElement) =>
                                input.validity.valueMissing,
                        ),
                ).toBe(true);
                await form.getByLabel('Batas Revisi').fill('2026-12-15T10:00');
                if (item.type === 'sidang')
                    await form
                        .getByLabel('Catatan Revisi')
                        .fill('Lengkapi analisis hasil.');
                await form
                    .locator('textarea')
                    .last()
                    .fill('Hasil akhir dengan revisi.');
                await form
                    .getByRole('button', { name: 'Tinjau hasil akhir' })
                    .click();
                const confirmation = page
                    .locator('.fi-modal-window:visible')
                    .last();
                await expect(confirmation).toContainText(
                    'Konfirmasi hasil akhir ujian',
                );
                await expect(confirmation).toContainText(
                    item.scenario.studentName,
                );
                await expect(confirmation).toContainText('#1');
                await expect(confirmation).toContainText('15/12/2026 10:00');
                await expect(confirmation).toContainText(
                    'Hasil akhir dengan revisi.',
                );
                await expect(confirmation).toContainText(
                    item.type === 'sempro' ? 'belum berpindah' : 'masih aktif',
                );
                await capture(page, `after-${item.key}-final-confirmation`);
                await confirmation
                    .getByRole('button', { name: 'Batal', exact: true })
                    .click();
                await expect(
                    form.getByRole('button', { name: 'Tinjau hasil akhir' }),
                ).toBeVisible();
                await expect(form.locator('textarea').last()).toHaveValue(
                    'Hasil akhir dengan revisi.',
                );
                expect(php(`echo ${query}->firstOrFail()->status;`)).toBe(
                    'awaiting_finalization',
                );
                await capture(page, `after-${item.key}-final-cancel`);

                // A real SQLite write failure after review, without relaxing business rules.
                await form
                    .getByRole('button', { name: 'Tinjau hasil akhir' })
                    .click();
                php(
                    `$d = ${query}->firstOrFail(); Illuminate\\Support\\Facades\\DB::unprepared("CREATE TRIGGER exam_confirmation_failure BEFORE UPDATE ON thesis_defenses WHEN NEW.id = ".$d->id." AND NEW.status = 'completed' BEGIN SELECT RAISE(ABORT, 'Confirmation storage unavailable'); END");`,
                );
                await page
                    .getByRole('button', {
                        name: 'Konfirmasi hasil akhir',
                        exact: true,
                    })
                    .click();
                // The failed transaction must leave both the persisted result and draft intact.
                await expect(
                    page
                        .getByText(/Gagal (mencatat hasil|memperbarui)/)
                        .first(),
                ).toBeVisible();
                await expect(form.locator('textarea').last()).toHaveValue(
                    'Hasil akhir dengan revisi.',
                );
                expect(php(`echo ${query}->firstOrFail()->status;`)).toBe(
                    'awaiting_finalization',
                );
                await capture(page, `after-${item.key}-final-error`);
                await page
                    .locator('.fi-no-notification')
                    .filter({ hasText: /Gagal (mencatat hasil|memperbarui)/ })
                    .getByRole('button')
                    .first()
                    .click();
                php(
                    `Illuminate\\Support\\Facades\\DB::unprepared("DROP TRIGGER exam_confirmation_failure");`,
                );
                await form
                    .getByRole('button', { name: 'Tinjau hasil akhir' })
                    .click();
                await expect(
                    page.getByRole('button', {
                        name: 'Konfirmasi hasil akhir',
                        exact: true,
                    }),
                ).toBeVisible();
                let mutationRequests = 0;
                let release: () => void = () => {};
                const held = new Promise<void>((resolve) => {
                    release = resolve;
                });
                await page.route('**/livewire*/update', async (route) => {
                    const body = route.request().postDataJSON() as {
                        components?: Array<{
                            calls?: Array<{ method: string }>;
                        }>;
                    };
                    if (
                        body.components?.some((component) =>
                            component.calls?.some(
                                (call) => call.method === 'callMountedAction',
                            ),
                        )
                    ) {
                        mutationRequests++;
                        await held;
                    }
                    await route.continue();
                });
                const confirm = page.getByRole('button', {
                    name: 'Konfirmasi hasil akhir',
                    exact: true,
                });
                await confirm.click({ clickCount: 2 });
                try {
                    await expect(confirm).toBeDisabled();
                    expect(mutationRequests).toBe(1);
                    await capture(page, `after-${item.key}-final-processing`);
                } finally {
                    release();
                }
                await expect(
                    page.locator('.fi-modal-window:visible'),
                ).toHaveCount(0);
                const stored = JSON.parse(
                    php(
                        `$d = ${query}->firstOrFail(); echo json_encode([$d->status, $d->result, $d->notes, $d->project->phase, $d->project->state, $d->revisions()->where("status", "open")->count()]);`,
                    ),
                );
                expect(stored.slice(0, 5)).toEqual([
                    'completed',
                    'pass_with_revision',
                    'Hasil akhir dengan revisi.',
                    item.type,
                    'active',
                ]);
                expect(stored[5]).toBeGreaterThan(0);
                await capture(page, `after-${item.key}-final-saved`);
            } finally {
                php(
                    `Illuminate\\Support\\Facades\\DB::unprepared("DROP TRIGGER IF EXISTS exam_confirmation_failure");`,
                );
                await context.close();
            }
        });
    }
    for (const [type, result, expectedPhase, expectedState] of [
        ['sempro', 'fail', 'sempro', 'active'],
        ['sidang', 'fail', 'sidang', 'active'],
        ['sidang', 'pass', 'completed', 'completed'],
    ] as const) {
        test(`${type}: ${result} final-result consequences`, async ({
            browser,
        }) => {
            const item = scenarios.find((scenario) => scenario.type === type)!;
            const email = playwrightAccounts[item.scenario.studentKey].email;
            const query = defenseQuery(email, type);
            php(
                `$d = ${query}->firstOrFail(); foreach ($d->examiners as $e) { app(App\\Services\\ThesisDefenseExaminerDecisionService::class)->submit($e->lecturer, $d->fresh(), ["decision" => "pass", "score" => ${type === 'sidang' ? '84' : 'null'}, "decision_notes" => "Keputusan fixture."]); }`,
            );
            const context = await browser.newContext({
                storageState: authStatePath('admin'),
            });
            const page = await context.newPage();
            try {
                await openAdminProject(page, item.scenario.studentName);
                const form = await openWorkflowAction(
                    page,
                    type === 'sempro' ? /^Catat Hasil/ : /^Selesaikan Sidang/,
                );
                await pickFilamentSelect(form, 0, result);
                await form
                    .locator('textarea')
                    .last()
                    .fill('Keputusan akhir sesuai hasil ujian.');
                await form
                    .getByRole('button', { name: 'Tinjau hasil akhir' })
                    .click();
                const confirmation = page
                    .locator('.fi-modal-window:visible')
                    .last();
                await expect(confirmation).toContainText(
                    result === 'fail'
                        ? 'ujian ulang perlu dijadwalkan'
                        : 'penugasan pembimbing aktif diakhiri',
                );
                await capture(page, `after-${type}-final-${result}`);
                await confirmation
                    .getByRole('button', {
                        name: 'Konfirmasi hasil akhir',
                        exact: true,
                    })
                    .click();
                await expect(
                    page.locator('.fi-modal-window:visible'),
                ).toHaveCount(0);
                expect(
                    JSON.parse(
                        php(
                            `$d = ${query}->firstOrFail(); echo json_encode([$d->result, $d->project->phase, $d->project->state]);`,
                        ),
                    ),
                ).toEqual([result, expectedPhase, expectedState]);
            } finally {
                await context.close();
            }
        });
    }

    test('a concurrent status change keeps the draft and exposes the service error without permitting another save', async ({
        browser,
    }) => {
        const item = scenarios[0];
        const query = defenseQuery(
            playwrightAccounts[item.scenario.studentKey].email,
            item.type,
        );
        const context = await browser.newContext({
            storageState: authStatePath(item.actor),
        });
        try {
            const page = await context.newPage();
            await openDecision(page, item.scenario.studentName);
            await page
                .getByRole('button', { name: 'Lulus', exact: true })
                .click();
            await page
                .getByLabel('Catatan Keputusan')
                .fill('Draft sebelum perubahan status.');
            await page
                .getByRole('button', { name: 'Submit Keputusan' })
                .click();
            php(`${query}->firstOrFail()->update(["status" => "cancelled"]);`);
            await page
                .getByRole('button', {
                    name: 'Konfirmasi penilaian',
                    exact: true,
                })
                .click();
            await expect(page.getByRole('alert')).toContainText(
                'Ujian tidak dalam status yang dapat dinilai.',
            );
            await expect(page.getByLabel('Catatan Keputusan')).toHaveValue(
                'Draft sebelum perubahan status.',
            );
            await expect(
                page.getByRole('button', { name: 'Submit Keputusan' }),
            ).toBeDisabled();
            expect(
                php(
                    `echo ${query}->firstOrFail()->examiners()->whereHas("lecturer", fn($q) => $q->where("email", "dosen3@sita.test"))->firstOrFail()->decision;`,
                ),
            ).toBe('pending');
            await capture(page, 'after-sempro-status-error');
        } finally {
            await context.close();
        }
    });

    test('kaprodi monitoring does not expose grading or finalization', async ({
        browser,
    }) => {
        const context = await browser.newContext({
            storageState: authStatePath('kaprodi'),
        });
        try {
            const page = await context.newPage();
            await page.goto('/kaprodi/sempro-sidang');
            await expect(
                page.getByText(/tanpa melakukan penilaian/),
            ).toBeVisible();
            await expect(
                page.getByRole('button', {
                    name: /Submit Keputusan|Konfirmasi penilaian|Catat Hasil|Selesaikan Sidang/,
                }),
            ).toHaveCount(0);
        } finally {
            await context.close();
        }
    });
});
