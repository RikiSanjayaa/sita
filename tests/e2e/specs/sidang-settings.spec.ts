import { execFileSync } from 'node:child_process';

import { expect, test } from '@playwright/test';

import {
    playwrightEnvironment,
    repositoryRoot,
    runArtisan,
} from '../support/db';
import { authStatePath } from '../support/thesis-fixtures';

test.use({ storageState: authStatePath('superadmin') });

test('program settings show validation errors and preserve the draft', async ({
    page,
}, testInfo) => {
    const original = JSON.parse(
        execFileSync(
            'php',
            [
                'artisan',
                'tinker',
                "--execute=$p=App\\Models\\ProgramStudi::query()->where('slug','ilkom')->firstOrFail(); echo json_encode(['id'=>$p->id,'settings'=>$p->sidang_settings]);",
            ],
            {
                cwd: repositoryRoot,
                env: playwrightEnvironment(),
                encoding: 'utf8',
            },
        ).trim(),
    ) as {
        id: number;
        settings: Record<string, unknown> | null;
    };

    try {
        await page.goto(`/admin/program-studis/${original.id}/edit`);
        await page
            .getByRole('button', { name: 'Atur Dokumen dan Nilai Sidang' })
            .click();
        const dialog = page.locator('.fi-modal-window:visible').last();
        const submit = dialog.getByRole('button', {
            name: 'Simpan Pengaturan',
        });
        await dialog
            .getByLabel('Minimal dosen penguji (selain pembimbing)')
            .fill('2');
        await dialog.getByLabel('Bobot Penguji 1 (%)').fill('40');
        await submit.click();
        await expect(
            dialog.getByText('Total bobot ketiga penilai harus 100%.'),
        ).toBeVisible();
        await expect(dialog.getByLabel('Bobot Penguji 1 (%)')).toHaveValue(
            '40',
        );

        await dialog.getByLabel('Bobot Penguji 1 (%)').fill('30');
        await dialog
            .getByRole('spinbutton', { name: 'Nilai minimum B+*', exact: true })
            .fill('90');
        await submit.click();
        await expect(
            dialog.getByText(
                'Batas nilai huruf harus berurutan dari tertinggi ke terendah.',
            ),
        ).toBeVisible();

        await dialog
            .getByRole('spinbutton', { name: 'Nilai minimum B+*', exact: true })
            .fill('71');
        const rubric = dialog.getByRole('textbox', {
            name: 'Komponen nilai penguji*',
            exact: true,
        });
        const originalRubric = await rubric.inputValue();
        await rubric.fill('Penguasaan Materi | 60');
        await submit.click();
        await expect(
            dialog.getByText(
                'Gunakan format Komponen | Bobot. Total bobot harus 100%.',
            ),
        ).toBeVisible();
        for (const width of [1280, 390]) {
            await page.setViewportSize({ width, height: 920 });
            for (const theme of ['light', 'dark']) {
                await page.evaluate(
                    (mode) =>
                        document.documentElement.classList.toggle(
                            'dark',
                            mode === 'dark',
                        ),
                    theme,
                );
                await rubric.scrollIntoViewIfNeeded();
                await testInfo.attach(`settings-error-${width}-${theme}`, {
                    body: await page.screenshot(),
                    contentType: 'image/png',
                });
            }
        }
        await rubric.fill(originalRubric);
        await submit.click();
        await expect(dialog).toBeHidden();
    } finally {
        const encoded = Buffer.from(JSON.stringify(original.settings)).toString(
            'base64',
        );
        runArtisan([
            'tinker',
            `--execute=App\\Models\\ProgramStudi::query()->findOrFail(${original.id})->update(['sidang_settings'=>json_decode(base64_decode('${encoded}'),true)]);`,
        ]);
    }
});
