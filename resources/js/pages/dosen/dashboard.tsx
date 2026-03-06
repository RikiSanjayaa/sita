import { Head, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    FileStack,
    MessageSquareText,
    UserRound,
} from 'lucide-react';

import { EmptyState } from '@/components/empty-state';
import { StatCard } from '@/components/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import DosenLayout from '@/layouts/dosen-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dosen/dashboard' },
];

type QueueCard = {
    title: string;
    value: string;
    description: string;
};

type QueueItem = {
    id: number;
    mahasiswa: string;
    task: string;
    time: string;
    priority: 'Tinggi' | 'Normal';
    status: string;
};

type DashboardProps = {
    queueCards: QueueCard[];
    todayQueue: QueueItem[];
};

const queueCardIcons = {
    'Jadwal Pending': CalendarClock,
    'Revisi Belum Dicek': FileStack,
    'Pesan Belum Dibaca': MessageSquareText,
    'Mahasiswa Aktif': UserRound,
} as const;

export default function DosenDashboardPage() {
    const { queueCards, todayQueue } = usePage<SharedData & DashboardProps>()
        .props;

    return (
        <DosenLayout
            breadcrumbs={breadcrumbs}
            title="Dashboard Dosen"
            subtitle="Ringkasan antrian bimbingan mahasiswa"
        >
            <Head title="Dashboard Dosen" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 md:px-6 lg:gap-8 lg:py-8">
                <div className="grid gap-4 md:grid-cols-2 lg:gap-6 xl:grid-cols-4">
                    {queueCards.map((card) => {
                        const Icon =
                            queueCardIcons[
                            card.title as keyof typeof queueCardIcons
                            ] ?? CalendarClock;

                        return (
                            <StatCard
                                key={card.title}
                                title={card.title}
                                value={card.value}
                                description={card.description}
                                icon={Icon}
                            />
                        );
                    })}
                </div>

                <Card className="shadow-sm">
                    <CardHeader className="border-b bg-muted/20 px-6 py-4">
                        <CardTitle className="text-lg font-semibold">
                            Antrian Hari Ini
                        </CardTitle>
                        <CardDescription>
                            Tugas prioritas yang perlu ditindaklanjuti
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 p-6">
                        {todayQueue.length > 0 ? (
                            todayQueue.map((item) => (
                                <div
                                    key={`${item.mahasiswa}-${item.task}`}
                                    className="flex flex-col gap-4 rounded-xl border bg-background p-5 shadow-sm transition-shadow hover:shadow-md sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="grid gap-1.5">
                                        <p className="text-base font-semibold">
                                            {item.mahasiswa}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {item.task}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2 sm:shrink-0">
                                        <Badge
                                            variant="soft"
                                            className="bg-muted text-muted-foreground hover:bg-muted"
                                        >
                                            {item.time}
                                        </Badge>
                                        <Badge
                                            variant={
                                                item.priority === 'Tinggi'
                                                    ? 'destructive'
                                                    : 'soft'
                                            }
                                            className={
                                                item.priority === 'Tinggi'
                                                    ? 'bg-destructive/10 text-destructive hover:bg-destructive/20'
                                                    : ''
                                            }
                                        >
                                            {item.priority}
                                        </Badge>
                                        <Button
                                            variant="soft"
                                            size="sm"
                                            className="ml-2 font-semibold"
                                        >
                                            Buka
                                        </Button>
                                    </div>
                                </div>
                            ))
                        ) : (
                            <EmptyState
                                icon={CalendarClock}
                                title="Bagus! Tidak ada antrian"
                                description="Semua tugas hari ini sudah diselesaikan."
                                className="flex flex-col items-center justify-center py-12"
                            />
                        )}
                    </CardContent>
                </Card>
            </div>
        </DosenLayout>
    );
}
