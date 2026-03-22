import { Head, useForm, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    CheckCircle2,
    MessageSquareText,
    Star,
} from 'lucide-react';
import { FormEvent } from 'react';

import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';

type CsatPageProps = {
    csat: {
        respondentRole: 'mahasiswa' | 'dosen';
        programStudi: string;
        cooldownDays: number;
        canSubmit: boolean;
        nextAvailableAt: string | null;
        lastSubmittedAt: string | null;
        lastScore: number | null;
    };
    status?: string | null;
};

type CsatFormData = {
    score: number | null;
    kritik: string;
    saran: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings' },
    { title: 'CSAT', href: '/settings/csat' },
];

const scoreOptions = [
    { value: 1, label: 'Sangat tidak puas', hint: 'Banyak kendala' },
    { value: 2, label: 'Tidak puas', hint: 'Masih menghambat' },
    { value: 3, label: 'Cukup puas', hint: 'Cukup terbantu' },
    { value: 4, label: 'Puas', hint: 'Nyaman digunakan' },
    { value: 5, label: 'Sangat puas', hint: 'Sangat membantu' },
] as const;

export default function CsatPage() {
    const { auth, csat, status } = usePage<SharedData & CsatPageProps>().props;
    const form = useForm<CsatFormData>({
        score: null,
        kritik: '',
        saran: '',
    });

    const roleLabel = csat.respondentRole === 'dosen' ? 'Dosen' : 'Mahasiswa';
    const lastSubmittedAt = formatDate(csat.lastSubmittedAt);
    const nextAvailableAt = formatDate(csat.nextAvailableAt);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.post('/settings/csat', {
            preserveScroll: true,
        });
    }

    return (
        <AppLayout
            role={auth.activeRole === 'dosen' ? 'dosen' : auth.activeRole}
            breadcrumbs={breadcrumbs}
            title="CSAT"
            subtitle="Nilai pengalaman Anda, lalu tambahkan kritik atau saran jika diperlukan"
        >
            <Head title="CSAT" />

            <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 px-4 py-6 md:px-6 lg:gap-8">
                <Card className="overflow-hidden border-border/70 p-0 shadow-sm">
                    <CardContent className="bg-gradient-to-br from-background via-background to-primary/5 p-6 lg:p-8">
                        <div className="flex flex-col gap-5">
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge className="bg-primary text-primary-foreground hover:bg-primary/90">
                                    Form CSAT
                                </Badge>
                                <Badge variant="outline">{roleLabel}</Badge>
                                <Badge variant="outline">
                                    {csat.programStudi}
                                </Badge>
                            </div>

                            <div className="space-y-2">
                                <h1 className="text-2xl font-semibold tracking-tight text-foreground lg:text-3xl">
                                    Bagaimana pengalaman Anda menggunakan SiTA?
                                </h1>
                                <p className="text-sm leading-6 text-muted-foreground lg:text-base">
                                    Pilih nilai yang paling sesuai. Jika ada hal
                                    yang perlu diperbaiki, tuliskan dengan
                                    singkat di bawah.
                                </p>
                            </div>

                            <div className="flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                                <span className="inline-flex items-center gap-2 rounded-full border bg-background/80 px-3 py-1.5">
                                    <CalendarClock className="size-4" />
                                    Limit 1 submit/{csat.cooldownDays} hari
                                </span>

                                {lastSubmittedAt ? (
                                    <span className="inline-flex items-center gap-2 rounded-full border bg-background/80 px-3 py-1.5">
                                        <Star className="size-4" />
                                        Submit terakhir {lastSubmittedAt}
                                    </span>
                                ) : null}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {status ? (
                    <div className="rounded-2xl border border-emerald-500/25 bg-emerald-500/10 p-4 text-emerald-950 shadow-sm dark:text-emerald-100">
                        <div className="flex items-start gap-3">
                            <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-300" />
                            <div className="space-y-1">
                                <p className="text-sm font-semibold">
                                    Feedback berhasil dikirim
                                </p>
                                <p className="text-sm text-emerald-900/80 dark:text-emerald-100/80">
                                    {status}
                                </p>
                            </div>
                        </div>
                    </div>
                ) : null}

                {!csat.canSubmit ? (
                    <div className="rounded-2xl border border-amber-500/25 bg-amber-500/10 p-4 text-amber-950 shadow-sm dark:text-amber-100">
                        <div className="flex items-start gap-3">
                            <CalendarClock className="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-300" />
                            <div className="space-y-1">
                                <p className="text-sm font-semibold">
                                    Anda belum bisa submit lagi
                                </p>
                                <p className="text-sm text-amber-900/80 dark:text-amber-100/80">
                                    Feedback berikutnya bisa dikirim setelah{' '}
                                    {nextAvailableAt ?? 'periode limit selesai'}
                                    .
                                </p>
                                {csat.lastScore ? (
                                    <p className="text-sm text-amber-900/80 dark:text-amber-100/80">
                                        Skor terakhir Anda: {csat.lastScore}/5.
                                    </p>
                                ) : null}
                            </div>
                        </div>
                    </div>
                ) : null}

                <form onSubmit={submit} className="grid gap-6">
                    <Card className="overflow-hidden py-0 shadow-sm">
                        <CardHeader className="border-b bg-muted/20 px-6 py-4">
                            <CardTitle>Nilai kepuasan</CardTitle>
                            <CardDescription>
                                Pilih satu nilai yang paling menggambarkan
                                pengalaman Anda saat ini.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="grid gap-5 pb-6">
                            <div className="grid gap-2">
                                {scoreOptions.map((option) => {
                                    const isSelected =
                                        form.data.score === option.value;

                                    return (
                                        <button
                                            key={option.value}
                                            type="button"
                                            onClick={() =>
                                                form.setData(
                                                    'score',
                                                    option.value,
                                                )
                                            }
                                            disabled={
                                                !csat.canSubmit ||
                                                form.processing
                                            }
                                            className={cn(
                                                'flex items-center gap-4 rounded-xl border px-4 py-3 text-left transition-colors disabled:cursor-not-allowed disabled:opacity-60',
                                                isSelected
                                                    ? 'border-primary bg-primary/10'
                                                    : 'border-border bg-background hover:bg-muted/40',
                                            )}
                                        >
                                            <span
                                                className={cn(
                                                    'flex size-5 shrink-0 items-center justify-center rounded-full border transition-colors',
                                                    isSelected
                                                        ? 'border-primary bg-primary'
                                                        : 'border-muted-foreground/40 bg-background',
                                                )}
                                            >
                                                <span
                                                    className={cn(
                                                        'size-2 rounded-full bg-background transition-opacity',
                                                        isSelected
                                                            ? 'opacity-100'
                                                            : 'opacity-0',
                                                    )}
                                                />
                                            </span>

                                            <div className="min-w-0 flex-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="text-sm font-semibold text-foreground">
                                                        {option.value}.{' '}
                                                        {option.label}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {option.hint}
                                                    </span>
                                                </div>
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>

                            <InputError
                                message={form.errors.score}
                                className="text-sm"
                            />
                        </CardContent>
                    </Card>

                    <Card className="overflow-hidden py-0 shadow-sm">
                        <CardHeader className="border-b bg-muted/20 px-6 py-4">
                            <CardTitle>Kritik dan saran</CardTitle>
                            <CardDescription>
                                Opsional, tetapi sangat membantu jika ada hal
                                yang ingin Anda jelaskan lebih spesifik.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="grid gap-5 pb-6">
                            <div className="grid gap-2">
                                <label
                                    htmlFor="kritik"
                                    className="text-sm font-medium"
                                >
                                    Kritik
                                </label>
                                <Textarea
                                    id="kritik"
                                    value={form.data.kritik}
                                    onChange={(event) =>
                                        form.setData(
                                            'kritik',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Contoh: status proses masih membingungkan di beberapa halaman."
                                    rows={5}
                                    disabled={
                                        !csat.canSubmit || form.processing
                                    }
                                />
                                <InputError
                                    message={form.errors.kritik}
                                    className="text-sm"
                                />
                            </div>

                            <div className="grid gap-2">
                                <label
                                    htmlFor="saran"
                                    className="text-sm font-medium"
                                >
                                    Saran
                                </label>
                                <Textarea
                                    id="saran"
                                    value={form.data.saran}
                                    onChange={(event) =>
                                        form.setData(
                                            'saran',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Contoh: tambahkan penjelasan singkat di tiap status atau langkah berikutnya."
                                    rows={5}
                                    disabled={
                                        !csat.canSubmit || form.processing
                                    }
                                />
                                <InputError
                                    message={form.errors.saran}
                                    className="text-sm"
                                />
                            </div>

                            <div className="flex flex-wrap items-center justify-end border-t pt-4">
                                <Button
                                    type="submit"
                                    disabled={
                                        !csat.canSubmit || form.processing
                                    }
                                >
                                    {form.processing
                                        ? 'Menyimpan...'
                                        : 'Kirim CSAT'}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}

function formatDate(value: string | null): string | null {
    if (!value) {
        return null;
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'long',
    }).format(new Date(value));
}
