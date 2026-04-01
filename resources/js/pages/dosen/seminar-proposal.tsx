import { Head, router, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    CheckCircle2,
    Clock,
    FileWarning,
    Inbox,
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
import { ScheduleDetailModal } from '@/components/schedule-detail-modal';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import DosenLayout from '@/layouts/dosen-layout';
import {
    academicGradeClassName,
    calculateAverageAcademicScore,
    resolveAcademicGrade,
} from '@/lib/academic-grade';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dosen/dashboard' },
    { title: 'Sempro & Sidang', href: '/dosen/seminar-proposal' },
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
    statusLabel: string;
    dueAt: string | null;
    resolvedAt: string | null;
    resolutionNotes: string | null;
    requestedBy: string;
    canResolve: boolean;
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
    errorMessage?: string | null;
};

const statusLabel: Record<string, string> = {
    scheduled: 'Dijadwalkan',
    awaiting_finalization: 'Menunggu Finalisasi',
    completed: 'Selesai',
    cancelled: 'Dibatalkan',
};

const statusColor: Record<string, string> = {
    scheduled: 'bg-primary/10 text-primary',
    awaiting_finalization: 'bg-amber-600/10 text-amber-700',
    completed: 'bg-emerald-600/10 text-emerald-600',
    cancelled: 'bg-destructive/10 text-destructive',
};

const decisionLabel: Record<string, string> = {
    pending: 'Pending',
    pass_with_revision: 'Perlu Revisi',
    pass: 'Disetujui',
    fail: 'Tidak Lulus',
};

const resultLabel: Record<string, string> = {
    pending: 'Menunggu Finalisasi',
    pass: 'Lulus',
    pass_with_revision: 'Lulus Revisi',
    fail: 'Tidak Lulus',
};

function resolveDefenseRoleLabel(role: string) {
    if (role === 'primary_supervisor') return 'Pembimbing 1';
    if (role === 'secondary_supervisor') return 'Pembimbing 2';
    return 'Penguji';
}

