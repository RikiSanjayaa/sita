import { expect, test } from '@playwright/test';

import { runArtisan } from '../support/db';
import { authStatePath } from '../support/thesis-fixtures';

const prefix = 'E2E UX148 long note ';
const noteBody = `${prefix}${'Pembahasan metode dan hasil bimbingan.\n'.repeat(30)}Akhir catatan lengkap.`;

test('admin reads long revision and both visibility notes without expanding every table row, and copies contacts', async ({
    browser,
}) => {
    runArtisan([
        'tinker',
        '--execute',
        `
        $student = App\\Models\\User::where('email', 'mahasiswa@sita.test')->firstOrFail();
        $lecturer = App\\Models\\User::where('email', 'dosen@sita.test')->firstOrFail();
        $project = App\\Models\\ThesisProject::where('student_user_id', $student->id)->current()->firstOrFail();
        foreach (['internal', 'student'] as $visibility) {
            App\\Models\\ThesisProjectNote::create(['project_id' => $project->id, 'author_user_id' => $lecturer->id, 'visibility' => $visibility, 'body' => ${JSON.stringify(noteBody)}]);
        }
        $document = App\\Models\\MentorshipDocument::where('student_user_id', $student->id)->firstOrFail()->replicate();
        $document->forceFill(['document_group' => '${prefix}', 'title' => 'Demo catatan revisi panjang', 'revision_notes' => ${JSON.stringify(noteBody)}, 'status' => 'needs_revision'])->save();
    `,
    ]);
    const context = await browser.newContext({
        storageState: authStatePath('admin'),
        permissions: ['clipboard-read', 'clipboard-write'],
    });
    try {
        const page = await context.newPage();
        await page.goto('/admin/thesis-projects?search=Mahasiswa%20SiTA');
        await page
            .locator('tr')
            .filter({ hasText: 'Mahasiswa SiTA' })
            .first()
            .getByRole('link', { name: /view/i })
            .click();
        await page.getByText('mahasiswa@sita.test', { exact: true }).click();
        expect(await page.evaluate(() => navigator.clipboard.readText())).toBe(
            'mahasiswa@sita.test',
        );
        await page.getByRole('tab', { name: 'Dokumen', exact: true }).click();
        const revision = page
            .locator('details.sita-expandable-note')
            .filter({ hasText: prefix })
            .first();
        await expect(
            revision.getByRole('region', { name: 'Catatan lengkap' }),
        ).toBeHidden();
        const collapsedHeight = (await revision.boundingBox())!.height;
        expect(collapsedHeight).toBeLessThan(140);
        await revision.locator('summary').focus();
        await revision.locator('summary').press('Enter');
        await expect(
            revision.getByRole('region', { name: 'Catatan lengkap' }),
        ).toHaveText(noteBody);
        await expect(
            revision.getByRole('region', { name: 'Catatan lengkap' }),
        ).toBeVisible();
        await revision.locator('summary').press('Enter');
        await expect(
            revision.getByRole('region', { name: 'Catatan lengkap' }),
        ).toBeHidden();
        await page.getByRole('tab', { name: 'Catatan', exact: true }).click();
        const notes = page
            .locator('details.sita-expandable-note:visible')
            .filter({ hasText: prefix });
        await expect(notes).toHaveCount(2);
        for (const note of await notes.all()) {
            await expect(
                note.getByRole('region', { name: 'Catatan lengkap' }),
            ).toBeHidden();
            await note.locator('summary').click();
            await expect(
                note.getByRole('region', { name: 'Catatan lengkap' }),
            ).toHaveText(noteBody);
            await expect(
                note.getByRole('region', { name: 'Catatan lengkap' }),
            ).toBeVisible();
        }
    } finally {
        await context.close();
        runArtisan([
            'tinker',
            '--execute',
            `App\\Models\\ThesisProjectNote::where('body', 'like', '${prefix}%')->delete(); App\\Models\\MentorshipDocument::where('document_group', '${prefix}')->delete();`,
        ]);
    }
});
