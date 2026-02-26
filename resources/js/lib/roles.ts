import { AppRole } from '@/types';

export const ROLE_LABELS: Record<AppRole, string> = {
    mahasiswa: 'Mahasiswa',
    dosen: 'Dosen',
    admin: 'Admin',
    penguji: 'Penguji',
};

export const ROLE_PORTAL_LABELS: Record<AppRole, string> = {
    mahasiswa: 'Portal Mahasiswa',
    dosen: 'Portal Dosen',
    admin: 'Portal Admin',
    penguji: 'Portal Penguji',
};

export const ROLE_DASHBOARD_PATHS: Record<AppRole, string> = {
    mahasiswa: '/mahasiswa/dashboard',
    dosen: '/dosen/dashboard',
    admin: '/admin/dashboard',
    penguji: '/mahasiswa/dashboard',
};

export const UI_ROLES: AppRole[] = ['mahasiswa', 'dosen', 'admin'];