function DecisionForm({
    defenseId,
    onClose,
}: {
    defenseId: number;
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
        <div className="space-y-4 border-t bg-muted/20 px-4 py-4">
            <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                Input Keputusan
            </p>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-1.5">
                    <Label htmlFor="decision">Keputusan *</Label>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant={decision === 'pass' ? 'default' : 'outline'}
                            onClick={() => setDecision('pass')}
                            className={decision === 'pass' ? 'bg-emerald-600 hover:bg-emerald-700' : ''}
                        >
                            <CheckCircle2 className="size-3.5" /> Setujui
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant={decision === 'pass_with_revision' ? 'default' : 'outline'}
                            onClick={() => setDecision('pass_with_revision')}
                            className={decision === 'pass_with_revision' ? 'bg-amber-600 hover:bg-amber-700' : ''}
                        >
                            <FileWarning className="size-3.5" /> Perlu Revisi
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant={decision === 'fail' ? 'default' : 'outline'}
                            onClick={() => setDecision('fail')}
                            className={decision === 'fail' ? 'bg-destructive hover:bg-destructive/90' : ''}
                        >
                            <FileWarning className="size-3.5" /> Tidak Lulus
                        </Button>
                    </div>
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor="score">Nilai (0–100) *</Label>
                    <Input
                        id="score"
                        type="number"
                        min={0}
                        max={100}
                        step={0.01}
                        value={score}
                        onChange={(e) => setScore(e.target.value)}
                        placeholder="0 – 100"
                    />
                </div>
            </div>

            <div className="grid gap-1.5">
                <Label htmlFor="decision-notes">Catatan Keputusan</Label>
                <Textarea
                    id="decision-notes"
                    rows={2}
                    value={decisionNotes}
                    onChange={(e) => setDecisionNotes(e.target.value)}
                    placeholder="Catatan umum untuk mahasiswa..."
                    className="resize-none"
                />
            </div>

            {decision === 'pass_with_revision' && (
                <div className="grid gap-1.5">
                    <Label htmlFor="revision-notes">Catatan Revisi *</Label>
                    <Textarea
                        id="revision-notes"
                        rows={3}
                        value={revisionNotes}
                        onChange={(e) => setRevisionNotes(e.target.value)}
                        placeholder="Jelaskan apa yang perlu direvisi..."
                        className="resize-none"
                    />
                </div>
            )}

            <div className="flex gap-2">
                <Button size="sm" onClick={submit} disabled={submitting || !decision || !score}>
                    <Send className="size-3.5" />
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
    const [resolvingRevisionId, setResolvingRevisionId] = useState<number | null>(null);

    const canDecide = item.myDecision === 'pending' && item.defenseStatus === 'scheduled';
    const averageScore = calculateAverageAcademicScore([
        item.myScore,
        ...item.otherExaminers.map((e) => e.score),
    ]);
    const finalGrade = resolveAcademicGrade(averageScore);

    function resolveRevision(revisionId: number) {
        setResolvingRevisionId(revisionId);
        router.post(
            `/dosen/seminar-proposal/revisions/${revisionId}/resolve`,
            {},
            { preserveScroll: true, onFinish: () => setResolvingRevisionId(null) },
        );
    }

    return (
        <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
            {/* ── Title + status ── */}
            <div className="flex items-start justify-between gap-3 px-4 py-3">
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold leading-snug">{item.titleId}</p>
                    {item.titleEn && (
                        <p className="mt-0.5 truncate text-xs italic text-muted-foreground">
                            {item.titleEn}
                        </p>
                    )}
                </div>
                <span
                    className={cn(
                        'shrink-0 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                        statusColor[item.defenseStatus] ?? 'bg-muted text-muted-foreground',
                    )}
                >
                    {statusLabel[item.defenseStatus] ?? item.defenseStatus}
                </span>
            </div>

            {/* ── Meta strip ── */}
            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 border-t bg-muted/20 px-4 py-2 text-xs text-muted-foreground">
                <Badge variant="outline" className="rounded-full text-xs">
                    {item.typeLabel} #{item.attemptNo}
                </Badge>
                <span className="flex items-center gap-1">
                    <User className="size-3 shrink-0" />
                    {item.studentName} · {item.studentNim}
                </span>
                {item.scheduledFor && (
                    <span className="flex items-center gap-1">
                        <Clock className="size-3 shrink-0" />
                        {item.scheduledFor}
                    </span>
                )}
                <span className="flex items-center gap-1">
                    <MapPin className="size-3 shrink-0" />
                    {item.location} · {item.mode}
                </span>
            </div>

            {/* ── Decision rows ── */}
            <div className="divide-y border-t px-4">
                {/* My decision */}
                <div className="flex flex-wrap items-center gap-2 py-2.5 text-xs">
                    <span className="text-muted-foreground">
                        Saya ({resolveDefenseRoleLabel(item.myRole)}):
                    </span>
                    {item.myDecision === 'pending' ? (
                        <span className="font-medium text-muted-foreground">Belum diputus</span>
                    ) : (
                        <>
                            <Badge
                                variant="soft"
                                className={cn(
                                    'text-xs font-semibold',
                                    item.myDecision === 'pass'
                                        ? 'bg-emerald-600/10 text-emerald-600'
                                        : item.myDecision === 'pass_with_revision'
                                          ? 'bg-amber-600/10 text-amber-600'
                                          : 'bg-destructive/10 text-destructive',
                                )}
                            >
                                {decisionLabel[item.myDecision ?? ''] ?? item.myDecision}
                            </Badge>
                            {item.myScore !== null && (
                                <span className="flex items-center gap-1 font-medium">
                                    <Star className="size-3 text-amber-500" />
                                    {item.myScore}
                                </span>
                            )}
                            {item.myDecisionNotes && (
                                <span className="italic text-muted-foreground">
                                    — {item.myDecisionNotes}
                                </span>
                            )}
                        </>
                    )}
                </div>

                {/* Other examiners */}
                {item.otherExaminers.map((ex, i) => (
                    <div key={i} className="flex flex-wrap items-center gap-2 py-2.5 text-xs">
                        <span className="text-muted-foreground">
                            {ex.name} ({resolveDefenseRoleLabel(ex.role)}):
                        </span>
                        <Badge
                            variant="soft"
                            className={cn(
                                'text-xs font-medium',
                                ex.decision === 'pass'
                                    ? 'bg-emerald-600/10 text-emerald-600'
                                    : ex.decision === 'pass_with_revision'
                                      ? 'bg-amber-600/10 text-amber-600'
                                      : ex.decision === 'fail'
                                        ? 'bg-destructive/10 text-destructive'
                                        : 'bg-muted text-muted-foreground',
                            )}
                        >
                            {decisionLabel[ex.decision ?? ''] ?? 'Pending'}
                        </Badge>
                        {ex.score !== null && (
                            <span className="flex items-center gap-1 font-medium">
                                <Star className="size-3 text-amber-500" />
                                {ex.score}
                            </span>
                        )}
                    </div>
                ))}

                {/* Average score */}
                {averageScore !== null && (
                    <div className="flex flex-wrap items-center gap-2 py-2.5 text-xs">
                        <span className="text-muted-foreground">Rata-rata:</span>
                        <Badge variant="secondary" className="text-xs">
                            {averageScore.toFixed(2)}
                        </Badge>
                        {finalGrade && (
                            <Badge
                                variant="soft"
                                className={cn('text-xs', academicGradeClassName(finalGrade))}
                            >
                                Grade {finalGrade}
                            </Badge>
                        )}
                        <span className="text-muted-foreground">
                            · Hasil: {resultLabel[item.defenseResult] ?? item.defenseResult}
                        </span>
                    </div>
                )}
            </div>

            {/* ── Revisions ── */}
            {item.revisions.length > 0 && (
                <div className="space-y-2 border-t bg-amber-50 px-4 py-3 dark:bg-amber-950/30">
                    <p className="text-[11px] font-medium uppercase tracking-wider text-amber-700 dark:text-amber-300">
                        Revisi ({item.revisions.length})
                    </p>
                    {item.revisions.map((rev) => (
                        <div key={rev.id}>
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge variant="outline" className="text-xs">
                                    {rev.statusLabel}
                                </Badge>
                                <span className="text-xs text-amber-700 dark:text-amber-300">
                                    {rev.requestedBy}
                                </span>
                                {rev.dueAt && (
                                    <span className="text-xs text-amber-600 dark:text-amber-400">
                                        Batas: {rev.dueAt}
                                    </span>
                                )}
                            </div>
                            <p className="mt-1 text-xs text-amber-900 dark:text-amber-100">
                                {rev.notes}
                            </p>
                            {rev.canResolve && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="secondary"
                                    className="mt-1.5"
                                    disabled={resolvingRevisionId === rev.id}
                                    onClick={() => resolveRevision(rev.id)}
                                >
                                    {resolvingRevisionId === rev.id ? 'Menyimpan...' : 'Revisi Selesai'}
                                </Button>
                            )}
                        </div>
                    ))}
                </div>
            )}

            {/* ── Action ── */}
            {canDecide && !showForm && (
                <div className="border-t px-4 py-3">
                    <Button size="sm" onClick={() => setShowForm(true)}>
                        Input Keputusan
                    </Button>
                </div>
            )}

            {showForm && (
                <DecisionForm
                    defenseId={item.defenseId}
                    onClose={() => setShowForm(false)}
                />
            )}
        </div>
    );
}

