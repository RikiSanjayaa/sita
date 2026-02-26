import { Head, Link } from '@inertiajs/react';
import {
    BellDot,
    CalendarClock,
    FileStack,
    ShieldAlert,
    UserRoundCog,
} from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem, type SystemActivityEvent } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Aktivitas Sistem', href: '/admin/aktivitas-sistem' },
];

const events: SystemActivityEvent[] = [
    {
        id: 'evt-001',
        type: 'assignment',
        actor: 'Admin Prodi',
        description:
            'Assign pembimbing 1 untuk mahasiswa Intan Permata ke Dr. Ratna Kusuma.',
        timestamp: '9 Feb 2026 08:14',
    },
    {
        id: 'evt-002',
        type: 'jadwal',
        actor: 'Muhammad Akbar',
        description:
            'Mengajukan jadwal bimbingan baru pada 10 Feb 2026 13:00.',
        timestamp: '9 Feb 2026 09:01',
    },
    {
        id: 'evt-003',
        type: 'dokumen',
        actor: 'Nadia Putri',
        description: 'Upload dokumen Proposal_ta_v2.pdf di group bimbingan.',
        timestamp: '9 Feb 2026 09:45',
    },
    {
        id: 'evt-004',
        type: 'chat-escalation',
        actor: 'System',
        description:
            'Thread GRP-102 ditandai eskalasi untuk monitoring admin.',
        timestamp: '9 Feb 2026 10:10',
    },
    {
        id: 'evt-005',
        type: 'status',
        actor: 'Admin Prodi',
        description:
            'Status akademik mahasiswa Rizky Pratama diubah menjadi siap seminar.',
        timestamp: '9 Feb 2026 10:35',
    },
];

function iconByType(type: SystemActivityEvent['type']) {
    if (type === 'assignment') return UserRoundCog;
    if (type === 'jadwal') return CalendarClock;
    if (type === 'dokumen') return FileStack;
    if (type === 'chat-escalation') return BellDot;
    return ShieldAlert;
}

export default function AdminAktivitasSistemPage() {
    return (
        <AdminLayout
            breadcrumbs={breadcrumbs}
            title="Aktivitas Sistem"
            subtitle="Timeline assignment, jadwal, dokumen, dan eskalasi chat"
        >
            <Head title="Aktivitas Sistem Admin" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Timeline Operasional</CardTitle>
                        <CardDescription>
                            Admin hanya melihat metadata chat. Isi percakapan
                            membutuhkan eskalasi.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {events.map((event) => {
                            const Icon = iconByType(event.type);

                            return (
                                <div
                                    key={event.id}
                                    className="rounded-lg border bg-background p-4"
                                >
                                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div className="flex items-start gap-3">
                                            <span className="inline-flex size-8 items-center justify-center rounded-md bg-muted text-muted-foreground">
                                                <Icon className="size-4" />
                                            </span>
                                            <div className="grid gap-1">
                                                <div className="flex items-center gap-2">
                                                    <Badge variant="outline">
                                                        {event.type}
                                                    </Badge>
                                                    <span className="text-xs text-muted-foreground">
                                                        {event.timestamp}
                                                    </span>
                                                </div>
                                                <p className="text-sm">
                                                    {event.description}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    Actor: {event.actor}
                                                </p>
                                            </div>
                                        </div>
                                        {event.type === 'chat-escalation' && (
                                            <Button asChild size="sm">
                                                <Link href="/admin/chat/threads/GRP-102?escalated=1">
                                                    Buka Eskalasi
                                                </Link>
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
