import { Head, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    FileStack,
    MessageSquareText,
    UserRound,
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

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-6">
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {queueCards.map((card) => {
                        const Icon =
                            queueCardIcons[
                                card.title as keyof typeof queueCardIcons
                            ] ?? CalendarClock;

                        return (
                            <Card key={card.title}>
                                <CardHeader className="pb-2">
                                    <CardDescription>
                                        {card.title}
                                    </CardDescription>
                                    <CardTitle className="text-2xl">
                                        {card.value}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="flex items-start gap-3">
                                    <span className="inline-flex size-9 items-center justify-center rounded-md bg-muted text-muted-foreground">
                                        <Icon className="size-4" />
                                    </span>
                                    <p className="text-sm text-muted-foreground">
                                        {card.description}
                                    </p>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Antrian Hari Ini</CardTitle>
                        <CardDescription>
                            Tugas prioritas yang perlu ditindaklanjuti
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        {todayQueue.map((item) => (
                            <div
                                key={`${item.mahasiswa}-${item.task}`}
                                className="flex flex-col gap-3 rounded-lg border bg-background p-4 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div className="grid gap-1">
                                    <p className="text-sm font-semibold">
                                        {item.mahasiswa}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {item.task}
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge variant="secondary">
                                        {item.time}
                                    </Badge>
                                    <Badge
                                        variant={
                                            item.priority === 'Tinggi'
                                                ? 'destructive'
                                                : 'outline'
                                        }
                                    >
                                        {item.priority}
                                    </Badge>
                                    <Button variant="outline" size="sm">
                                        Buka
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </DosenLayout>
    );
}
