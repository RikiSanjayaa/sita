import { Head, useForm, usePage } from '@inertiajs/react';
import { CalendarClock, Star } from 'lucide-react';
import { FormEvent } from 'react';

import InputError from '@/components/input-error';
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
    const { auth, csat } = usePage<SharedData & CsatPageProps>().props;
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
                    <h2 className="text-xl font-semibold tracking-tight">
                        Kepuasan pengguna (CSAT)
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {roleLabel}, {csat.programStudi}
                    </p>
                    <p className="mt-3 text-sm text-pretty text-muted-foreground">
                        Bagaimana pengalaman Anda menggunakan SiTA? Pilih nilai
                        yang paling sesuai dan berikan masukan jika perlu.
                    </p>
                    <ul className="mt-3 flex flex-col gap-2 text-sm text-muted-foreground">
                        <li className="flex items-center gap-2">
                            <CalendarClock
                                aria-hidden="true"
                                className="size-4 shrink-0"
                            />
                            Limit 1 submit/{csat.cooldownDays} hari
                        </li>
                        {lastSubmittedAt ? (
                            <li className="flex items-center gap-2">
                                <Star
                                    aria-hidden="true"
                                    className="size-4 shrink-0"
                                />
                                Submit terakhir {lastSubmittedAt}
                            </li>
                        ) : null}
                    </ul>
                </div>

                <Separator />

                {!csat.canSubmit ? (
                    <div
                        role="status"
                        className="rounded-xl border border-warning/40 bg-warning-muted p-4 text-warning-muted-foreground"
                    >
                        <div className="flex items-start gap-3">
                            <CalendarClock
                                aria-hidden="true"
                                className="mt-0.5 size-5 shrink-0"
                            />
                            <div className="space-y-1">
                                <p className="text-sm font-semibold">
                                    Anda belum bisa submit lagi
                                </p>
                                <p className="text-sm">
                                    Feedback berikutnya bisa dikirim setelah{' '}
                                    {nextAvailableAt ?? 'periode limit selesai'}
                                    .
                                </p>
                                {csat.lastScore ? (
                                    <p className="text-sm">
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
                            <h3 className="text-base font-semibold">
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
                                        aria-pressed={isSelected}
                                        onClick={() =>
                                            form.setData('score', option.value)
                                        }
                                        disabled={
                                            !csat.canSubmit || form.processing
                                        }
                                        className={cn(
                                            'flex items-center gap-4 rounded-xl border px-4 py-3 text-left transition-colors disabled:cursor-not-allowed disabled:opacity-60 max-md:min-h-14',
                                            isSelected
                                                ? 'border-primary bg-primary-muted'
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
                            <h3 className="text-base font-semibold">
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
                                className="max-md:h-11 max-md:w-full"
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