type WorkspaceFilter = 'ujian' | 'bimbingan' | 'semua';

export default function DosenSeminarProposalPage() {
    const { defenses, workspaceEvents, flashMessage, errorMessage } =
        usePage<SharedData & PageProps>().props;

    const [search, setSearch] = useState('');
    const [workspaceFilter, setWorkspaceFilter] = useState<WorkspaceFilter>('ujian');
    const [visibleSemproReviewedCount, setVisibleSemproReviewedCount] = useState(5);
    const [visibleSidangReviewedCount, setVisibleSidangReviewedCount] = useState(5);

    const [selectedEvent, setSelectedEvent] = useState<{
        id: number;
        topic: string;
        person: string;
        personRole: 'lecturer' | 'student';
        start: string;
        end: string;
        location: string;
        status:
            | 'scheduled'
            | 'pending'
            | 'approved'
            | 'rescheduled'
            | 'rejected'
            | 'completed'
            | 'cancelled';
        notes?: string | null;
    } | null>(null);
    const [isDetailModalOpen, setIsDetailModalOpen] = useState(false);

    const sortByScheduledForDesc = (items: DefenseItem[]) =>
        [...items].sort((a, b) => {
            const aTime = a.scheduledFor ? new Date(a.scheduledFor).getTime() : 0;
            const bTime = b.scheduledFor ? new Date(b.scheduledFor).getTime() : 0;
            return bTime - aTime;
        });

    const normalizedSearch = search.trim().toLowerCase();
    const visibleDefenses = useMemo(() => {
        if (!normalizedSearch) return defenses;
        return defenses.filter((item) =>
            [item.studentName, item.titleId, item.titleEn]
                .filter(Boolean)
                .some((v) => v.toLowerCase().includes(normalizedSearch)),
        );
    }, [defenses, normalizedSearch]);

    const semproPendingItems = sortByScheduledForDesc(
        visibleDefenses.filter(
            (i) => i.type === 'sempro' && i.defenseStatus === 'scheduled' && i.myDecision === 'pending',
        ),
    );
    const semproReviewedItems = sortByScheduledForDesc(
        visibleDefenses.filter(
            (i) => i.type === 'sempro' && !(i.defenseStatus === 'scheduled' && i.myDecision === 'pending'),
        ),
    );
    const sidangPendingItems = sortByScheduledForDesc(
        visibleDefenses.filter(
            (i) => i.type === 'sidang' && i.defenseStatus === 'scheduled' && i.myDecision === 'pending',
        ),
    );
    const sidangReviewedItems = sortByScheduledForDesc(
        visibleDefenses.filter(
            (i) => i.type === 'sidang' && !(i.defenseStatus === 'scheduled' && i.myDecision === 'pending'),
        ),
    );

    const filteredWorkspaceEvents = workspaceEvents.filter((e) =>
        workspaceFilter === 'semua' ? true : e.category === workspaceFilter,
    );

    const visibleSemproReviewedItems = semproReviewedItems.slice(0, visibleSemproReviewedCount);
    const visibleSidangReviewedItems = sidangReviewedItems.slice(0, visibleSidangReviewedCount);

    function handleEventClick(event: BimbinganEvent) {
        const scheduleId = Number(
            String(event.id).replace('defense-', '').replace('schedule-', ''),
        );
        setSelectedEvent({
            id: Number.isNaN(scheduleId) ? 0 : scheduleId,
            topic:
                event.title === event.topic
                    ? event.topic
                    : `${event.title} - ${event.topic}`,
            person: event.person,
            personRole: event.personRole,
            start: event.start,
            end: event.end,
            location: event.location,
            status: event.status,
            notes: null,
        });
        setIsDetailModalOpen(true);
    }

    const workspaceFilterTabs: { label: string; value: WorkspaceFilter }[] = [
        { label: 'Sempro / Sidang', value: 'ujian' },
        { label: 'Bimbingan', value: 'bimbingan' },
        { label: 'Semua', value: 'semua' },
    ];

    function EmptySection({ text }: { text: string }) {
        return (
            <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-8 text-center">
                <CalendarClock className="mb-2 size-7 text-muted-foreground/40" />
                <p className="text-xs text-muted-foreground">{text}</p>
            </div>
        );
    }

    return (
        <DosenLayout
            breadcrumbs={breadcrumbs}
            title="Sempro & Sidang"
            subtitle="Jadwal dan penilaian sempro serta sidang"
        >
            <Head title="Sempro & Sidang — Dosen" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-8 px-4 py-6 md:px-6 lg:py-8">

                {/* Flash / Error */}
                {(flashMessage || errorMessage) && (
                    <Alert variant={errorMessage ? 'destructive' : 'default'}>
                        <AlertTitle>{errorMessage ? 'Error' : 'Berhasil'}</AlertTitle>
                        <AlertDescription>{errorMessage || flashMessage}</AlertDescription>
                    </Alert>
                )}

                {/* ── WORKSPACE CALENDAR ── */}
                <section>
                    <div className="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 className="text-base font-semibold">Workspace Jadwal</h2>
                            <p className="text-sm text-muted-foreground">
                                Lihat jadwal sempro, sidang, dan bimbingan dalam satu kalender
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-1.5">
                            {workspaceFilterTabs.map((tab) => (
                                <button
                                    key={tab.value}
                                    type="button"
                                    onClick={() => setWorkspaceFilter(tab.value)}
                                    className={cn(
                                        'rounded-full px-3 py-1 text-xs font-medium transition-colors',
                                        workspaceFilter === tab.value
                                            ? 'bg-primary text-primary-foreground shadow-sm'
                                            : 'bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground',
                                    )}
                                >
                                    {tab.label}
                                </button>
                            ))}
                        </div>
                    </div>
                    <BimbinganCalendar
                        events={filteredWorkspaceEvents}
                        onEventClick={handleEventClick}
                        defaultView="calendar"
                        showLegend={false}
                    />
                </section>

                {/* ── SEARCH ── */}
                <div className="relative max-w-xl">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Cari nama mahasiswa atau judul..."
                        className="pl-9"
                    />
                </div>

                {/* ── MAIN CONTENT ── */}
                {visibleDefenses.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-16 text-center">
                        <Inbox className="mb-3 size-10 text-muted-foreground/40" />
                        <p className="text-sm font-semibold">
                            {search.trim() === '' ? 'Belum ada tugas penguji ujian' : 'Tidak ada hasil yang cocok'}
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {search.trim() === ''
                                ? 'Anda belum ditugaskan sebagai penguji sempro atau sidang manapun.'
                                : 'Coba kata kunci lain untuk nama mahasiswa atau judul.'}
                        </p>
                    </div>
                ) : (
                    <div className="grid gap-10">

                        {/* ── SEMPRO ── */}
                        <section>
                            <div className="mb-4 border-b pb-3">
                                <h2 className="text-base font-semibold">Seminar Proposal</h2>
                                <p className="text-sm text-muted-foreground">
                                    Sempro yang menunggu penilaian atau tindak lanjut Anda
                                </p>
                            </div>
                            <div className="grid gap-8 lg:grid-cols-2 lg:items-start">
                                {/* Pending */}
                                <section>
                                    <div className="mb-3 flex items-center justify-between">
                                        <h3 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                            Menunggu Penilaian
                                        </h3>
                                        {semproPendingItems.length > 0 && (
                                            <span className="inline-flex size-5 items-center justify-center rounded-full bg-amber-600/10 text-xs font-bold text-amber-700">
                                                {semproPendingItems.length}
                                            </span>
                                        )}
                                    </div>
                                    {semproPendingItems.length > 0 ? (
                                        <div className="flex flex-col gap-3">
                                            {semproPendingItems.map((item) => (
                                                <DefenseCard key={item.defenseId} item={item} />
                                            ))}
                                        </div>
                                    ) : (
                                        <EmptySection text="Tidak ada sempro yang menunggu penilaian" />
                                    )}
                                </section>

                                {/* Reviewed */}
                                <section>
                                    <div className="mb-3 flex items-center justify-between">
                                        <h3 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                            Riwayat Sempro
                                        </h3>
                                        {semproReviewedItems.length > 0 && (
                                            <span className="text-xs text-muted-foreground">
                                                {semproReviewedItems.length} item
                                            </span>
                                        )}
                                    </div>
                                    {visibleSemproReviewedItems.length > 0 ? (
                                        <>
                                            <div className="flex flex-col gap-3">
                                                {visibleSemproReviewedItems.map((item) => (
                                                    <DefenseCard key={`rs-${item.defenseId}`} item={item} />
                                                ))}
                                            </div>
                                            {semproReviewedItems.length > visibleSemproReviewedItems.length && (
                                                <div className="mt-3 flex items-center justify-between">
                                                    <p className="text-xs text-muted-foreground">
                                                        {visibleSemproReviewedItems.length} dari {semproReviewedItems.length}
                                                    </p>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => setVisibleSemproReviewedCount((c) => c + 5)}
                                                    >
                                                        Muat Lebih Banyak
                                                    </Button>
                                                </div>
                                            )}
                                        </>
                                    ) : (
                                        <EmptySection text="Belum ada riwayat penilaian sempro" />
                                    )}
                                </section>
                            </div>
                        </section>

                        {/* ── SIDANG ── */}
                        <section>
                            <div className="mb-4 border-b pb-3">
                                <h2 className="text-base font-semibold">Sidang Skripsi</h2>
                                <p className="text-sm text-muted-foreground">
                                    Sidang yang menunggu penilaian atau tindak lanjut Anda
                                </p>
                            </div>
                            <div className="grid gap-8 lg:grid-cols-2 lg:items-start">
                                {/* Pending */}
                                <section>
                                    <div className="mb-3 flex items-center justify-between">
                                        <h3 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                            Menunggu Penilaian
                                        </h3>
                                        {sidangPendingItems.length > 0 && (
                                            <span className="inline-flex size-5 items-center justify-center rounded-full bg-amber-600/10 text-xs font-bold text-amber-700">
                                                {sidangPendingItems.length}
                                            </span>
                                        )}
                                    </div>
                                    {sidangPendingItems.length > 0 ? (
                                        <div className="flex flex-col gap-3">
                                            {sidangPendingItems.map((item) => (
                                                <DefenseCard key={item.defenseId} item={item} />
                                            ))}
                                        </div>
                                    ) : (
                                        <EmptySection text="Tidak ada sidang yang menunggu penilaian" />
                                    )}
                                </section>

                                {/* Reviewed */}
                                <section>
                                    <div className="mb-3 flex items-center justify-between">
                                        <h3 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                            Riwayat Sidang
                                        </h3>
                                        {sidangReviewedItems.length > 0 && (
                                            <span className="text-xs text-muted-foreground">
                                                {sidangReviewedItems.length} item
                                            </span>
                                        )}
                                    </div>
                                    {visibleSidangReviewedItems.length > 0 ? (
                                        <>
                                            <div className="flex flex-col gap-3">
                                                {visibleSidangReviewedItems.map((item) => (
                                                    <DefenseCard key={`rs-${item.defenseId}`} item={item} />
                                                ))}
                                            </div>
                                            {sidangReviewedItems.length > visibleSidangReviewedItems.length && (
                                                <div className="mt-3 flex items-center justify-between">
                                                    <p className="text-xs text-muted-foreground">
                                                        {visibleSidangReviewedItems.length} dari {sidangReviewedItems.length}
                                                    </p>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => setVisibleSidangReviewedCount((c) => c + 5)}
                                                    >
                                                        Muat Lebih Banyak
                                                    </Button>
                                                </div>
                                            )}
                                        </>
                                    ) : (
                                        <EmptySection text="Belum ada riwayat penilaian sidang" />
                                    )}
                                </section>
                            </div>
                        </section>
                    </div>
                )}
            </div>

            <ScheduleDetailModal
                open={isDetailModalOpen}
                onOpenChange={setIsDetailModalOpen}
                schedule={selectedEvent}
                currentUserRole="dosen"
            />
        </DosenLayout>
    );
}
