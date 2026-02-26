import { Head } from '@inertiajs/react';

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
    { title: 'Mahasiswa', href: '/admin/mahasiswa' },
];

const mahasiswaRows = [
    {
        nim: '2210510001',
        name: 'Muhammad Akbar',
        program: 'Informatika',
        pembimbing1: 'Dr. Budi Santoso',
        pembimbing2: 'Dr. Ratna Kusuma',
        progress: 'Bimbingan (68%)',
    },
    {
        nim: '2210510011',
        name: 'Rizky Pratama',
        program: 'Informatika',
        pembimbing1: 'Dr. Hendra Wijaya',
        pembimbing2: 'Dr. Lia Permana',
        progress: 'Siap Seminar',
    },
    {
        nim: '2210510031',
        name: 'Intan Permata',
        program: 'Sistem Informasi',
        pembimbing1: null,
        pembimbing2: null,
        progress: 'Menunggu Assignment',
    },
];

export default function AdminMahasiswaPage() {
    return (
        <AdminLayout
            breadcrumbs={breadcrumbs}
            title="Data Mahasiswa"
            subtitle="Pantau assignment pembimbing dan progres tugas akhir"
        >
            <Head title="Admin Mahasiswa" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Mahasiswa</CardTitle>
                        <CardDescription>
                            Placeholder data mahasiswa lintas prodi
                        </CardDescription>
                        <Input placeholder="Cari NIM / nama / program studi..." />
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {mahasiswaRows.map((row) => (
                            <div
                                key={row.nim}
                                className="rounded-lg border bg-background p-4"
                            >
                                <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div className="grid gap-1">
                                        <p className="text-sm font-semibold">
                                            {row.name}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {row.nim} • {row.program}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            P1:{' '}
                                            {row.pembimbing1 ??
                                                'Belum ditetapkan'}{' '}
                                            • P2:{' '}
                                            {row.pembimbing2 ??
                                                'Belum ditetapkan'}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Badge
                                            variant={
                                                row.progress ===
                                                'Menunggu Assignment'
                                                    ? 'destructive'
                                                    : 'secondary'
                                            }
                                            className={
                                                row.progress === 'Siap Seminar'
                                                    ? 'bg-emerald-600 text-white dark:bg-emerald-500'
                                                    : ''
                                            }
                                        >
                                            {row.progress}
                                        </Badge>
                                        <Button size="sm" variant="outline">
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
