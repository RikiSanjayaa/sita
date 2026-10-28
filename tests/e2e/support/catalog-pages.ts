import { expect, type Page } from '@playwright/test';

import { type PlaywrightAccountKey } from './thesis-fixtures';

/**
 * The screenshot catalog (`npm run catalog`): every page below is captured at
 * 1280 and 390 px, light and dark, full page, and written to
 * catalog-output/<role>/<name>-<width>-<theme>.png. The same relative path is
 * used on the `pr-assets` branch under catalog/.
 *
 * Add a page by adding one entry. `heading` is mandatory: the run fails when
 * the page redirected (for example to a login screen) or that heading is not
 * visible, so a catalog image can never silently show the wrong page.
 */
export type CatalogRole =
    | 'public'
    | 'mahasiswa'
    | 'dosen'
    | 'kaprodi'
    | 'admin';

export type CatalogPage = {
    role: CatalogRole;
    /** File name stem; unique within the role. kebab-case. */
    name: string;
    path: string;
    /** Text (or pattern) of a heading that proves the intended page rendered. */
    heading: string | RegExp;
    /** Auth state to browse with; defaults to the role's main account. */
    account?: PlaywrightAccountKey;
    /** Opens the state to capture (dialog, sheet, ...) once the page is ready. */
    prepare?: (page: Page) => Promise<void>;
};

export const roleAccounts: Record<CatalogRole, PlaywrightAccountKey | null> = {
    public: null,
    mahasiswa: 'mahasiswa',
    dosen: 'dosen1',
    kaprodi: 'kaprodi',
    admin: 'admin',
};

export const catalogWidths = [
    { width: 1280, height: 800 },
    { width: 390, height: 844 },
] as const;

export const catalogThemes = ['light', 'dark'] as const;

async function openDialog(page: Page, trigger: RegExp): Promise<void> {
    await page.getByRole('button', { name: trigger }).first().click();
    await expect(page.getByRole('dialog')).toBeVisible();
}

async function openNotesSheet(page: Page): Promise<void> {
    await page
        .getByRole('row')
        .filter({ hasText: 'Muhammad Akbar' })
        .first()
        .getByRole('button', { name: 'Aksi lainnya' })
        .click();
    await page.getByRole('menuitem', { name: /^Catatan/ }).click();
    await expect(page.getByRole('dialog')).toBeVisible();
}

async function openDocumentPage(page: Page, prefix: string): Promise<void> {
    if (prefix === '/dosen/dokumen-revisi') {
        await page
            .getByPlaceholder(/Cari mahasiswa.*file/i)
            .fill('Mahasiswa SiTA');
    }
    const scope =
        prefix === '/dosen/dokumen-revisi'
            ? page.locator('tr').filter({ hasText: 'Mahasiswa SiTA' })
            : page.locator('main');
    await scope.locator(`a[href*="${prefix}/"]`).first().click();
    await expect(page).toHaveURL(new RegExp(`${prefix}/\\d+$`));
    await expect(
        page.getByRole('heading', { name: 'Detail dokumen', exact: true }),
    ).toBeVisible();
}

