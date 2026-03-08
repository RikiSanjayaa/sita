import { Head, router, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    CheckCircle2,
    Clock,
    FileWarning,
    MapPin,
    Search,
    Send,
    Star,
    User,
} from 'lucide-react';
import { useMemo, useState } from 'react';

import {
    BimbinganCalendar,
    type BimbinganEvent,
} from '@/components/bimbingan-calendar';
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
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import DosenLayout from '@/layouts/dosen-layout';
import {
    academicGradeClassName,
    calculateAverageAcademicScore,
    resolveAcademicGrade,
} from '@/lib/academic-grade';
import { type BreadcrumbItem, type SharedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dosen/dashboard' },
    { title: 'Seminar Proposal', href: '/dosen/seminar-proposal' },
];

type OtherExaminer = {
    name: string;
    role: string;
    order: number;
    decision: string | null;
    score: number | null;
};

type Revision = {
    id: number;
    notes: string;
    status: string;
    dueAt: string | null;
    resolvedAt: string | null;
    requestedBy: string;
};

type DefenseItem = {
    defenseId: number;
    type: 'sempro' | 'sidang';
    typeLabel: string;
    attemptNo: number;
    studentName: string;
    studentNim: string;
    titleId: string;
    titleEn: string;
    defenseStatus: string;
    defenseResult: string;
    scheduledFor: string | null;
    location: string;
    mode: string;
    myExaminerId: number;
    myRole: string;
    myOrder: number;
    myDecision: string | null;
    myScore: number | null;
    myDecisionNotes: string | null;
    otherExaminers: OtherExaminer[];
    revisions: Revision[];
};

type PageProps = {
    defenses: DefenseItem[];
    workspaceEvents: BimbinganEvent[];
    flashMessage?: string | null;
};

const statusLabel: Record<string, string> = {
    scheduled: 'Dijadwalkan',
    completed: 'Selesai',
    cancelled: 'Dibatalkan',
};

const statusColor: Record<string, string> = {
    scheduled: 'bg-primary/10 text-primary hover:bg-primary/20',
    completed: 'bg-emerald-600/10 text-emerald-600 hover:bg-emerald-600/20',
    cancelled: 'bg-destructive/10 text-destructive hover:bg-destructive/20',
};

const decisionLabel: Record<string, string> = {
    pending: 'Pending',
    pass_with_revision: 'Perlu Revisi',
    pass: 'Disetujui',
    fail: 'Tidak Lulus',
};

const resultLabel: Record<string, string> = {
    pending: 'Menunggu Hasil',
    pass: 'Lulus',
    pass_with_revision: 'Lulus Revisi',
    fail: 'Tidak Lulus',
};

