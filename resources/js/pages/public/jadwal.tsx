import { usePage } from '@inertiajs/react';

import { PublicLayout } from '@/components/public/public-layout';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { type SharedData } from '@/types';

type ScheduleItem = {
    id: number;
    type: 'sempro' | 'sidang';
    typeLabel: string;
    studentName: string;
    studentNim: string;
    programStudi: string;
    title: string;
    scheduledFor: string | null;
    location: string;
    mode: string;
    statusLabel: string;
    statusTone: 'default' | 'warning' | 'danger' | 'muted';
    statusDetail: string | null;
};

type PageProps = {
    upcomingSchedules: ScheduleItem[];
    followUpSchedules: ScheduleItem[];
};

const statusClassName: Record<ScheduleItem['statusTone'], string> = {
    default: 'bg-primary/10 text-primary hover:bg-primary/20',
    warning:
        'bg-amber-600/10 text-amber-700 hover:bg-amber-600/20 dark:text-amber-400',
    danger: 'bg-destructive/10 text-destructive hover:bg-destructive/20',
    muted: 'bg-muted text-muted-foreground hover:bg-muted',
};

function ScheduleTable({
    items,
    title,
}: {
    items: ScheduleItem[];
    title: string;
}) {
    return (
        <Card className="shadow-sm">
            <CardHeader>
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent>
                {items.length > 0 ? (
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full min-w-[760px] text-sm">
                            <thead className="bg-muted/30 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-semibold">
                                        Jenis
                                    </th>
                                    <th className="px-4 py-3 font-semibold">
                                        Waktu
                                    </th>
                                    <th className="px-4 py-3 font-semibold">
                                        Mahasiswa
                                    </th>
                                    <th className="px-4 py-3 font-semibold">
                                        Prodi
                                    </th>
                                    <th className="px-4 py-3 font-semibold">
                                        Judul
                                    </th>
                                    <th className="px-4 py-3 font-semibold">
                                        Lokasi
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.map((item) => (
                                    <tr
                                        key={`${title}-${item.id}`}
                                        className="border-t align-top"
                                    >
                                        <td className="px-4 py-3">
                                            <div className="flex flex-col gap-2">
                                                <Badge
                                                    className={
                                                        item.type === 'sempro'
                                                            ? 'bg-primary text-primary-foreground hover:bg-primary/90'
                                                            : 'bg-emerald-600 text-white hover:bg-emerald-600/90'
                                                    }
                                                >
                                                    {item.typeLabel}
                                                </Badge>
                                                <Badge
                                                    variant="soft"
                                                    className={
                                                        statusClassName[
                                                            item.statusTone
                                                        ]
                                                    }
                                                >
                                                    {item.statusLabel}
                                                </Badge>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {item.scheduledFor ?? '-'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="font-medium text-foreground">
                                                {item.studentName}
                                            </div>
                                            <div className="text-muted-foreground">
                                                {item.studentNim}
                                            </div>
                                            {item.statusDetail ? (
                                                <div className="mt-1 max-w-xs text-xs leading-5 text-muted-foreground">
                                                    {item.statusDetail}
                                                </div>
                                            ) : null}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {item.programStudi}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="max-w-md leading-6 font-medium text-foreground">
                                                {item.title}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {item.location} ({item.mode})
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="rounded-xl border border-dashed bg-muted/20 p-6 text-center text-sm text-muted-foreground">
                        Belum ada data pada bagian ini.
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

export default function PublicSchedulesPage() {
    const { upcomingSchedules, followUpSchedules } = usePage<
        SharedData & PageProps
    >().props;

    return (
        <PublicLayout
            active="jadwal"
            headTitle="Jadwal Sempro dan Sidang"
            pageTitle="Jadwal Sempro dan Sidang"
            description="Agenda publik yang akan datang, serta seminar terbaru yang masih memerlukan tindak lanjut seperti revisi atau pelengkapan nilai."
        >
            <div className="grid gap-6">
                <ScheduleTable
                    title="Jadwal Akan Datang"
                    items={upcomingSchedules}
                />
                <ScheduleTable
                    title="Tindak Lanjut Terbaru"
                    items={followUpSchedules}
                />
            </div>
        </PublicLayout>
    );
}
