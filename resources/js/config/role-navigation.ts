import {
    BookOpen,
    CalendarClock,
    ClipboardCheck,
    FileStack,
    FileText,
    Gauge,
    LayoutGrid,
    MessageSquareText,
    Settings,
    ShieldCheck,
    Upload,
    UserCog,
    Users,
    UsersRound,
} from 'lucide-react';

import { AppRole, NavItem } from '@/types';

type RoleNavigation = {
    main: NavItem[];
    settings: NavItem;
};

export const roleNavigationConfig: Record<AppRole, RoleNavigation> = {
    mahasiswa: {
        main: [
            {
                title: 'Dashboard',
                href: '/mahasiswa/dashboard',
                icon: LayoutGrid,
            },
            {
                title: 'Tugas Akhir',
                href: '/mahasiswa/tugas-akhir',
                icon: FileText,
            },
            {
                title: 'Jadwal Bimbingan',
                href: '/mahasiswa/jadwal-bimbingan',
                icon: CalendarClock,
            },
            {
                title: 'Upload Dokumen',
                href: '/mahasiswa/upload-dokumen',
                icon: Upload,
            },
            {
                title: 'Pesan',
                href: '/mahasiswa/pesan',
                icon: MessageSquareText,
            },
            {
                title: 'Panduan',
                href: '/mahasiswa/panduan',
                icon: BookOpen,
            },
        ],
        settings: {
            title: 'Settings',
            href: '/settings',
            icon: Settings,
        },
    },
    dosen: {
        main: [
            {
                title: 'Dashboard',
                href: '/dosen/dashboard',
                icon: LayoutGrid,
            },
            {
                title: 'Sempro & Sidang',
                href: '/dosen/seminar-proposal',
                icon: ClipboardCheck,
            },
            {
                title: 'Mahasiswa Bimbingan',
                href: '/dosen/mahasiswa-bimbingan',
                icon: UsersRound,
            },
            {
                title: 'Jadwal Bimbingan',
                href: '/dosen/jadwal-bimbingan',
                icon: CalendarClock,
            },
            {
                title: 'Dokumen & Revisi',
                href: '/dosen/dokumen-revisi',
                icon: FileStack,
            },
            {
                title: 'Pesan Bimbingan',
                href: '/dosen/pesan-bimbingan',
                icon: MessageSquareText,
            },
        ],
        settings: {
            title: 'Settings',
            href: '/settings',
            icon: Settings,
        },
    },
    admin: {
        main: [
            {
                title: 'Dashboard',
                href: '/admin',
                icon: Gauge,
            },
            {
                title: 'Users',
                href: '/admin/users',
                icon: UserCog,
            },
            {
                title: 'Sempro',
                href: '/admin/sempros',
                icon: ShieldCheck,
            },
            {
                title: 'Judul & Proposal',
                href: '/admin/thesis-submissions',
                icon: Users,
            },
        ],
        settings: {
            title: 'Settings',
            href: '/settings',
            icon: Settings,
        },
    },
    penguji: {
        main: [
            {
                title: 'Dashboard',
                href: '/mahasiswa/dashboard',
                icon: LayoutGrid,
            },
        ],
        settings: {
            title: 'Settings',
            href: '/settings',
            icon: Settings,
        },
    },
};
