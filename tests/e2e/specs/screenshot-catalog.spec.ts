import fs from 'node:fs';
import path from 'node:path';

import { expect, test, type Page } from '@playwright/test';

import { catalogFixedNow } from '../support/catalog-env';
import {
    catalogPages,
    catalogThemes,
    catalogWidths,
    roleAccounts,
} from '../support/catalog-pages';
import { repositoryRoot, runArtisan } from '../support/db';
import { authStatePath } from '../support/thesis-fixtures';

// Screenshot catalog (issue #127). Run with `npm run catalog`; the normal e2e
// project ignores this file. Review-state fixtures clone seeded demo documents;
// no shared document is changed, and only those clones are removed afterward.
const outputRoot = path.resolve(
    repositoryRoot,
    process.env.CATALOG_OUTPUT ?? 'catalog-output',
);

const freezeAnimations = `
    *, *::before, *::after {
        animation: none !important;
        transition: none !important;
        scroll-behavior: auto !important;
        caret-color: transparent !important;
    }
`;

function shoot(page: Page): Promise<Buffer> {
    return page.screenshot({
        fullPage: true,
        animations: 'disabled',
        caret: 'hide',
    });
}

const reviewFixtureGroup = 'catalog-ux148-document-';

test.beforeAll(() => {
    runArtisan([
        'tinker',
        '--execute',
        `
        $student = App\\Models\\User::where('email', 'mahasiswa@sita.test')->firstOrFail();
        $lecturer = App\\Models\\User::where('email', 'dosen@sita.test')->firstOrFail();
        $source = App\\Models\\MentorshipDocument::where('student_user_id', $student->id)->where('lecturer_user_id', $lecturer->id)->firstOrFail();
        foreach (['submitted', 'needs_revision'] as $status) {
            $group = '${reviewFixtureGroup}'.$status;
            if (App\\Models\\MentorshipDocument::where('document_group', $group)->exists()) { continue; }
            $document = $source->replicate();
            $document->forceFill([
                'document_group' => $group, 'title' => 'Draft Metodologi Demo '.$status,
                'status' => $status, 'uploaded_by_user_id' => $student->id,
                'uploaded_by_role' => 'mahasiswa',
                'revision_notes' => $status === 'needs_revision' ? implode("\\n", array_map(fn ($n) => $n.'. Perjelas metode, sumber data, dan interpretasi hasil penelitian.', range(1, 12))) : null,
                'reviewed_at' => $status === 'needs_revision' ? now() : null,
            ])->save();
        }
        $project = App\\Models\\ThesisProject::where('student_user_id', $student->id)->current()->firstOrFail();
        foreach (['internal', 'student'] as $visibility) {
            App\\Models\\ThesisProjectNote::firstOrCreate([
                'project_id' => $project->id, 'author_user_id' => $lecturer->id,
                'visibility' => $visibility,
                'body' => 'Katalog UX148 '.$visibility.': '.str_repeat('Jelaskan metode, pembahasan hasil, dan langkah berikutnya. ', 12),
            ]);
        }
        foreach ([9, 10] as $hour) {
            App\\Models\\MentorshipSchedule::factory()->create([
                'student_user_id' => $student->id, 'lecturer_user_id' => $lecturer->id,
                'status' => 'approved', 'topic' => 'Katalog UX148 agenda '.$hour,
                'requested_for' => now()->addDay()->setTime($hour, 0),
                'scheduled_for' => now()->addDay()->setTime($hour, 0),
            ]);
        }
    `,
    ]);
});

test.afterAll(() => {
    runArtisan([
        'tinker',
        '--execute',
        `App\\Models\\MentorshipDocument::where('document_group', 'like', '${reviewFixtureGroup}%')->delete();
         App\\Models\\ThesisProjectNote::where('body', 'like', 'Katalog UX148 %')->delete();
         App\\Models\\MentorshipSchedule::where('topic', 'like', 'Katalog UX148 agenda %')->delete();`,
    ]);
});

const names = new Set<string>();

for (const entry of catalogPages) {
    const key = `${entry.role}/${entry.name}`;

    if (names.has(key)) {
        throw new Error(`Duplicate catalog entry ${key}`);
    }

    names.add(key);

    test(`${key} (${entry.path})`, async ({ browser }) => {
        const account = entry.account ?? roleAccounts[entry.role];
        const directory = path.join(outputRoot, entry.role);

        fs.mkdirSync(directory, { recursive: true });

        for (const { width, height } of catalogWidths) {
            for (const theme of catalogThemes) {
                // A fresh context per capture: only this role's auth state.
                const context = await browser.newContext({
                    storageState: account
                        ? authStatePath(account)
                        : { cookies: [], origins: [] },
                    viewport: { width, height },
                    deviceScaleFactor: 1,
                    permissions: ['clipboard-read', 'clipboard-write'],
                    colorScheme: theme,
                    reducedMotion: 'reduce',
                    locale: 'id-ID',
                    timezoneId: 'Asia/Makassar',
                });

                try {
                    await context.clock.setFixedTime(new Date(catalogFixedNow));
                    // Inertia pages read `appearance`, Filament reads `theme`.
                    await context.addInitScript((value) => {
                        localStorage.setItem('appearance', value);
                        localStorage.setItem('theme', value);
                    }, theme);

                    const page = await context.newPage();
                    const response = await page.goto(entry.path);

                    expect(response?.ok(), `${entry.path} responded`).toBe(
                        true,
                    );
                    await page.waitForLoadState('networkidle');

                    // Never shoot a login redirect or an error page.
                    expect(new URL(page.url()).pathname).toBe(
                        new URL(entry.path, 'http://catalog.test').pathname,
                    );
                    await expect(
                        page
                            .getByRole('heading', { name: entry.heading })
                            .first(),
                    ).toBeVisible();
                    await expect(page.locator('html')).toHaveClass(
                        theme === 'dark' ? /dark/ : /^(?!.*\bdark\b).*$/,
                    );

                    await page.addStyleTag({ content: freezeAnimations });

                    if (entry.prepare) {
                        await entry.prepare(page);
                    }

                    await page.evaluate(() => document.fonts.ready);
                    await page.waitForLoadState('networkidle');
                    // Two frames so layout and fonts have settled.
                    await page.evaluate(
                        () =>
                            new Promise<void>((resolve) =>
                                requestAnimationFrame(() =>
                                    requestAnimationFrame(() => resolve()),
                                ),
                            ),
                    );

                    // Chevrons and badges can still be a pixel off when the
                    // last Alpine/Livewire tick lands; shoot until two
                    // consecutive captures are identical.
                    let image = await shoot(page);

                    for (let attempt = 0; attempt < 10; attempt += 1) {
                        await page.waitForTimeout(150);

                        const next = await shoot(page);
                        const settled = next.equals(image);

                        image = next;

                        if (settled) {
                            break;
                        }
                    }

                    fs.writeFileSync(
                        path.join(
                            directory,
                            `${entry.name}-${width}-${theme}.png`,
                        ),
                        image,
                    );
                } finally {
                    await context.close();
                }
            }
        }
    });
}
