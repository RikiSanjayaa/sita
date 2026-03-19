import { Head, Link, usePage } from '@inertiajs/react';
import { MessageCircle, MessageSquareText, UserRound } from 'lucide-react';

import { EmptyState } from '@/components/empty-state';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useInitials } from '@/hooks/use-initials';
import DosenLayout from '@/layouts/dosen-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dosen/dashboard' },
    { title: 'Mahasiswa Bimbingan', href: '/dosen/mahasiswa-bimbingan' },
];

type MahasiswaRow = {
    nim: string;
    name: string;
    avatar: string | null;
    advisorType: string;
    otherAdvisors: string[];
    stageLabel: string;
    stageDescription: string;
    status: string;
    lastUpdate: string;
    chatUrl: string | null;
    whatsappUrl: string | null;
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
    const getInitials = useInitials();

    return (
        <DosenLayout
            breadcrumbs={breadcrumbs}
            title="Mahasiswa Bimbingan"
            subtitle="Daftar kontak mahasiswa aktif dengan tahap akademik dan akses chat cepat"
        >
            <Head title="Mahasiswa Bimbingan" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-6 lg:gap-8 lg:py-8">
                <Card className="overflow-hidden py-0 shadow-sm">
                    <CardHeader className="border-b bg-muted/20 px-6 py-4">
                        <CardTitle>Kontak Mahasiswa Aktif</CardTitle>
                        <CardDescription>
                            Saat ini Anda menangani{' '}
                            <span className="font-semibold text-foreground">
                                {activeCount}
                            </span>{' '}
                            dari{' '}
                            <span className="font-semibold text-foreground">
                                {capacityLimit}
                            </span>{' '}
                            kuota mahasiswa aktif.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="pb-6">
                        {mahasiswaRows.length > 0 ? (
                            <div className="grid gap-3">
                                {mahasiswaRows.map((row) => (
                                    <div
                                        key={`${row.nim}-${row.advisorType}`}
                                        className="rounded-2xl border bg-background p-4 shadow-sm"
                                    >
                                        <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                            <div className="min-w-0 space-y-3">
                                                <div className="flex items-start gap-3">
                                                    <Avatar className="size-12 border">
                                                        <AvatarImage
                                                            src={
                                                                row.avatar ??
                                                                undefined
                                                            }
                                                            alt={row.name}
                                                        />
                                                        <AvatarFallback className="bg-primary/10 text-primary">
                                                            {getInitials(
                                                                row.name,
                                                            )}
                                                        </AvatarFallback>
                                                    </Avatar>

                                                    <div className="min-w-0 space-y-1">
                                                        <p className="text-base font-semibold text-foreground">
                                                            {row.name}
                                                        </p>
                                                        <p className="text-sm text-muted-foreground">
                                                            {row.nim} ·{' '}
                                                            {row.advisorType}
                                                        </p>
                                                    </div>
                                                </div>

                                                <div className="flex flex-wrap gap-2">
                                                    <Badge variant="outline">
                                                        {row.stageLabel}
                                                    </Badge>
                                                    <Badge
                                                        variant={
                                                            row.status ===
                                                            'Aktif'
                                                                ? 'secondary'
                                                                : 'outline'
                                                        }
                                                    >
                                                        {row.status}
                                                    </Badge>
                                                </div>

                                                <div className="grid gap-1 text-sm text-muted-foreground">
                                                    <p>
                                                        {row.stageDescription}
                                                    </p>
                                                    <p>
                                                        Pembimbing lain:{' '}
                                                        {row.otherAdvisors
                                                            .length > 0
                                                            ? row.otherAdvisors.join(
                                                                  ', ',
                                                              )
                                                            : 'Belum ada'}
                                                    </p>
                                                    <p>
                                                        Aktivitas terbaru:{' '}
                                                        {row.lastUpdate}
                                                    </p>
                                                </div>
                                            </div>

                                            <div className="flex shrink-0 flex-col gap-2 lg:items-end">
                                                {row.chatUrl ? (
                                                    <Button asChild>
                                                        <Link
                                                            href={row.chatUrl}
                                                        >
                                                            <MessageSquareText className="size-4" />
                                                            Buka Chat
                                                        </Link>
                                                    </Button>
                                                ) : (
                                                    <Button disabled>
                                                        <MessageSquareText className="size-4" />
                                                        Chat Belum Tersedia
                                                    </Button>
                                                )}

                                                {row.whatsappUrl ? (
                                                    <Button
                                                        asChild
                                                        variant="outline"
                                                    >
                                                        <a
                                                            href={
                                                                row.whatsappUrl
                                                            }
                                                            target="_blank"
                                                            rel="noreferrer"
                                                        >
                                                            <MessageCircle className="size-4" />
                                                            Chat WA
                                                        </a>
                                                    </Button>
                                                ) : null}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <EmptyState
                                icon={UserRound}
                                title="Belum ada mahasiswa aktif"
                                description="Mahasiswa aktif akan muncul di sini setelah penugasan pembimbing berjalan."
                            />
                        )}
                    </CardContent>
                </Card>
            </div>
        </DosenLayout>
    );
}
