import { Head, useForm, usePage } from '@inertiajs/react';
import { CalendarClock, CheckCircle2, Star } from 'lucide-react';
import { FormEvent } from 'react';

import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { cn } from '@/lib/utils';
import { makeSettingsBreadcrumbs } from '@/pages/settings/breadcrumbs';
import { type SharedData } from '@/types';

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

const breadcrumbs = makeSettingsBreadcrumbs('CSAT', '/settings/csat');

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
        >
            <Head title="CSAT" />

            <SettingsLayout>
                {/* Header section */}
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="text-lg font-semibold">
                            Kepuasan pengguna (CSAT)
                        </h2>
                        <Badge variant="outline">{roleLabel}</Badge>
                        <Badge variant="outline">{csat.programStudi}</Badge>
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Bagaimana pengalaman Anda menggunakan SiTA? Pilih nilai
                        yang paling sesuai dan berikan masukan jika perlu.
                    </p>
                    <div className="mt-3 flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                        <span className="inline-flex items-center gap-2 rounded-full border bg-muted/40 px-3 py-1">
                            <CalendarClock className="size-4" />
                            Limit 1 submit/{csat.cooldownDays} hari
                        </span>
                        {lastSubmittedAt ? (
                            <span className="inline-flex items-center gap-2 rounded-full border bg-muted/40 px-3 py-1">
                                <Star className="size-4" />
                                Submit terakhir {lastSubmittedAt}
                            </span>
                        ) : null}
                    </div>
                </div>

                <Separator />

                {/* Status messages */}
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

                {/* Rating form */}
                <form onSubmit={submit} className="space-y-8">
                    {/* Score section */}
                    <div className="space-y-4">
                        <div>
                            <h3 className="text-base font-medium">
                                Nilai kepuasan
                            </h3>
                            <p className="text-sm text-muted-foreground">
                                Pilih satu nilai yang paling menggambarkan
                                pengalaman Anda saat ini.
                            </p>
                        </div>

                        <div className="grid gap-2">
                            {scoreOptions.map((option) => {
                                const isSelected =
                                    form.data.score === option.value;

                                return (
                                    <button
                                        key={option.value}
                                        type="button"
                                        onClick={() =>
                                            form.setData('score', option.value)
                                        }
                                        disabled={
                                            !csat.canSubmit || form.processing
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
                    </div>

                    <Separator />

                    {/* Kritik & Saran section */}
                    <div className="space-y-4">
                        <div>
                            <h3 className="text-base font-medium">
                                Kritik dan saran
                            </h3>
                            <p className="text-sm text-muted-foreground">
                                Opsional, tetapi sangat membantu jika ada hal
                                yang ingin Anda jelaskan lebih spesifik.
                            </p>
                        </div>

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
                                    form.setData('kritik', event.target.value)
                                }
                                placeholder="Tuliskan hal yang paling perlu diperbaiki"
                                rows={4}
                                disabled={!csat.canSubmit || form.processing}
                            />
                            <InputError message={form.errors.kritik} />
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
                                    form.setData('saran', event.target.value)
                                }
                                placeholder="Tuliskan ide atau saran yang menurut Anda penting"
                                rows={4}
                                disabled={!csat.canSubmit || form.processing}
                            />
                            <InputError message={form.errors.saran} />
                        </div>

                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <p className="text-sm text-muted-foreground">
                                Feedback Anda akan membantu tim admin
                                memprioritaskan perbaikan produk.
                            </p>
                            <Button
                                type="submit"
                                disabled={!csat.canSubmit || form.processing}
                            >
                                {form.processing
                                    ? 'Menyimpan...'
                                    : 'Kirim CSAT'}
                            </Button>
                        </div>
                    </div>
                </form>
            </SettingsLayout>
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
