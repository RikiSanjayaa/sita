import {
    BookOpen,
    CalendarClock,
    ClipboardCheck,
    FileStack,
    FileText,
    Gauge,
    LayoutGrid,
    ListChecks,
    MessageSquareText,
    Settings,
    ShieldCheck,
    Upload,
    UserCog,
    Users,
    UsersRound,
} from 'lucide-react';

import { neutralAcademicTerminology as n } from '@/lib/academic-terminology';
import { type AppRole, type NavItem } from '@/types';

type NavSection = {
    label: string;
    items: NavItem[];
};

type RoleNavigation = {
    main: NavItem[];
    /** Label for `main`. Defaults to "Menu utama". */
    mainLabel?: string;
    /** Extra labelled groups rendered after `main`. */
    sections?: NavSection[];
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
                title: 'Progres Mahasiswa',
                href: '/progres-mahasiswa',
                icon: ListChecks,
            },
            {
                title: n.finalWork,
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
            title: 'Pengaturan Akun',
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
                title: `${n.proposalExamShort} & ${n.finalExam}`,
                href: '/dosen/seminar-proposal',
                icon: ClipboardCheck,
            },
            {
                title: 'Mahasiswa Dosen',
                href: '/dosen/mahasiswa-bimbingan',
                icon: UsersRound,
            },
            {
                title: 'Progres Mahasiswa',
                href: '/progres-mahasiswa',
                icon: ListChecks,
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
                title: 'Pesan',
                href: '/dosen/pesan',
                icon: MessageSquareText,
            },
        ],
        settings: {
            title: 'Pengaturan Akun',
            href: '/settings',
            icon: Settings,
        },
    },
    kaprodi: {
        mainLabel: 'Kaprodi',
        main: [
            {
                title: 'Dashboard',
                href: '/kaprodi/dashboard',
                icon: LayoutGrid,
            },
            {
                title: 'Mahasiswa Prodi',
                href: '/kaprodi/mahasiswa',
                icon: UsersRound,
            },
            {
                title: 'Progres Mahasiswa',
                href: '/progres-mahasiswa',
                icon: ListChecks,
            },
            {
                title: `${n.proposalExamShort} & ${n.finalExam}`,
                href: '/kaprodi/sempro-sidang',
                icon: ClipboardCheck,
            },
            {
                title: 'Dokumen Prodi',
                href: '/kaprodi/dokumen',
                icon: FileStack,
            },
            {
                title: 'Dosen Prodi',
                href: '/kaprodi/dosen-prodi',
                icon: UserCog,
            },
        ],
        sections: [
            {
                label: 'Bimbingan Saya',
                items: [
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
                        title: 'Penilaian Ujian',
                        href: '/dosen/seminar-proposal',
                        icon: ClipboardCheck,
                    },
                    {
                        title: 'Pesan',
                        href: '/dosen/pesan',
                        icon: MessageSquareText,
                    },
                ],
            },
        ],
        settings: {
            title: 'Pengaturan Akun',
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
                title: 'Progres Mahasiswa',
                href: '/progres-mahasiswa',
                icon: ListChecks,
            },
            {
                title: 'Users',
                href: '/admin/users',
                icon: UserCog,
            },
            {
                title: n.proposalExamShort,
                href: '/admin/sempros',
                icon: ShieldCheck,
            },
            {
                title: `Judul & ${n.proposalDocument}`,
                href: '/admin/thesis-submissions',
                icon: Users,
            },
        ],
        settings: {
            title: 'Pengaturan Akun',
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
            {
                title: 'Progres Mahasiswa',
                href: '/progres-mahasiswa',
                icon: ListChecks,
            },
        ],
        settings: {
            title: 'Pengaturan Akun',
            href: '/settings',
            icon: Settings,
        },
    },
};
