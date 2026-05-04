import {
    BookOpen,
    CalendarCheck,
    CalendarClock,
    FileText,
    Gauge,
    GraduationCap,
    type LucideIcon,
    UserCheck,
    Users,
} from 'lucide-react';

import { PersonCardLink } from '@/components/profile/person-card-link';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { type ProfileFieldItem, type UserProfileDetail } from '@/types';

// ---------------------------------------------------------------------------
// Stat icon + color config, keyed by label from the backend
// ---------------------------------------------------------------------------

type StatConfig = {
    icon: LucideIcon;
    /** className applied to the icon wrapper square */
    wrapperClass: string;
    /** className applied to the icon itself */
    iconClass: string;
};

const STAT_CONFIGS: Record<string, StatConfig> = {
    'Status Skripsi': {
        icon: BookOpen,
        wrapperClass: 'bg-primary/10',
        iconClass: 'text-primary',
    },
    'Pembimbing Aktif': {
        icon: GraduationCap,
        wrapperClass: 'bg-primary/10',
        iconClass: 'text-primary',
    },
    'Penguji Aktif': {
        icon: UserCheck,
        wrapperClass: 'bg-primary/10',
        iconClass: 'text-primary',
    },
    'Mahasiswa Aktif': {
        icon: Users,
        wrapperClass: 'bg-primary/10',
        iconClass: 'text-primary',
    },
    'Sisa Kuota': {
        icon: Gauge,
        wrapperClass: 'bg-primary/10',
        iconClass: 'text-primary',
    },
    'Sempro Terjadwal': {
        icon: CalendarClock,
        wrapperClass: 'bg-primary/10',
        iconClass: 'text-primary',
    },
    'Sidang Terjadwal': {
        icon: CalendarCheck,
        wrapperClass: 'bg-primary/10',
        iconClass: 'text-primary',
    },
};

const DEFAULT_STAT_CONFIG: StatConfig = {
    icon: FileText,
    wrapperClass: 'bg-primary/10',
    iconClass: 'text-primary',
};

// ---------------------------------------------------------------------------

function StatItem({ label, value }: ProfileFieldItem) {
    const {
        icon: Icon,
        wrapperClass,
        iconClass,
    } = STAT_CONFIGS[label] ?? DEFAULT_STAT_CONFIG;

    return (
        <div className="flex items-center gap-3">
            <div
                className={cn(
                    'flex size-10 shrink-0 items-center justify-center rounded-lg',
                    wrapperClass,
                )}
            >
                <Icon aria-hidden="true" className={cn('size-5', iconClass)} />
            </div>
            <div className="min-w-0">
                <p className="text-[10px] font-semibold tracking-widest text-muted-foreground uppercase">
                    {label}
                </p>
                <p className="mt-0.5 text-sm font-bold text-foreground">
                    {value}
                </p>
            </div>
        </div>
    );
}

function SectionHeader({
    title,
    description,
    icon: Icon,
}: {
    title: string;
    description?: string;
    icon?: LucideIcon;
}) {
    return (
        <div className="space-y-1">
            <h2 className="flex items-center gap-2 text-lg font-semibold text-balance">
                {Icon ? (
                    <Icon aria-hidden="true" className="size-5 shrink-0" />
                ) : null}
                {title}
            </h2>
            {description ? (
                <p className="text-sm text-muted-foreground">{description}</p>
            ) : null}
        </div>
    );
}

