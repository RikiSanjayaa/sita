import path from 'node:path';

import { playwrightAuthPath } from './db';

export type PlaywrightAccountKey =
    | 'admin'
    | 'dosen1'
    | 'dosen2'
    | 'dosen3'
    | 'dosen4'
    | 'dosen5'
    | 'dosen6'
    | 'mahasiswa'
    | 'akbar'
    | 'nadia'
    | 'rizky'
    | 'siti'
    | 'bagas'
    | 'farhan'
    | 'putra';

type PlaywrightAccount = {
    email: string;
    password: string;
    loginPath: string;
    landingPath: string;
};

export const playwrightAccounts: Record<
    PlaywrightAccountKey,
    PlaywrightAccount
> = {
    admin: {
        email: 'admin@sita.test',
        password: 'password',
        loginPath: '/admin/login',
        landingPath: '/admin',
    },
    dosen1: {
        email: 'dosen@sita.test',
        password: 'password',
        loginPath: '/login',
        landingPath: '/dosen/dashboard',
    },
    dosen2: {
        email: 'dosen2@sita.test',
        password: 'password',
        loginPath: '/login',
        landingPath: '/dosen/dashboard',
    },
    dosen3: {
        email: 'dosen3@sita.test',
        password: 'password',
        loginPath: '/login',
        landingPath: '/dosen/dashboard',
    },
    dosen4: {
        email: 'dosen4@sita.test',
        password: 'password',
        loginPath: '/login',
        landingPath: '/dosen/dashboard',
    },
    dosen5: {
        email: 'dosen5@sita.test',
        password: 'password',
        loginPath: '/login',
        landingPath: '/dosen/dashboard',
    },
    dosen6: {
        email: 'dosen6@sita.test',
        password: 'password',
        loginPath: '/login',
        landingPath: '/dosen/dashboard',
    },
    mahasiswa: {
        email: 'mahasiswa@sita.test',
        password: 'password',
        loginPath: '/login',
        landingPath: '/mahasiswa/dashboard',
    },
    akbar: {
        email: 'akbar@sita.test',
        password: 'password',
        loginPath: '/login',
        landingPath: '/mahasiswa/dashboard',
    },
    nadia: {
        email: 'nadia@sita.test',
        password: 'password',
        loginPath: '/login',
        landingPath: '/mahasiswa/dashboard',
    },
    rizky: {
        email: 'rizky@sita.test',
        password: 'password',
        loginPath: '/login',
        landingPath: '/mahasiswa/dashboard',
    },
    siti: {
        email: 'siti@sita.test',
        password: 'password',
        loginPath: '/login',
        landingPath: '/mahasiswa/dashboard',
    },
    bagas: {
        email: 'bagas@sita.test',
        password: 'password',
        loginPath: '/login',
        landingPath: '/mahasiswa/dashboard',
    },
    farhan: {
        email: 'farhan@sita.test',
        password: 'password',
        loginPath: '/login',
        landingPath: '/mahasiswa/dashboard',
    },
    putra: {
        email: 'putra@sita.test',
        password: 'password',
        loginPath: '/login',
        landingPath: '/mahasiswa/dashboard',
    },
};

export const thesisScenarios = {
    titleReviewPending: {
        studentKey: 'rizky' as const,
        studentName: 'Rizky Pratama',
        currentTitle: 'Implementasi Blockchain untuk Sistem Voting Digital',
    },
    semproScheduled: {
        studentKey: 'akbar' as const,
        studentName: 'Muhammad Akbar',
        currentTitle:
            'Analisis Sentimen Media Sosial Menggunakan Deep Learning',
    },
    semproRevision: {
        studentKey: 'nadia' as const,
        studentName: 'Nadia Putri',
        currentTitle: 'Pengembangan Aplikasi E-Learning Berbasis Gamifikasi',
    },
    semproPassedWithoutSupervisors: {
        studentKey: 'siti' as const,
        studentName: 'Siti Aminah',
        currentTitle:
            'Sistem Deteksi Intrusi Jaringan Menggunakan Machine Learning',
    },
    semproRetry: {
        studentKey: 'bagas' as const,
        studentName: 'Bagas Saputra',
        currentTitle: 'Platform Asesmen Otomatis Kualitas Proposal Skripsi',
    },
    firstSubmission: {
        studentKey: 'farhan' as const,
        studentName: 'Farhan Maulana',
    },
    sidangScheduled: {
        studentKey: 'putra' as const,
        studentName: 'Putra Mahendra',
        currentTitle:
            'Deteksi Dini Risiko Dropout Mahasiswa Menggunakan Pembelajaran Mesin',
    },
    activeResearch: {
        studentKey: 'mahasiswa' as const,
        studentName: 'Mahasiswa SiTA',
        currentTitle:
            'Sistem Rekomendasi Topik Bimbingan Berbasis Riwayat Interaksi',
    },
};

export const thesisFixtures = {
    adminProjectsPath: '/admin/thesis-projects',
    thesisDocumentUploadPath: path.join(
        'tests',
        'fixtures',
        'panduan-template.pdf',
    ),
};

export function authStatePath(key: PlaywrightAccountKey): string {
    return path.join(playwrightAuthPath, `${key}.json`);
}
