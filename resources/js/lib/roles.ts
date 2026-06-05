import { AppRole } from '@/types';

export const ROLE_LABELS: Record<AppRole, string> = {
    mahasiswa: 'Mahasiswa',
    dosen: 'Dosen',
    kaprodi: 'Kaprodi',
    admin: 'Admin',
    penguji: 'Penguji',
};

export const ROLE_PORTAL_LABELS: Record<AppRole, string> = {
    mahasiswa: 'Portal Mahasiswa',
    dosen: 'Portal Dosen',
    kaprodi: 'Portal Kaprodi',
    admin: 'Portal Admin',
    penguji: 'Portal Penguji',
};

export const ROLE_DASHBOARD_PATHS: Record<AppRole, string> = {
    mahasiswa: '/mahasiswa/dashboard',
    dosen: '/dosen/dashboard',
    kaprodi: '/kaprodi/dashboard',
    admin: '/admin',
    penguji: '/mahasiswa/dashboard',
};

export const UI_ROLES: AppRole[] = ['mahasiswa', 'dosen', 'kaprodi', 'admin'];