export function ProfileDetailsSections({
    profile,
}: {
    profile: UserProfileDetail;
}) {
    return (
        <div className="space-y-8">
            {/* ── Detail Akademik & Peran ─────────────────────────────── */}
            <div className="overflow-hidden rounded-xl border">
                {/* Header */}
                <div className="px-6 py-5">
                    <SectionHeader
                        title="Detail Akademik & Peran"
                        description="Ringkasan identitas dan status bimbingan Anda saat ini."
                    />
                </div>

                <Separator />

                {/* Meta grid */}
                <div className="grid gap-x-8 gap-y-5 px-6 py-5 sm:grid-cols-2 xl:grid-cols-3">
                    {profile.meta.map((item) => (
                        <div key={item.label}>
                            <p className="text-[10px] font-semibold tracking-widest text-muted-foreground uppercase">
                                {item.label}
                            </p>
                            <p className="mt-1 text-sm font-semibold text-foreground">
                                {item.value}
                            </p>
                        </div>
                    ))}
                </div>

                {/* Stats row — only if present */}
                {profile.stats.length > 0 ? (
                    <>
                        <Separator />
                        <div className="grid gap-5 px-6 py-5 sm:grid-cols-2 lg:grid-cols-3">
                            {profile.stats.map((item) => (
                                <StatItem
                                    key={item.label}
                                    label={item.label}
                                    value={item.value}
                                />
                            ))}
                        </div>
                    </>
                ) : null}
            </div>

            {/* ── Tugas Akhir ─────────────────────────────────────────── */}
            {profile.thesis ? (
                <div className="overflow-hidden rounded-xl border">
                    {/* Header */}
                    <div className="px-6 py-5">
                        <SectionHeader
                            title="Tugas Akhir"
                            description="Status terkini dan dosen yang sedang terlibat."
                            icon={FileText}
                        />
                    </div>

                    <Separator />

                    <div className="space-y-6 px-6 py-5">
                        {/* Thesis title block */}
                        <div>
                            <p className="text-[10px] font-semibold tracking-widest text-muted-foreground uppercase">
                                Judul Saat Ini
                            </p>
                            <p className="mt-1 text-sm font-semibold text-foreground">
                                {profile.thesis.title ??
                                    'Belum ada judul aktif'}
                            </p>
                            <Badge variant="secondary" className="mt-2">
                                Status: {profile.thesis.statusLabel}
                            </Badge>
                        </div>

                        {/* Advisors & examiners */}
                        <div className="grid gap-6 lg:grid-cols-2">
                            <div className="space-y-3">
                                <div className="flex items-center gap-2 text-sm font-semibold text-foreground">
                                    <GraduationCap
                                        aria-hidden="true"
                                        className="size-4 shrink-0"
                                    />
                                    Dosen Pembimbing
                                </div>
                                {profile.thesis.advisors.length > 0 ? (
                                    <div className="grid gap-3">
                                        {profile.thesis.advisors.map(
                                            (person, index) => (
                                                <PersonCardLink
                                                    key={person.id}
                                                    person={person}
                                                    label={`Pembimbing ${index + 1}`}
                                                />
                                            ),
                                        )}
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        Belum ada pembimbing aktif.
                                    </p>
                                )}
                            </div>

                            <div className="space-y-3">
                                <div className="flex items-center gap-2 text-sm font-semibold text-foreground">
                                    <Users
                                        aria-hidden="true"
                                        className="size-4 shrink-0"
                                    />
                                    Dosen Penguji
                                </div>
                                {profile.thesis.examiners.length > 0 ? (
                                    <div className="grid gap-3">
                                        {profile.thesis.examiners.map(
                                            (person, index) => (
                                                <PersonCardLink
                                                    key={`${person.id}-${index}`}
                                                    person={person}
                                                    label={`Penguji ${index + 1}`}
                                                />
                                            ),
                                        )}
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        Belum ada penguji aktif.
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            ) : null}

            {/* ── Related user groups ─────────────────────────────────── */}
            {profile.relatedUsers.map((group) => (
                <div
                    key={group.title}
                    className="overflow-hidden rounded-xl border"
                >
                    <div className="px-6 py-5">
                        <SectionHeader title={group.title} />
                    </div>

                    <Separator />

                    <div className="px-6 py-5">
                        {group.users.length > 0 ? (
                            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                {group.users.map((person) => (
                                    <PersonCardLink
                                        key={person.id}
                                        person={person}
                                    />
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                {group.emptyMessage}
                            </p>
                        )}
                    </div>
                </div>
            ))}
        </div>
    );
}
