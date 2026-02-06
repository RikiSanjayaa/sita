import { Head, usePage } from '@inertiajs/react';
import { CircleAlert, CircleCheckBig } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
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
    { title: 'Mahasiswa Bimbingan', href: '/dosen/mahasiswa-bimbingan' },
];

type MahasiswaRow = {
    nim: string;
    name: string;
    advisorType: string;
    progress: number;
    status: string;
    lastUpdate: string;
};

type MahasiswaBimbinganProps = {
    mahasiswaRows: MahasiswaRow[];
    activeCount: number;
    capacityLimit: number;
};

export default function DosenMahasiswaBimbinganPage() {
    const { mahasiswaRows, activeCount, capacityLimit } = usePage<
        SharedData & MahasiswaBimbinganProps
    >().props;

    return (
        <DosenLayout
            breadcrumbs={breadcrumbs}
            title="Mahasiswa Bimbingan"
            subtitle="Daftar mahasiswa yang saat ini dibimbing"
        >
            <Head title="Mahasiswa Bimbingan" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Mahasiswa Aktif</CardTitle>
                        <CardDescription>
                            Kapasitas saat ini {activeCount} dari{' '}
                            {capacityLimit} mahasiswa aktif
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {mahasiswaRows.map((row) => (
                            <div
                                key={`${row.nim}-${row.advisorType}`}
                                className="rounded-lg border bg-background p-4"
                            >
                                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                    <div>
                                        <p className="text-sm font-semibold">
                                            {row.name}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {row.nim} - {row.advisorType}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Badge variant="outline">
                                            Progress {row.progress}%
                                        </Badge>
                                        <Badge
                                            className={
                                                row.status === 'Siap Seminar'
                                                    ? 'bg-emerald-600 text-white dark:bg-emerald-500'
                                                    : ''
                                            }
                                            variant={
                                                row.status === 'Siap Seminar'
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {row.status}
                                        </Badge>
                                    </div>
                                </div>
                                <div className="mt-3 flex items-start gap-2 text-sm text-muted-foreground">
                                    {row.status === 'Siap Seminar' ? (
                                        <CircleCheckBig className="mt-0.5 size-4 text-emerald-600 dark:text-emerald-400" />
                                    ) : (
                                        <CircleAlert className="mt-0.5 size-4 text-amber-500" />
                                    )}
                                    <span>{row.lastUpdate}</span>
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </DosenLayout>
    );
}