function DecisionForm({
    defenseId,
    defenseType,
    onClose,
}: {
    defenseId: number;
    defenseType: 'sempro' | 'sidang';
    onClose: () => void;
}) {
    const [decision, setDecision] = useState<string>('');
    const [score, setScore] = useState<string>('');
    const [decisionNotes, setDecisionNotes] = useState<string>('');
    const [revisionNotes, setRevisionNotes] = useState<string>('');
    const [submitting, setSubmitting] = useState(false);

    function submit() {
        if (!decision || !score) return;

        setSubmitting(true);
        router.post(
            `/dosen/seminar-proposal/${defenseId}/decision`,
            {
                decision,
                score: parseFloat(score),
                decision_notes: decisionNotes || null,
                revision_notes:
                    decision === 'pass_with_revision' ? revisionNotes : null,
            },
            {
                onFinish: () => setSubmitting(false),
                onSuccess: () => onClose(),
            },
        );
    }

    return (
        <div className="mt-4 space-y-5 rounded-xl border bg-background p-5 shadow-sm">
            <h4 className="text-sm font-semibold">Input Keputusan</h4>

            <div className="grid gap-3 sm:grid-cols-2">
                <div>
                    <Label htmlFor="decision">Keputusan *</Label>
                    <div className="mt-1.5 flex gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant={
                                decision === 'pass' ? 'default' : 'outline'
                            }
                            onClick={() => setDecision('pass')}
                            className={
                                decision === 'pass'
                                    ? 'bg-emerald-600 hover:bg-emerald-700'
                                    : ''
                            }
                        >
                            <CheckCircle2 className="mr-1.5 size-3.5" />
                            Setujui
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant={
                                decision === 'pass_with_revision'
                                    ? 'default'
                                    : 'outline'
                            }
                            onClick={() => setDecision('pass_with_revision')}
                            className={
                                decision === 'pass_with_revision'
                                    ? 'bg-amber-600 hover:bg-amber-700'
                                    : ''
                            }
                        >
                            <FileWarning className="mr-1.5 size-3.5" />
                            Perlu Revisi
                        </Button>
                        {defenseType === 'sidang' && (
                            <Button
                                type="button"
                                size="sm"
                                variant={
                                    decision === 'fail' ? 'default' : 'outline'
                                }
                                onClick={() => setDecision('fail')}
                                className={
                                    decision === 'fail'
                                        ? 'bg-destructive hover:bg-destructive/90'
                                        : ''
                                }
                            >
                                <FileWarning className="mr-1.5 size-3.5" />
                                Tidak Lulus
                            </Button>
                        )}
                    </div>
                </div>

                <div>
                    <Label htmlFor="score">Nilai (0-100) *</Label>
                    <Input
                        id="score"
                        type="number"
                        min={0}
                        max={100}
                        step={0.01}
                        value={score}
                        onChange={(e) => setScore(e.target.value)}
                        placeholder="0 - 100"
                        className="mt-1.5"
                    />
                </div>
            </div>

            <div>
                <Label htmlFor="decision-notes">Catatan Keputusan</Label>
                <Textarea
                    id="decision-notes"
                    rows={2}
                    value={decisionNotes}
                    onChange={(e) => setDecisionNotes(e.target.value)}
                    placeholder="Catatan umum untuk mahasiswa..."
                    className="mt-1.5"
                />
            </div>

            {decision === 'pass_with_revision' && (
                <div>
                    <Label htmlFor="revision-notes">Catatan Revisi *</Label>
                    <Textarea
                        id="revision-notes"
                        rows={3}
                        value={revisionNotes}
                        onChange={(e) => setRevisionNotes(e.target.value)}
                        placeholder="Jelaskan apa yang perlu direvisi..."
                        className="mt-1.5"
                    />
                </div>
            )}

            <div className="flex gap-2">
                <Button
                    size="sm"
                    onClick={submit}
                    disabled={submitting || !decision || !score}
                >
                    <Send className="mr-1.5 size-3.5" />
                    {submitting ? 'Menyimpan...' : 'Submit Keputusan'}
                </Button>
                <Button size="sm" variant="ghost" onClick={onClose}>
                    Batal
                </Button>
            </div>
        </div>
    );
}

