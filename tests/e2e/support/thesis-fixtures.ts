import path from 'node:path';

import { playwrightAuthPath } from './db';

export type PlaywrightAccountKey =
    | 'superadmin'
    | 'admin'
    | 'kaprodi'
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
    | 'putra'
    | 'kirana'
    | 'dimas'
    | 'laras'
    | 'raka'
    | 'sinta'
    | 'wulan'
    | 'masa-aktif-habis'
    | 'lulus-tenggang'
    | 'lulus-lama';

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
    superadmin: {
        email: 'superadmin@sita.test',
        password: 'password',
        loginPath: '/login',
        landingPath: '/dashboard',
    },
    admin: {
        email: 'admin@sita.test',
        password: 'password',
        loginPath: '/admin',
        landingPath: '/admin',
    },
    kaprodi: {
        email: 'kaprodi@sita.test',
        password: 'password',
        loginPath: '/login',
        landingPath: '/kaprodi/dashboard',
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
    kirana: {
        email: 'e2e-pembimbing@sita.test',
        password: 'password',
        loginPath: '/login',
        landingPath: '/mahasiswa/dashboard',
    },
    dimas: {
        email: 'e2e-sempro@sita.test',
        password: 'password',
        loginPath: '/login',
        landingPath: '/mahasiswa/dashboard',
    },
    'masa-aktif-habis': {
        email: 'e2e-masa-aktif-habis@sita.test',
        password: 'password',
        loginPath: '/login',
        landingPath: '/mahasiswa/dashboard',
    },
    laras: {
        email: 'e2e-jadwalsempro@sita.test',
        password: 'password',
        loginPath: '/login',
        landingPath: '/mahasiswa/dashboard',
    },
    raka: {
        email: 'e2e-pembimbingbaru@sita.test',
        password: 'password',
        loginPath: '/login',
        landingPath: '/mahasiswa/dashboard',
    },
    sinta: {
        email: 'e2e-sidang@sita.test',
        password: 'password',
        loginPath: '/login',
        landingPath: '/mahasiswa/dashboard',
    },
    wulan: {
        email: 'e2e-sinopsis@sita.test',
        password: 'password',
        loginPath: '/login',
        landingPath: '/mahasiswa/dashboard',
    },
    'lulus-tenggang': {
        email: 'e2e-lulus-tenggang@sita.test',
        password: 'password',
        loginPath: '/login',
        landingPath: '/mahasiswa/dashboard',
    },
    'lulus-lama': {
        email: 'e2e-lulus-lama@sita.test',
        password: 'password',
        loginPath: '/login',
        landingPath: '/mahasiswa/dashboard',
    },
    phonePrompt: {
        email: 'e2e-popup@sita.test',
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
    // Dedicated students from PlaywrightScenarioSeeder (database/seeders).
    // Specs reset them with seedPlaywrightScenarios() in beforeAll, so they do
    // not depend on what earlier specs did to the shared seeded students.
    twoSupervisors: {
        studentKey: 'kirana' as const,
        studentName: 'Kirana Dua Pembimbing',
        currentTitle: 'Sistem Peringatan Dini Banjir Berbasis Sensor IoT',
        supervisorKeys: ['dosen1', 'dosen2'] as const,
        supervisorNames: [
            'Dr. Budi Santoso, M.Kom.',
            'Dr. Ratna Kusuma, M.Kom.',
        ],
    },
    semproTwoExaminers: {
        studentKey: 'dimas' as const,
        studentName: 'Dimas Penguji Sempro',
        currentTitle:
            'Klasifikasi Citra Daun Padi Menggunakan Jaringan Konvolusi',
        examinerKeys: ['dosen3', 'dosen4'] as const,
        location: 'Ruang Seminar E2E',
    },
    expiredTicket: {
        studentKey: 'masa-aktif-habis' as const,
        studentName: 'Tiara Masa Aktif Habis',
        currentTitle: 'Pengaruh Pembelajaran Adaptif Terhadap Motivasi Belajar',
        supervisorKeys: ['dosen1'] as const,
    },
    completedInGrace: {
        studentKey: 'lulus-tenggang' as const,
        studentName: 'Anindya Lulus Masa Tenggang',
        currentTitle:
            'Analisis Kepuasan Pengguna Aplikasi Perpustakaan Digital',
        supervisorKeys: ['dosen1'] as const,
    },
    completedPastGrace: {
        studentKey: 'lulus-lama' as const,
        studentName: 'Arif Lulus Siap Arsip',
        currentTitle: 'Perancangan Sistem Informasi Inventaris Laboratorium',
        supervisorKeys: ['dosen1'] as const,
    },
    sinopsisObe: {
        studentKey: 'wulan' as const,
        studentName: 'Wulan Ujian Sinopsis',
        currentTitle: 'Deteksi Hoaks Berita Daring Dengan Model Bahasa',
        examinerKeys: ['dosen3', 'dosen4'] as const,
        location: 'Ruang Sinopsis E2E',
    },
    semproUnscheduled: {
        studentKey: 'laras' as const,
        studentName: 'Laras Siap Sempro',
        currentTitle: 'Aplikasi Pemantauan Kualitas Udara Dalam Ruangan',
    },
    passedWithoutSupervisors: {
        studentKey: 'raka' as const,
        studentName: 'Raka Belum Pembimbing',
        currentTitle:
            'Optimasi Rute Pengiriman Logistik Dengan Algoritma Genetika',
    },
    sidangWithPanel: {
        studentKey: 'sinta' as const,
        studentName: 'Sinta Sidang Terjadwal',
        currentTitle: 'Prediksi Kelulusan Tepat Waktu Dengan Pohon Keputusan',
        panelKeys: ['dosen1', 'dosen2', 'dosen3'] as const,
        location: 'Ruang Sidang E2E',
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