export const catalogPages: readonly CatalogPage[] = [
    // Public and auth screens
    { role: 'public', name: 'beranda', path: '/', heading: /Akses cepat/ },
    {
        role: 'public',
        name: 'beranda-dialog-tampilan',
        path: '/',
        heading: /Akses cepat/,
        prepare: (page) => openDialog(page, /^Buka pengaturan tampilan$/),
    },
    {
        role: 'public',
        name: 'jadwal',
        path: '/jadwal',
        heading: 'Jadwal Akan Datang',
    },
    {
        role: 'public',
        name: 'pembimbing',
        path: '/pembimbing',
        heading: 'Direktori Pembimbing',
    },
    { role: 'public', name: 'topik', path: '/topik', heading: 'Daftar Topik' },
    {
        role: 'public',
        name: 'mahasiswa-aktif',
        path: '/mahasiswa-aktif',
        heading: 'Daftar Mahasiswa',
    },
    {
        role: 'public',
        name: 'login',
        path: '/login',
        heading: 'Selamat datang kembali',
    },
    {
        role: 'public',
        name: 'lupa-password',
        path: '/forgot-password',
        heading: 'Forgot password',
    },
    {
        role: 'public',
        name: 'login-admin',
        path: '/admin/login',
        heading: 'Masuk ke panel admin',
    },

    // Mahasiswa
    {
        role: 'mahasiswa',
        name: 'dashboard',
        path: '/mahasiswa/dashboard',
        heading: 'Dashboard mahasiswa',
    },
    {
        role: 'mahasiswa',
        name: 'tugas-akhir',
        path: '/mahasiswa/tugas-akhir',
        heading: 'Status Pengajuan',
    },
    {
        role: 'mahasiswa',
        name: 'jadwal-bimbingan',
        path: '/mahasiswa/jadwal-bimbingan',
        heading: 'Kalender Bimbingan',
    },
    {
        role: 'mahasiswa',
        name: 'upload-dokumen',
        path: '/mahasiswa/upload-dokumen',
        heading: 'Dokumen yang Diupload',
    },
    {
        role: 'mahasiswa',
        name: 'dokumen-detail',
        path: '/mahasiswa/upload-dokumen',
        heading: 'Dokumen yang Diupload',
        prepare: (page) => openDocumentPage(page, '/mahasiswa/upload-dokumen'),
    },
    {
        role: 'mahasiswa',
        name: 'pesan',
        path: '/mahasiswa/pesan',
        heading: 'Pesan',
    },
    {
        role: 'mahasiswa',
        name: 'panduan',
        path: '/mahasiswa/panduan',
        heading: 'Panduan utama',
    },
    {
        role: 'mahasiswa',
        name: 'progres-mahasiswa',
        path: '/progres-mahasiswa',
        heading: 'Agenda sempro dan sidang',
    },
    {
        role: 'mahasiswa',
        name: 'profil',
        path: '/profil/10',
        heading: /Mahasiswa SiTA/,
    },
    {
        role: 'mahasiswa',
        name: 'profil-email-disalin',
        path: '/profil/10',
        heading: /Mahasiswa SiTA/,
        prepare: async (page) => {
            await page
                .getByRole('button', { name: 'Salin Email', exact: true })
                .click();
            await expect(
                page.getByRole('status').filter({ hasText: 'Email disalin' }),
            ).toHaveText('Email disalin');
            await page.evaluate(() => window.scrollTo(0, 0));
        },
    },
    {
        role: 'mahasiswa',
        name: 'pengaturan-profil',
        path: '/settings/profile',
        heading: 'Perbarui profil',
    },
    {
        role: 'mahasiswa',
        name: 'pengaturan-password',
        path: '/settings/password',
        heading: 'Ubah password',
    },
    {
        role: 'mahasiswa',
        name: 'pengaturan-tampilan',
        path: '/settings/appearance',
        heading: 'Mode tampilan',
    },
    {
        role: 'mahasiswa',
        name: 'pengaturan-notifikasi',
        path: '/settings/notifications',
        heading: 'Jenis notifikasi',
    },
    {
        role: 'mahasiswa',
        name: 'pengaturan-csat',
        path: '/settings/csat',
        heading: /Kepuasan pengguna/,
    },
    // States
    {
        role: 'mahasiswa',
        name: 'dashboard-not-started',
        path: '/mahasiswa/dashboard',
        heading: 'Dashboard mahasiswa',
        account: 'farhan',
    },
    {
        role: 'mahasiswa',
        name: 'dashboard-pending',
        path: '/mahasiswa/dashboard',
        heading: 'Dashboard mahasiswa',
        account: 'rizky',
    },
    {
        role: 'mahasiswa',
        name: 'dashboard-completed',
        path: '/mahasiswa/dashboard',
        heading: 'Dashboard mahasiswa',
        account: 'lulus-tenggang',
    },
    {
        role: 'mahasiswa',
        name: 'jadwal-bimbingan-dialog-ajukan',
        path: '/mahasiswa/jadwal-bimbingan',
        heading: 'Kalender Bimbingan',
        prepare: (page) => openDialog(page, /Ajukan/),
    },
    {
        role: 'mahasiswa',
        name: 'upload-dokumen-dialog-upload',
        path: '/mahasiswa/upload-dokumen',
        heading: 'Dokumen yang Diupload',
        prepare: (page) => openDialog(page, /Upload/),
    },
    {
        role: 'mahasiswa',
        name: 'jadwal-bimbingan-kosong',
        path: '/mahasiswa/jadwal-bimbingan',
        heading: 'Fitur Bimbingan Belum Aktif',
        account: 'rizky',
    },
    {
        role: 'mahasiswa',
        name: 'pesan-kosong',
        path: '/mahasiswa/pesan',
        heading: 'Pesan',
        account: 'rizky',
    },

    // Dosen
    {
        role: 'dosen',
        name: 'dashboard',
        path: '/dosen/dashboard',
        heading: 'Perlu tindakan',
    },
    {
        role: 'dosen',
        name: 'dashboard-hover',
        path: '/dosen/dashboard',
        heading: 'Perlu tindakan',
        prepare: async (page) => {
            await page.setViewportSize({
                width: page.viewportSize()!.width,
                height: await page.evaluate(
                    () => document.documentElement.scrollHeight,
                ),
            });
            await page.evaluate(() => window.scrollTo(0, 0));
            await page
                .getByRole('region', { name: 'Progres mahasiswa' })
                .getByRole('link')
                .filter({ hasText: 'Tiara Masa Aktif Habis' })
                .first()
                .hover();
        },
    },
    {
        role: 'dosen',
        name: 'seminar-proposal',
        path: '/dosen/seminar-proposal',
        heading: 'Daftar ujian',
    },
    {
        role: 'dosen',
        name: 'mahasiswa-bimbingan',
        path: '/dosen/mahasiswa-bimbingan',
        heading: 'Mahasiswa Aktif',
    },
    {
        role: 'dosen',
        name: 'mahasiswa-bimbingan-menu',
        path: '/dosen/mahasiswa-bimbingan',
        heading: 'Mahasiswa Aktif',
        prepare: async (page) => {
            await page.setViewportSize({
                width: page.viewportSize()!.width,
                height: await page.evaluate(
                    () => document.documentElement.scrollHeight,
                ),
            });
            await page
                .getByRole('row')
                .filter({ hasText: 'Mahasiswa SiTA' })
                .first()
                .getByRole('button', { name: 'Chat', exact: true })
                .click();
            await expect(
                page.getByRole('menuitem', { name: 'WhatsApp', exact: true }),
            ).toBeVisible();
        },
    },
    {
        role: 'dosen',
        name: 'jadwal-bimbingan',
        path: '/dosen/jadwal-bimbingan',
        heading: 'Permintaan Menunggu Konfirmasi',
    },
    {
        role: 'dosen',
        name: 'dokumen-revisi',
        path: '/dosen/dokumen-revisi',
        heading: 'Daftar Dokumen',
    },
    {
        role: 'dosen',
        name: 'dokumen-detail',
        path: '/dosen/dokumen-revisi',
        heading: 'Daftar Dokumen',
        prepare: (page) => openDocumentPage(page, '/dosen/dokumen-revisi'),
    },
    {
        role: 'dosen',
        name: 'dokumen-detail-draft',
        path: '/dosen/dokumen-revisi',
        heading: 'Daftar Dokumen',
        prepare: async (page) => {
            await openDocumentPage(page, '/dosen/dokumen-revisi');
            await page
                .getByRole('textbox', { name: 'Catatan dosen' })
                .fill(
                    'Perjelas sumber data dan metode pengambilan sampel sebelum revisi berikutnya.',
                );
        },
    },
    {
        role: 'dosen',
        name: 'dokumen-detail-revision-required',
        path: '/dosen/dokumen-revisi',
        heading: 'Daftar Dokumen',
        prepare: async (page) => {
            await openDocumentPage(page, '/dosen/dokumen-revisi');
            // The pinned server clock expires XSRF-TOKEN before the browser's
            // real clock. Send the session's real meta token, as login does.
            const csrfToken = await page
                .locator('meta[name="csrf-token"]')
                .getAttribute('content');
            expect(csrfToken).toBeTruthy();
            await page.route(
                '**/dosen/dokumen-revisi/*/review',
                async (route) => {
                    await route.continue({
                        headers: {
                            ...route.request().headers(),
                            'X-CSRF-TOKEN': csrfToken!,
                        },
                    });
                },
            );
            await page.getByRole('textbox', { name: 'Catatan dosen' }).fill('');
            await page
                .getByRole('button', { name: 'Minta revisi', exact: true })
                .click();
            await expect(
                page.getByText(
                    'Catatan revisi wajib diisi saat meminta revisi.',
                    { exact: true },
                ),
            ).toBeVisible();
        },
    },
    { role: 'dosen', name: 'pesan', path: '/dosen/pesan', heading: 'Pesan' },
    {
        role: 'dosen',
        name: 'progres-mahasiswa',
        path: '/progres-mahasiswa',
        heading: 'Agenda sempro dan sidang',
    },
    {
        role: 'dosen',
        name: 'mahasiswa-bimbingan-sheet-catatan',
        path: '/dosen/mahasiswa-bimbingan',
        heading: 'Mahasiswa Aktif',
        prepare: openNotesSheet,
    },

    // Kaprodi
    {
        role: 'kaprodi',
        name: 'dashboard',
        path: '/kaprodi/dashboard',
        heading: 'Perlu tindakan',
    },
    {
        role: 'kaprodi',
        name: 'mahasiswa',
        path: '/kaprodi/mahasiswa',
        heading: 'Daftar Mahasiswa',
    },
    {
        role: 'kaprodi',
        name: 'profil-mahasiswa',
        path: '/profil/11',
        heading: /Muhammad Akbar/,
    },
    {
        role: 'kaprodi',
        name: 'sempro-sidang',
        path: '/kaprodi/sempro-sidang',
        heading: 'Monitoring Ujian',
    },
    {
        role: 'kaprodi',
        name: 'dokumen',
        path: '/kaprodi/dokumen',
        heading: 'Dokumen Prodi',
    },
    {
        role: 'kaprodi',
        name: 'dokumen-detail-workspace',
        path: '/kaprodi/dokumen',
        heading: 'Dokumen Prodi',
        prepare: (page) => openDocumentPage(page, '/kaprodi/dokumen/workspace'),
    },
    {
        role: 'kaprodi',
        name: 'dokumen-detail-thesis',
        path: '/kaprodi/dokumen',
        heading: 'Dokumen Prodi',
        prepare: (page) => openDocumentPage(page, '/kaprodi/dokumen/thesis'),
    },
    {
        role: 'kaprodi',
        name: 'dosen-prodi',
        path: '/kaprodi/dosen-prodi',
        heading: 'Daftar Dosen',
    },
    {
        role: 'kaprodi',
        name: 'progres-mahasiswa',
        path: '/progres-mahasiswa',
        heading: 'Agenda sempro dan sidang',
    },

    // Admin (Filament)
    {
        role: 'admin',
        name: 'dashboard',
        path: '/admin',
        heading: 'Perlu tindakan',
    },
    { role: 'admin', name: 'users', path: '/admin/users', heading: 'Users' },
    {
        role: 'admin',
        name: 'users-detail',
        path: '/admin/users/10',
        heading: 'User Account',
    },
    {
        role: 'admin',
        name: 'users-create',
        path: '/admin/users/create',
        heading: 'Create User',
    },
    {
        role: 'admin',
        name: 'thesis-projects',
        path: '/admin/thesis-projects',
        heading: 'Tugas Akhir',
    },
    {
        role: 'admin',
        name: 'thesis-projects-detail',
        path: '/admin/thesis-projects/1',
        heading: 'Status dan Langkah Berikutnya',
    },
    {
        role: 'admin',
        name: 'thesis-projects-dokumen',
        path: '/admin/thesis-projects/1',
        heading: 'Status dan Langkah Berikutnya',
        prepare: async (page) => {
            await page
                .getByRole('tab', { name: 'Dokumen', exact: true })
                .click();
        },
    },
    {
        role: 'admin',
        name: 'thesis-projects-catatan',
        path: '/admin/thesis-projects/1',
        heading: 'Status dan Langkah Berikutnya',
        prepare: async (page) => {
            await page
                .getByRole('tab', { name: 'Catatan', exact: true })
                .click();
        },
    },
    {
        role: 'admin',
        name: 'thesis-projects-catatan-expanded',
        path: '/admin/thesis-projects/1',
        heading: 'Status dan Langkah Berikutnya',
        prepare: async (page) => {
            await page
                .getByRole('tab', { name: 'Catatan', exact: true })
                .click();
            await page
                .locator('details.sita-expandable-note:visible')
                .first()
                .locator('summary')
                .click();
            await expect(
                page.getByRole('region', { name: 'Catatan lengkap' }).first(),
            ).toBeVisible();
            await page.evaluate(() => window.scrollTo(0, 0));
        },
    },
    {
        role: 'admin',
        name: 'system-announcements',
        path: '/admin/system-announcements',
        heading: 'Pengumuman Sistem',
    },
    {
        role: 'admin',
        name: 'csat-responses',
        path: '/admin/csat-responses',
        heading: 'Distribusi Nilai CSAT',
    },
    {
        role: 'admin',
        name: 'student-guides',
        path: '/admin/student-guides',
        heading: 'Panduan Mahasiswa',
    },
    {
        role: 'admin',
        name: 'system-audit-logs',
        path: '/admin/system-audit-logs',
        heading: 'Audit Sistem',
    },
    {
        role: 'admin',
        name: 'thesis-project-events',
        path: '/admin/thesis-project-events',
        heading: 'Audit Tugas Akhir',
    },
    {
        role: 'admin',
        name: 'profile',
        path: '/admin/profile',
        heading: 'Edit profil admin',
    },
    {
        role: 'admin',
        name: 'program-studis',
        path: '/admin/program-studis',
        heading: /Program Studi/,
        account: 'superadmin',
    },
    {
        role: 'admin',
        name: 'faculties',
        path: '/admin/faculties',
        heading: /Fakultas|Faculties/,
        account: 'superadmin',
    },
    {
        role: 'admin',
        name: 'expertise-fields',
        path: '/admin/expertise-fields',
        heading: /Bidang|Expertise/,
        account: 'superadmin',
    },
];
