import { Head } from '@inertiajs/react';
import { AlertTriangle, ChartNoAxesColumn, CircleCheckBig } from 'lucide-react';

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
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/admin/dashboard' },
    { title: 'Beban Dosen', href: '/admin/beban-dosen' },
];

const dosenLoads = [
    { name: 'Dr. Budi Santoso', activeCount: 14, homebase: 'Informatika' },
    { name: 'Dr. Ratna Kusuma', activeCount: 11, homebase: 'Informatika' },
    { name: 'Dr. Hendra Wijaya', activeCount: 8, homebase: 'Sistem Informasi' },
    { name: 'Dr. Lia Permana', activeCount: 6, homebase: 'Informatika' },
];

export default function AdminBebanDosenPage() {
    return (
        <AdminLayout
            breadcrumbs={breadcrumbs}
            title="Beban Dosen"
            subtitle="Monitoring distribusi mahasiswa bimbingan per dosen"
        >
            <Head title="Beban Dosen" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Distribusi Beban Aktif</CardTitle>
                        <CardDescription>
                            Kuota maksimal tiap dosen adalah 14 mahasiswa aktif
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {dosenLoads.map((row) => {
                            const percentage = Math.min(
                                100,
                                (row.activeCount / 14) * 100,
                            );
                            const level =
                                row.activeCount >= 14
                                    ? 'critical'
                                    : row.activeCount >= 12
                                      ? 'warning'
                                      : 'normal';

                            return (
                                <div
                                    key={row.name}
                                    className="rounded-lg border bg-background p-4"
                                >
                                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <p className="text-sm font-semibold">
                                                {row.name}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {row.homebase}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Badge
                                                variant={
                                                    level === 'critical'
                                                        ? 'destructive'
                                                        : 'secondary'
                                                }
                                                className={
                                                    level === 'normal'
                                                        ? 'bg-emerald-600 text-white dark:bg-emerald-500'
                                                        : ''
                                                }
                                            >
                                                {row.activeCount}/14
                                            </Badge>
                                            <Button size="sm" variant="outline">
                                                Detail
                                            </Button>
                                        </div>
                                    </div>
                                    <div className="mt-3 h-2 rounded-full bg-muted">
                                        <div
                                            className={cn(
                                                'h-2 rounded-full',
                                                level === 'critical'
                                                    ? 'bg-destructive'
                                                    : level === 'warning'
                                                      ? 'bg-amber-500'
                                                      : 'bg-emerald-600',
                                            )}
                                            style={{ width: `${percentage}%` }}
                                        />
                                    </div>
                                    <div className="mt-2 flex items-center gap-2 text-xs text-muted-foreground">
                                        {level === 'critical' ? (
                                            <AlertTriangle className="size-3.5 text-destructive" />
                                        ) : (
                                            <CircleCheckBig className="size-3.5 text-emerald-600 dark:text-emerald-400" />
                                        )}
                                        <span>
                                            {level === 'critical'
                                                ? 'Kuota penuh, assignment baru diblokir.'
                                                : level === 'warning'
                                                  ? 'Mendekati kuota maksimal.'
                                                  : 'Kapasitas masih aman.'}
                                        </span>
                                    </div>
                                </div>
                            );
                        })}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Analitik Ringkas</CardTitle>
                        <CardDescription>
                            Placeholder untuk grafik distribusi beban
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex min-h-44 items-center justify-center rounded-lg border border-dashed bg-muted/30 p-6 text-sm text-muted-foreground">
                            <ChartNoAxesColumn className="mr-2 size-4" />
                            Area chart distribusi beban dosen akan ditampilkan
                            di fase berikutnya.
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
