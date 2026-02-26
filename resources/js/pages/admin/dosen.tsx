import { Head } from '@inertiajs/react';
import { CheckCircle2, CircleOff } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Dosen', href: '/admin/dosen' },
];

const dosenRows = [
    {
        nidn: '1234567890',
        name: 'Dr. Budi Santoso',
        homebase: 'Informatika',
        activeCount: 14,
        status: 'Aktif',
    },
    {
        nidn: '1234567891',
        name: 'Dr. Ratna Kusuma',
        homebase: 'Informatika',
        activeCount: 11,
        status: 'Aktif',
    },
    {
        nidn: '1234567892',
        name: 'Dr. Yudi Prasetyo',
        homebase: 'Sistem Informasi',
        activeCount: 0,
        status: 'Cuti',
    },
];

export default function AdminDosenPage() {
    return (
        <AdminLayout
            breadcrumbs={breadcrumbs}
            title="Data Dosen"
            subtitle="Direktori dosen pembimbing dan status kapasitas"
        >
            <Head title="Admin Dosen" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Direktori Dosen</CardTitle>
                        <CardDescription>
                            Data dosen aktif dan status beban pembimbingan
                        </CardDescription>
                        <Input placeholder="Cari nama dosen / NIDN..." />
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {dosenRows.map((row) => (
                            <div
                                key={row.nidn}
                                className="rounded-lg border bg-background p-4"
                            >
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="grid gap-1">
                                        <p className="text-sm font-semibold">
                                            {row.name}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {row.nidn} • {row.homebase}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Badge variant="outline">
                                            Beban {row.activeCount}/14
                                        </Badge>
                                        <Badge
                                            variant={
                                                row.status === 'Aktif'
                                                    ? 'secondary'
                                                    : 'destructive'
                                            }
                                            className={
                                                row.status === 'Aktif'
                                                    ? 'bg-emerald-600 text-white dark:bg-emerald-500'
                                                    : ''
                                            }
                                        >
                                            {row.status}
                                        </Badge>
                                        <Button size="sm" variant="outline">
                                            {row.status === 'Aktif' ? (
                                                <CheckCircle2 className="size-4" />
                                            ) : (
                                                <CircleOff className="size-4" />
                                            )}
                                            Detail
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
