import { Head, usePage } from '@inertiajs/react';
import { CircleAlert, CircleCheckBig } from 'lucide-react';

import {
    CardListItem,
    CardListItemFooter,
    CardListItemHeader,
} from '@/components/card-list-item';
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

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-6 lg:gap-8 lg:py-8">
                <Card className="py-0 shadow-sm">
                    <CardHeader className="border-b bg-muted/20 px-6 py-4">
                        <CardTitle className="text-lg font-semibold">
                            Mahasiswa Aktif
                        </CardTitle>
                        <CardDescription>
                            Kapasitas saat ini{' '}
                            <span className="font-semibold text-foreground">
                                {activeCount}
                            </span>{' '}
                            dari{' '}
                            <span className="font-semibold text-foreground">
                                {capacityLimit}
                            </span>{' '}
                            mahasiswa aktif
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4 pb-6">
                        {mahasiswaRows.map((row) => (
                            <CardListItem key={`${row.nim}-${row.advisorType}`}>
                                <CardListItemHeader
                                    title={row.name}
                                    subtitle={`${row.nim} - ${row.advisorType}`}
                                    endContent={
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge
                                                variant="soft"
                                                className="bg-muted font-medium text-muted-foreground hover:bg-muted"
                                            >
                                                Progress {row.progress}%
                                            </Badge>
                                            <Badge
                                                variant="soft"
                                                className={
                                                    row.status ===
                                                    'Siap Seminar'
                                                        ? 'bg-emerald-600/10 font-semibold text-emerald-600 hover:bg-emerald-600/20'
                                                        : 'bg-primary/10 font-semibold text-primary hover:bg-primary/20'
                                                }
                                            >
                                                {row.status}
                                            </Badge>
                                        </div>
                                    }
                                />
                                <CardListItemFooter>
                                    {row.status === 'Siap Seminar' ? (
                                        <CircleCheckBig className="mt-0.5 size-4 text-emerald-600 dark:text-emerald-400" />
                                    ) : (
                                        <CircleAlert className="mt-0.5 size-4 text-amber-500" />
                                    )}
                                    <span>
                                        Terakhir diupdate:{' '}
                                        <span className="font-medium text-foreground">
                                            {row.lastUpdate}
                                        </span>
                                    </span>
                                </CardListItemFooter>
                            </CardListItem>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </DosenLayout>
    );
}