function DefenseCard({ item }: { item: DefenseItem }) {
    const [showForm, setShowForm] = useState(false);
    const canDecide =
        item.myDecision === 'pending' && item.defenseStatus === 'scheduled';
    const averageScore = calculateAverageAcademicScore([
        item.myScore,
        ...item.otherExaminers.map((examiner) => examiner.score),
    ]);
    const finalGrade = resolveAcademicGrade(averageScore);

    return (
        <Card className="shadow-sm">
            <CardHeader>
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <CardTitle className="text-base leading-snug">
                            {item.titleId}
                        </CardTitle>
                        {item.titleEn && (
                            <CardDescription className="mt-1 text-xs italic">
                                {item.titleEn}
                            </CardDescription>
                        )}
                    </div>
                    <Badge
                        variant="soft"
                        className={`font-semibold ${statusColor[item.defenseStatus] ?? 'bg-muted text-muted-foreground'}`}
                    >
                        {statusLabel[item.defenseStatus] ?? item.defenseStatus}
                    </Badge>
                </div>
                <div className="mt-3 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                    <Badge variant="outline">{item.typeLabel}</Badge>
                    <span>Attempt #{item.attemptNo}</span>
                    <span>
                        Hasil:{' '}
                        {resultLabel[item.defenseResult] ?? item.defenseResult}
                    </span>
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                {/* Student info */}
                <div className="flex flex-wrap gap-4 text-sm text-muted-foreground">
                    <span className="inline-flex items-center gap-1.5">
                        <User className="size-3.5" />
                        {item.studentName} ({item.studentNim})
                    </span>
                    {item.scheduledFor && (
                        <span className="inline-flex items-center gap-1.5">
                            <Clock className="size-3.5" />
                            {item.scheduledFor}
                        </span>
                    )}
                    <span className="inline-flex items-center gap-1.5">
                        <MapPin className="size-3.5" />
                        {item.location} ({item.mode})
                    </span>
                </div>

                {/* My decision */}
                <div className="rounded-md border p-3">
                    <p className="mb-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                        Keputusan Saya ({item.myRole} {item.myOrder})
                    </p>
                    {item.myDecision === 'pending' ? (
                        <Badge
                            variant="soft"
                            className="bg-muted font-medium text-muted-foreground hover:bg-muted"
                        >
                            Belum Diputuskan
                        </Badge>
                    ) : (
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge
                                variant="soft"
                                className={`font-semibold ${
                                    item.myDecision === 'pass'
                                        ? 'bg-emerald-600/10 text-emerald-600 hover:bg-emerald-600/20'
                                        : item.myDecision ===
                                            'pass_with_revision'
                                          ? 'bg-amber-600/10 text-amber-600 hover:bg-amber-600/20'
                                          : 'bg-destructive/10 text-destructive'
                                }`}
                            >
                                {decisionLabel[item.myDecision ?? ''] ??
                                    item.myDecision}
                            </Badge>
                            {item.myScore !== null && (
                                <span className="inline-flex items-center gap-1 text-sm font-medium">
                                    <Star className="size-3.5 text-amber-500" />
                                    {item.myScore}
                                </span>
                            )}
                            {item.myDecisionNotes && (
                                <p className="mt-1 w-full text-sm text-muted-foreground">
                                    {item.myDecisionNotes}
                                </p>
                            )}
                        </div>
                    )}
                </div>

                {/* Other examiners */}
                {item.otherExaminers.length > 0 && (
                    <div className="rounded-md border p-3">
                        <p className="mb-2 text-xs font-medium text-muted-foreground">
                            Penguji Lain
                        </p>
                        <div className="space-y-1.5">
                            {item.otherExaminers.map((ex, i) => (
                                <div
                                    key={i}
                                    className="flex items-center gap-2 text-sm"
                                >
                                    <span className="font-medium">
                                        {ex.name}
                                    </span>
                                    <Badge
                                        variant="soft"
                                        className={`font-medium ${
                                            ex.decision === 'pass'
                                                ? 'bg-emerald-600/10 text-emerald-600 hover:bg-emerald-600/20'
                                                : ex.decision ===
                                                    'pass_with_revision'
                                                  ? 'bg-amber-600/10 text-amber-600 hover:bg-amber-600/20'
                                                  : ex.decision === 'fail'
                                                    ? 'bg-destructive/10 text-destructive hover:bg-destructive/20'
                                                    : 'bg-muted text-muted-foreground hover:bg-muted'
                                        }`}
                                    >
                                        {decisionLabel[ex.decision ?? ''] ??
                                            'Pending'}
                                    </Badge>
                                    {ex.score !== null && (
                                        <span className="text-xs text-muted-foreground">
                                            Nilai: {ex.score}
                                        </span>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {averageScore !== null ? (
                    <div className="rounded-md border bg-muted/15 p-3">
                        <p className="mb-2 text-xs font-medium text-muted-foreground">
                            Nilai Akhir Gabungan
                        </p>
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="secondary">
                                Rata-rata: {averageScore.toFixed(2)}
                            </Badge>
                            {finalGrade ? (
                                <Badge
                                    variant="soft"
                                    className={academicGradeClassName(
                                        finalGrade,
                                    )}
                                >
                                    Grade Akhir {finalGrade}
                                </Badge>
                            ) : null}
                        </div>
                    </div>
                ) : null}

                {/* Revisions */}
                {item.revisions.length > 0 && (
                    <div className="rounded-md border border-amber-200 bg-amber-50 p-3 dark:border-amber-900 dark:bg-amber-950/30">
                        <p className="mb-2 text-xs font-medium text-amber-800 dark:text-amber-200">
                            Revisi ({item.revisions.length})
                        </p>
                        <div className="space-y-2">
                            {item.revisions.map((rev) => (
                                <div key={rev.id} className="text-sm">
                                    <p className="text-amber-900 dark:text-amber-100">
                                        {rev.notes}
                                    </p>
                                    <p className="mt-0.5 text-xs text-amber-700 dark:text-amber-300">
                                        Diminta oleh: {rev.requestedBy}
                                        {rev.dueAt && ` · Batas: ${rev.dueAt}`}
                                        {rev.resolvedAt &&
                                            ` · Selesai: ${rev.resolvedAt}`}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Action */}
                {canDecide && !showForm && (
                    <div className="pt-2">
                        <Button
                            size="sm"
                            onClick={() => setShowForm(true)}
                            className="px-5 font-semibold"
                        >
                            Input Keputusan
                        </Button>
                    </div>
                )}

                {showForm && (
                    <DecisionForm
                        defenseId={item.defenseId}
                        defenseType={item.type}
                        onClose={() => setShowForm(false)}
                    />
                )}
            </CardContent>
        </Card>
    );
}

export default function DosenSeminarProposalPage() {
    const { defenses, workspaceEvents, flashMessage } = usePage<
        SharedData & PageProps
    >().props;
    const [search, setSearch] = useState('');
    const sortByScheduledForDesc = (items: DefenseItem[]) =>
        [...items].sort((a, b) => {
            const aTime = a.scheduledFor
                ? new Date(a.scheduledFor).getTime()
                : 0;
            const bTime = b.scheduledFor
                ? new Date(b.scheduledFor).getTime()
                : 0;

            return bTime - aTime;
        });

    const normalizedSearch = search.trim().toLowerCase();
    const visibleDefenses = useMemo(() => {
        if (normalizedSearch === '') {
            return defenses;
        }

        return defenses.filter((item: DefenseItem) => {
            return [item.studentName, item.titleId, item.titleEn]
                .filter(Boolean)
                .some((value) =>
                    value.toLowerCase().includes(normalizedSearch),
                );
        });
    }, [defenses, normalizedSearch]);

    const semproPendingItems = sortByScheduledForDesc(
        visibleDefenses.filter(
            (item: DefenseItem) =>
                item.type === 'sempro' &&
                item.defenseStatus === 'scheduled' &&
                item.myDecision === 'pending',
        ),
    );
    const semproReviewedItems = sortByScheduledForDesc(
        visibleDefenses.filter(
            (item: DefenseItem) =>
                item.type === 'sempro' &&
                !(
                    item.defenseStatus === 'scheduled' &&
                    item.myDecision === 'pending'
                ),
        ),
    );
    const sidangPendingItems = sortByScheduledForDesc(
        visibleDefenses.filter(
            (item: DefenseItem) =>
                item.type === 'sidang' &&
                item.defenseStatus === 'scheduled' &&
                item.myDecision === 'pending',
        ),
    );
    const sidangReviewedItems = sortByScheduledForDesc(
        visibleDefenses.filter(
            (item: DefenseItem) =>
                item.type === 'sidang' &&
                !(
                    item.defenseStatus === 'scheduled' &&
                    item.myDecision === 'pending'
                ),
        ),
    );
    const [workspaceFilter, setWorkspaceFilter] = useState<
        'ujian' | 'bimbingan' | 'semua'
    >('ujian');
    const [visibleSemproReviewedCount, setVisibleSemproReviewedCount] =
        useState(5);
    const [visibleSidangReviewedCount, setVisibleSidangReviewedCount] =
        useState(5);

    const filteredWorkspaceEvents = workspaceEvents.filter((event) => {
        if (workspaceFilter === 'semua') {
            return true;
        }

        return event.category === workspaceFilter;
    });
    const visibleSemproReviewedItems = semproReviewedItems.slice(
        0,
        visibleSemproReviewedCount,
    );
    const visibleSidangReviewedItems = sidangReviewedItems.slice(
        0,
        visibleSidangReviewedCount,
    );

    return (
        <DosenLayout
            breadcrumbs={breadcrumbs}
            title="Sempro & Sidang"
            subtitle="Kelola tugas penguji seminar proposal dan sidang skripsi"
        >
            <Head title="Sempro & Sidang — Dosen" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-6 lg:gap-8 lg:py-8">
                {flashMessage && (
                    <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200">
                        {flashMessage}
                    </div>
                )}

                <Card className="shadow-sm">
                    <CardHeader>
                        <CardTitle>Pencarian</CardTitle>
                        <CardDescription>
                            Cari berdasarkan nama mahasiswa atau judul tugas
                            akhir.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="relative max-w-xl">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Cari nama mahasiswa atau judul..."
                                className="pl-9"
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card className="shadow-sm">
                    <CardHeader className="gap-3">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <CardTitle>Workspace Jadwal</CardTitle>
                                <CardDescription>
                                    Fokus default halaman ini adalah
                                    sempro/sidang, tetapi Anda tetap bisa
                                    menampilkan agenda bimbingan pada kalender
                                    yang sama.
                                </CardDescription>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant={
                                        workspaceFilter === 'ujian'
                                            ? 'default'
                                            : 'outline'
                                    }
                                    onClick={() => setWorkspaceFilter('ujian')}
                                >
                                    Sempro / Sidang
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant={
                                        workspaceFilter === 'bimbingan'
                                            ? 'default'
                                            : 'outline'
                                    }
                                    onClick={() =>
                                        setWorkspaceFilter('bimbingan')
                                    }
                                >
                                    Bimbingan
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant={
                                        workspaceFilter === 'semua'
                                            ? 'default'
                                            : 'outline'
                                    }
                                    onClick={() => setWorkspaceFilter('semua')}
                                >
                                    Semua
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <BimbinganCalendar
                            events={filteredWorkspaceEvents}
                            defaultView="calendar"
                            showLegend={false}
                        />
                    </CardContent>
                </Card>

                {visibleDefenses.length === 0 ? (
                    <div className="mt-6 rounded-xl border border-dashed bg-muted/20 p-8 text-center">
                        <span className="mx-auto mb-3 inline-flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
                            <CalendarClock className="size-6" />
                        </span>
                        <p className="text-sm font-semibold">
                            {search.trim() === ''
                                ? 'Belum ada tugas penguji ujian'
                                : 'Tidak ada hasil yang cocok'}
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {search.trim() === ''
                                ? 'Anda belum ditugaskan sebagai penguji sempro atau sidang manapun.'
                                : 'Coba kata kunci lain untuk nama mahasiswa atau judul.'}
                        </p>
                    </div>
                ) : (
                    <div className="grid gap-8">
                        <section className="grid gap-4">
                            <div>
                                <h2 className="text-lg font-semibold">
                                    Sempro
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Daftar seminar proposal yang menunggu
                                    penilaian atau tindak lanjut Anda.
                                </p>
                            </div>
                            {semproPendingItems.length > 0 ? (
                                semproPendingItems.map((item) => (
                                    <DefenseCard
                                        key={item.defenseId}
                                        item={item}
                                    />
                                ))
                            ) : (
                                <div className="rounded-xl border border-dashed bg-muted/20 p-6 text-sm text-muted-foreground">
                                    Tidak ada sempro yang sedang menunggu
                                    penilaian Anda.
                                </div>
                            )}
                        </section>

                        <section className="grid gap-4">
                            <div>
                                <h2 className="text-lg font-semibold">
                                    Sidang
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Daftar sidang skripsi yang perlu Anda nilai
                                    sebagai ketua, sekretaris, atau penguji.
                                </p>
                            </div>
                            {sidangPendingItems.length > 0 ? (
                                sidangPendingItems.map((item) => (
                                    <DefenseCard
                                        key={item.defenseId}
                                        item={item}
                                    />
                                ))
                            ) : (
                                <div className="rounded-xl border border-dashed bg-muted/20 p-6 text-sm text-muted-foreground">
                                    Tidak ada sidang yang sedang menunggu
                                    penilaian Anda.
                                </div>
                            )}
                        </section>

                        <section className="grid gap-4 border-t pt-2">
                            <div className="grid gap-8 lg:grid-cols-2 lg:items-start">
                                <section className="grid gap-4">
                                    <div>
                                        <h3 className="text-base font-semibold">
                                            Riwayat Sempro
                                        </h3>
                                        <p className="text-sm text-muted-foreground">
                                            Seminar proposal yang sudah Anda
                                            respons atau sudah selesai.
                                        </p>
                                    </div>
                                    {visibleSemproReviewedItems.length > 0 ? (
                                        visibleSemproReviewedItems.map(
                                            (item) => (
                                                <DefenseCard
                                                    key={`reviewed-sempro-${item.defenseId}`}
                                                    item={item}
                                                />
                                            ),
                                        )
                                    ) : (
                                        <div className="rounded-xl border border-dashed bg-muted/20 p-6 text-sm text-muted-foreground">
                                            Belum ada riwayat penilaian sempro.
                                        </div>
                                    )}
                                    {semproReviewedItems.length >
                                    visibleSemproReviewedItems.length ? (
                                        <div className="flex items-center justify-between gap-3 rounded-xl border bg-muted/15 p-3">
                                            <p className="text-sm text-muted-foreground">
                                                Menampilkan{' '}
                                                {
                                                    visibleSemproReviewedItems.length
                                                }{' '}
                                                dari{' '}
                                                {semproReviewedItems.length}{' '}
                                                riwayat sempro.
                                            </p>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setVisibleSemproReviewedCount(
                                                        (current) =>
                                                            current + 5,
                                                    )
                                                }
                                            >
                                                Muat Lebih Banyak
                                            </Button>
                                        </div>
                                    ) : null}
                                </section>

                                <section className="grid gap-4">
                                    <div>
                                        <h3 className="text-base font-semibold">
                                            Riwayat Sidang
                                        </h3>
                                        <p className="text-sm text-muted-foreground">
                                            Sidang yang sudah Anda nilai atau
                                            sudah berpindah ke arsip hasil.
                                        </p>
                                    </div>
                                    {visibleSidangReviewedItems.length > 0 ? (
                                        visibleSidangReviewedItems.map(
                                            (item) => (
                                                <DefenseCard
                                                    key={`reviewed-sidang-${item.defenseId}`}
                                                    item={item}
                                                />
                                            ),
                                        )
                                    ) : (
                                        <div className="rounded-xl border border-dashed bg-muted/20 p-6 text-sm text-muted-foreground">
                                            Belum ada riwayat penilaian sidang.
                                        </div>
                                    )}
                                    {sidangReviewedItems.length >
                                    visibleSidangReviewedItems.length ? (
                                        <div className="flex items-center justify-between gap-3 rounded-xl border bg-muted/15 p-3">
                                            <p className="text-sm text-muted-foreground">
                                                Menampilkan{' '}
                                                {
                                                    visibleSidangReviewedItems.length
                                                }{' '}
                                                dari{' '}
                                                {sidangReviewedItems.length}{' '}
                                                riwayat sidang.
                                            </p>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setVisibleSidangReviewedCount(
                                                        (current) =>
                                                            current + 5,
                                                    )
                                                }
                                            >
                                                Muat Lebih Banyak
                                            </Button>
                                        </div>
                                    ) : null}
                                </section>
                            </div>
                        </section>
                    </div>
                )}
            </div>
        </DosenLayout>
    );
}
