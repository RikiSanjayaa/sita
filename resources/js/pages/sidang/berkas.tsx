import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { useRef, useState } from 'react';

import { PageHeader } from '@/components/patterns/page-header';
import { SectionCard } from '@/components/patterns/section-card';
import { StatusBadge } from '@/components/patterns/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import KaprodiLayout from '@/layouts/kaprodi-layout';
import { print, store } from '@/routes/sidang/paper';

const roles = {
    examiner_1: 'Penguji 1',
    examiner_2: 'Penguji 2',
    supervisor: 'Pembimbing penilai',
};

type ScoreRole = keyof typeof roles;
type Panel = {
    id: number;
    name: string;
    supervisor: boolean;
    score: string | null;
    recordedBy: string | null;
    recordedAt: string | null;
    source: string | null;
    verifiedBy: string | null;
    verifiedAt: string | null;
};
type Scores = Record<
    ScoreRole,
    { panel_id: string; value: string; expected_value: string | null }
>;

interface Props {
    defense: {
        id: number;
        label: string;
        status: string;
        editable: boolean;
        canPrint: boolean;
        name: string;
        nim: string | null;
        title: string | null;
        schedule: string | null;
        location: string | null;
        sk: string | null;
        panel: Panel[];
        panelIds: Record<ScoreRole, number> | null;
        summary: { value: number; grade: string } | null;
    };
    weights: Record<ScoreRole, number>;
    forms: Record<string, string>;
    backUrl: string;
    fromQueue: boolean;
    finalizeUrl: string | null;
    admin: boolean;
}

const selectClass =
    'min-h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-ring';

function initialScore(member?: Panel) {
    return {
        panel_id: member ? String(member.id) : '',
        value: member?.score ?? '',
        expected_value: member?.score ?? null,
    };
}

function ScoreHistory({ member }: { member: Panel }) {
    return (
        <div className="space-y-1 text-sm text-muted-foreground">
            <p>
                {member.recordedBy
                    ? `${member.source === 'paper' ? 'Dari berkas' : 'Dari portal Dosen'} oleh ${member.recordedBy}${member.recordedAt ? ` · ${member.recordedAt}` : ''}`
                    : 'Riwayat penginput nilai lama belum tercatat.'}
            </p>
            <p>
                {member.verifiedAt
                    ? `Cocok dengan berkas${member.verifiedBy ? ` · ${member.verifiedBy}` : ''} · ${member.verifiedAt}`
                    : 'Belum dicocokkan dengan berkas.'}
            </p>
        </div>
    );
}

export default function BerkasSidang({
    defense,
    weights,
    forms,
    backUrl,
    fromQueue,
    finalizeUrl,
    admin,
}: Props) {
    const examiners = defense.panel.filter((member) => !member.supervisor);
    const supervisors = defense.panel.filter((member) => member.supervisor);
    const [confirming, setConfirming] = useState(false);
    const scoreRequestPending = useRef(false);
    const selectedMember = (role: ScoreRole, fallback?: Panel) =>
        defense.panel.find(
            (member) => member.id === defense.panelIds?.[role],
        ) ?? fallback;
    const form = useForm({
        sidang_sk: defense.sk ?? '',
        scores: {
            examiner_1: initialScore(
                selectedMember('examiner_1', examiners[0]),
            ),
            examiner_2: initialScore(
                selectedMember('examiner_2', examiners[1]),
            ),
            supervisor: initialScore(
                selectedMember(
                    'supervisor',
                    supervisors.length === 1 ? supervisors[0] : undefined,
                ),
            ),
        } satisfies Scores,
    });
    const roleEntries = Object.entries(roles) as [ScoreRole, string][];
    const canEnterScores = examiners.length >= 2 && supervisors.length > 0;
    const canPreview =
        roleEntries.every(([role]) => {
            const entry = form.data.scores[role];
            return (
                entry.panel_id &&
                entry.value !== '' &&
                Number(entry.value) >= 0 &&
                Number(entry.value) <= 100
            );
        }) &&
        new Set(Object.values(form.data.scores).map((entry) => entry.panel_id))
            .size === 3;
    const preview = canPreview
        ? roleEntries.reduce(
              (total, [role]) =>
                  total +
                  (Number(form.data.scores[role].value) * weights[role]) / 100,
              0,
          )
        : null;
    const number = (value: number) =>
        new Intl.NumberFormat('id-ID', { maximumFractionDigits: 4 }).format(
            value,
        );
    const printUrl = print(defense.id).url;
    const storeUrl = store(defense.id, {
        query: fromQueue ? { dari: 'daftar' } : {},
    }).url;
    const content = (
        <main className="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 md:px-6 lg:py-8">
            <Head title={`Berkas ${defense.label}`} />
            <PageHeader
                title={`Berkas ${defense.label}`}
                description={`${defense.name}${defense.nim ? ` · ${defense.nim}` : ''}`}
                actions={
                    <Button asChild variant="outline">
                        <a href={backUrl}>
                            <ArrowLeft aria-hidden /> Kembali
                        </a>
                    </Button>
                }
            />
            <SectionCard title="Detail sidang">
                <div className="space-y-2">
                    <p className="font-medium">
                        {defense.title ?? 'Judul belum tersedia'}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        {defense.schedule ?? 'Belum terjadwal'}
                        {defense.location ? ` · ${defense.location}` : ''}
                    </p>
                    <StatusBadge domain="defense" status={defense.status}>
                        {defense.status === 'completed'
                            ? 'Selesai'
                            : defense.status === 'awaiting_finalization'
                              ? 'Menunggu finalisasi'
                              : defense.status === 'scheduled'
                                ? 'Terjadwal'
                                : defense.status === 'cancelled'
                                  ? 'Dibatalkan'
                                  : 'Draf'}
                    </StatusBadge>
                </div>
            </SectionCard>
            <SectionCard
                title="Paket cetak"
                description="Identitas dan jadwal terisi dari SiTA. Unduh paket lengkap atau satu lembar, lalu buka dan cetak di Word."
            >
                {defense.canPrint ? (
                    <div className="space-y-3">
                        <Button asChild>
                            <a href={printUrl}>
                                <Printer aria-hidden /> Unduh Paket Sidang
                                (.docx)
                            </a>
                        </Button>
                        <ul className="grid gap-1 sm:grid-cols-2">
                            {Object.entries(forms).map(([key, name]) => (
                                <li key={key}>
                                    <Button
                                        asChild
                                        variant="link"
                                        className="h-auto min-h-9 justify-start px-0 text-left whitespace-normal"
                                    >
                                        <a
                                            href={
                                                print(defense.id, {
                                                    query: { lembar: key },
                                                }).url
                                            }
                                        >
                                            {name}
                                        </a>
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        Paket tersedia untuk sidang yang sudah dijadwalkan.
                    </p>
                )}
            </SectionCard>
            <form
                className="space-y-5"
                onSubmit={(event) => event.preventDefault()}
            >
                {Object.entries(form.errors).length > 0 && (
                    <ul
                        role="alert"
                        className="space-y-1 text-sm text-destructive"
                    >
                        {Object.entries(form.errors).map(([field, message]) => (
                            <li key={field}>{message}</li>
                        ))}
                    </ul>
                )}
                <SectionCard title="SK sidang">
                    <div className="max-w-lg space-y-2">
                        <Label htmlFor="sidang-sk">Nomor SK</Label>
                        <Input
                            id="sidang-sk"
                            maxLength={150}
                            disabled={!defense.editable}
                            value={form.data.sidang_sk}
                            onChange={(event) =>
                                form.setData('sidang_sk', event.target.value)
                            }
                        />
                    </div>
                    {defense.editable && (
                        <Button
                            type="button"
                            variant="outline"
                            className="mt-4"
                            disabled={form.processing}
                            onClick={() => {
                                form.transform(({ sidang_sk }) => ({
                                    sidang_sk,
                                }));
                                form.post(storeUrl, {
                                    preserveScroll: true,
                                });
                            }}
                        >
                            Simpan No SK
                        </Button>
                    )}
                </SectionCard>
                <SectionCard
                    title={
                        <span id="nilai-dari-berkas" className="scroll-mt-6">
                            Nilai dari berkas
                        </span>
                    }
                    description="Masukkan nilai total pada lembar masing-masing dosen. Nilai yang sudah tercatat ditampilkan otomatis."
                    contentClassName="flex flex-col gap-4"
                >
                    {defense.summary && (
                        <div className="space-y-2">
                            <p className="font-semibold tabular-nums">
                                Nilai akhir {number(defense.summary.value)} ·
                                Grade {defense.summary.grade}
                            </p>
                            {defense.status === 'awaiting_finalization' && (
                                <p className="text-sm text-muted-foreground">
                                    Nilai dapat dikoreksi sampai finalisasi.
                                    Petugas berwenang dapat menetapkan hasil di
                                    detail Tugas Akhir pada panel admin.
                                </p>
                            )}
                            {finalizeUrl && (
                                <Button asChild variant="outline">
                                    <a href={finalizeUrl}>
                                        Buka finalisasi sidang
                                    </a>
                                </Button>
                            )}
                        </div>
                    )}
                    {!defense.editable && defense.summary && (
                        <ul className="divide-y">
                            {roleEntries.map(([role, label]) => {
                                const member = defense.panel.find(
                                    (person) =>
                                        person.id === defense.panelIds?.[role],
                                );
                                return member ? (
                                    <li key={role} className="space-y-2 py-3">
                                        <p className="text-sm font-medium">
                                            {label} · {member.name} ·{' '}
                                            {member.score}
                                        </p>
                                        <ScoreHistory member={member} />
                                    </li>
                                ) : null;
                            })}
                        </ul>
                    )}
                    {defense.editable && canEnterScores ? (
                        <div className="space-y-4">
                            {roleEntries.map(([role, label]) => {
                                const entry = form.data.scores[role];
                                const member = defense.panel.find(
                                    (person) =>
                                        String(person.id) === entry.panel_id,
                                );
                                return (
                                    <div
                                        key={role}
                                        className="grid gap-2 border-b pb-3 sm:grid-cols-[minmax(0,1fr)_11rem] sm:items-end"
                                    >
                                        <div className="space-y-2">
                                            <Label
                                                htmlFor={`sidang-role-${role}`}
                                            >
                                                {label} ({weights[role]}%)
                                            </Label>
                                            <select
                                                id={`sidang-role-${role}`}
                                                className={selectClass}
                                                value={entry.panel_id}
                                                onChange={(event) => {
                                                    const selected =
                                                        defense.panel.find(
                                                            (person) =>
                                                                String(
                                                                    person.id,
                                                                ) ===
                                                                event.target
                                                                    .value,
                                                        );
                                                    form.setData('scores', {
                                                        ...form.data.scores,
                                                        [role]: initialScore(
                                                            selected,
                                                        ),
                                                    });
                                                }}
                                            >
                                                <option value="">
                                                    Pilih dosen
                                                </option>
                                                {(role === 'supervisor'
                                                    ? supervisors
                                                    : examiners
                                                )
                                                    .filter(
                                                        (person) =>
                                                            role ===
                                                                'supervisor' ||
                                                            !roleEntries.some(
                                                                ([other]) =>
                                                                    other !==
                                                                        role &&
                                                                    form.data
                                                                        .scores[
                                                                        other
                                                                    ]
                                                                        .panel_id ===
                                                                        String(
                                                                            person.id,
                                                                        ),
                                                            ),
                                                    )
                                                    .map((person) => (
                                                        <option
                                                            key={person.id}
                                                            value={person.id}
                                                        >
                                                            {person.name}
                                                        </option>
                                                    ))}
                                            </select>
                                        </div>
                                        <div className="space-y-2">
                                            <Label
                                                htmlFor={`sidang-score-${role}`}
                                            >
                                                Nilai {label.toLowerCase()}
                                            </Label>
                                            <Input
                                                id={`sidang-score-${role}`}
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="0.01"
                                                value={entry.value}
                                                disabled={!member}
                                                onChange={(event) =>
                                                    form.setData('scores', {
                                                        ...form.data.scores,
                                                        [role]: {
                                                            ...entry,
                                                            value: event.target
                                                                .value,
                                                        },
                                                    })
                                                }
                                            />
                                        </div>
                                        {member?.score !== null && member && (
                                            <div className="sm:col-span-2">
                                                <ScoreHistory member={member} />
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                            {preview !== null && (
                                <p className="text-sm font-medium tabular-nums">
                                    Pratinjau nilai akhir {number(preview)}
                                </p>
                            )}
                            <Button
                                type="button"
                                disabled={!canPreview || form.processing}
                                onClick={() => setConfirming(true)}
                            >
                                {defense.summary
                                    ? 'Simpan Koreksi / Pencocokan'
                                    : 'Simpan untuk Finalisasi'}
                            </Button>
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            {defense.editable
                                ? 'Input dari berkas membutuhkan dua penguji dan minimal satu pembimbing pada panel. Periksa susunan panel pada jadwal sidang.'
                                : defense.summary
                                  ? 'Nilai sudah difinalisasi.'
                                  : 'Belum ada rekap nilai dari berkas untuk sidang ini.'}
                        </p>
                    )}
                </SectionCard>
            </form>
            <Dialog
                open={confirming}
                onOpenChange={(open) => {
                    if (!form.processing) setConfirming(open);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Konfirmasi nilai dari berkas</DialogTitle>
                        <DialogDescription>
                            {defense.name} · {defense.nim} · {defense.label}
                        </DialogDescription>
                    </DialogHeader>
                    <ul className="divide-y text-sm">
                        {roleEntries.map(([role, label]) => (
                            <li
                                key={role}
                                className="flex justify-between gap-4 py-2"
                            >
                                <span>
                                    {label} ·{' '}
                                    {
                                        defense.panel.find(
                                            (member) =>
                                                String(member.id) ===
                                                form.data.scores[role].panel_id,
                                        )?.name
                                    }
                                </span>
                                <span className="font-medium tabular-nums">
                                    {form.data.scores[role].value}
                                </span>
                            </li>
                        ))}
                    </ul>
                    <p className="text-sm">
                        Nilai disimpan dan dicocokkan dengan berkas. Hasil akhir
                        ditetapkan melalui finalisasi sidang.
                    </p>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            disabled={form.processing}
                            onClick={() => setConfirming(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            disabled={form.processing}
                            onClick={() => {
                                if (scoreRequestPending.current) return;
                                scoreRequestPending.current = true;
                                form.transform((data) => data);
                                form.post(storeUrl, {
                                    preserveScroll: true,
                                    onFinish: () => {
                                        scoreRequestPending.current = false;
                                    },
                                    onError: () => setConfirming(false),
                                    onSuccess: () => {
                                        setConfirming(false);
                                        form.setData(
                                            'scores',
                                            Object.fromEntries(
                                                roleEntries.map(([role]) => [
                                                    role,
                                                    {
                                                        ...form.data.scores[
                                                            role
                                                        ],
                                                        expected_value:
                                                            form.data.scores[
                                                                role
                                                            ].value,
                                                    },
                                                ]),
                                            ) as Scores,
                                        );
                                    },
                                });
                            }}
                        >
                            Konfirmasi Simpan Nilai
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </main>
    );

    return admin ? (
        <div className="min-h-screen bg-background text-foreground">
            {content}
        </div>
    ) : (
        <KaprodiLayout
            breadcrumbs={[
                { title: 'Sidang Akhir', href: backUrl },
                { title: 'Berkas Sidang', href: '#' },
            ]}
        >
            {content}
        </KaprodiLayout>
    );
}
