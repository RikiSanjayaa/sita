import { Head, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CalendarClock,
    CheckCircle2,
    ChevronRight,
    Clock,
    FileWarning,
    Inbox,
    MapPin,
    Search,
    Send,
    Star,
    User,
    XCircle,
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
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
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

function MyDecisionBadge({ decision }: { decision: string | null }) {
    if (!decision || decision === 'pending') {
        return (
            <span className="inline-flex items-center gap-1 rounded-full bg-amber-600/10 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-400">
                <Clock className="size-3" />
                Belum Diputus
            </span>
        );
    }
    if (decision === 'pass') {
        return (
            <span className="inline-flex items-center gap-1 rounded-full bg-emerald-600/10 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-400">
                <CheckCircle2 className="size-3" />
                Disetujui
            </span>
        );
    }
    if (decision === 'pass_with_revision') {
        return (
            <span className="inline-flex items-center gap-1 rounded-full bg-amber-600/10 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-400">
                <FileWarning className="size-3" />
                Perlu Revisi
            </span>
        );
    }
    return (
        <span className="inline-flex items-center gap-1 rounded-full bg-destructive/10 px-2 py-0.5 text-xs font-medium text-destructive">
            <XCircle className="size-3" />
            Tidak Lulus
        </span>
    );
}

// ── Decision Form (inside sheet) ────────────────────────────────────
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
        <div className="space-y-4 rounded-xl border bg-muted/30 p-4">
            <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                Input Keputusan Saya
            </p>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-1.5">
                    <Label htmlFor="sheet-decision">Keputusan *</Label>
                    <div className="flex flex-wrap gap-2">
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
                            <CheckCircle2 className="size-3.5" /> Setujui
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
                            <FileWarning className="size-3.5" /> Perlu Revisi
                        </Button>
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
                            <XCircle className="size-3.5" /> Tidak Lulus
                        </Button>
                    </div>
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor="sheet-score">Nilai (0–100) *</Label>
                    <Input
                        id="sheet-score"
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
                <Label htmlFor="sheet-decision-notes">Catatan Keputusan</Label>
                <Textarea
                    id="sheet-decision-notes"
                    rows={2}
                    value={decisionNotes}
                    onChange={(e) => setDecisionNotes(e.target.value)}
                    placeholder="Catatan umum untuk mahasiswa..."
                    className="resize-none"
                />
            </div>

            {decision === 'pass_with_revision' && (
                <div className="grid gap-1.5">
                    <Label htmlFor="sheet-revision-notes">
                        Catatan Revisi *
                    </Label>
                    <Textarea
                        id="sheet-revision-notes"
                        rows={3}
                        value={revisionNotes}
                        onChange={(e) => setRevisionNotes(e.target.value)}
                        placeholder="Jelaskan apa yang perlu direvisi..."
                        className="resize-none"
                    />
                </div>
            )}

            <div className="flex gap-2 pt-1">
                <Button
                    size="sm"
                    onClick={submit}
                    disabled={submitting || !decision || !score}
                    className="flex-1 sm:flex-none"
                >
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

// ── Defense Detail Sheet ─────────────────────────────────────────────
function DefenseDetailSheet({
    item,
    open,
    onOpenChange,
}: {
    item: DefenseItem | null;
    open: boolean;
    onOpenChange: (v: boolean) => void;
}) {
    const [showDecisionForm, setShowDecisionForm] = useState(false);
    const [resolvingRevisionId, setResolvingRevisionId] = useState<
        number | null
    >(null);

    if (!item) return null;

    const canDecide =
        item.myDecision === 'pending' && item.defenseStatus === 'scheduled';
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
            {
                preserveScroll: true,
                onFinish: () => setResolvingRevisionId(null),
            },
        );
    }

    function handleClose() {
        setShowDecisionForm(false);
        onOpenChange(false);
    }

    return (
        <Sheet
            open={open}
            onOpenChange={(v) => {
                if (!v) {
                    setShowDecisionForm(false);
                }
                onOpenChange(v);
            }}
        >
            <SheetContent side="right" className="w-full gap-0 p-0 sm:max-w-xl">
                {/* ── Header ── */}
                <SheetHeader className="border-b bg-muted/20 px-6 py-4">
                    <div className="flex items-center gap-2 pr-6">
                        <Badge
                            variant="outline"
                            className={cn(
                                'shrink-0 rounded-full text-xs',
                                item.type === 'sempro'
                                    ? 'border-cyan-500/50 bg-cyan-500/10 text-cyan-700 dark:text-cyan-400'
                                    : 'border-purple-500/50 bg-purple-500/10 text-purple-700 dark:text-purple-400',
                            )}
                        >
                            {item.typeLabel} #{item.attemptNo}
                        </Badge>
                        <span
                            className={cn(
                                'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                statusColor[item.defenseStatus] ??
                                    'bg-muted text-muted-foreground',
                            )}
                        >
                            {statusLabel[item.defenseStatus] ??
                                item.defenseStatus}
                        </span>
                    </div>
                    <SheetTitle className="mt-1 text-base leading-snug">
                        {item.titleId}
                    </SheetTitle>
                    {item.titleEn && (
                        <SheetDescription className="truncate italic">
                            {item.titleEn}
                        </SheetDescription>
                    )}
                </SheetHeader>

                <ScrollArea className="h-[calc(100vh-8rem)]">
                    <div className="space-y-5 px-6 py-5">
                        {/* ── Info Mahasiswa & Jadwal ── */}
                        <div className="grid gap-2 rounded-xl border bg-muted/20 p-4 text-sm">
                            <div className="flex items-center gap-2 text-muted-foreground">
                                <User className="size-3.5 shrink-0" />
                                <span className="font-medium text-foreground">
                                    {item.studentName}
                                </span>
                                <span className="text-xs">
                                    {item.studentNim}
                                </span>
                            </div>
                            {item.scheduledFor && (
                                <div className="flex items-center gap-2 text-muted-foreground">
                                    <CalendarClock className="size-3.5 shrink-0" />
                                    <span>{item.scheduledFor}</span>
                                </div>
                            )}
                            <div className="flex items-center gap-2 text-muted-foreground">
                                <MapPin className="size-3.5 shrink-0" />
                                <span>
                                    {item.location} · {item.mode}
                                </span>
                            </div>
                        </div>

                        {/* ── Keputusan Penguji ── */}
                        <div>
                            <p className="mb-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Keputusan Penguji
                            </p>
                            <div className="divide-y rounded-xl border bg-card">
                                {/* My decision */}
                                <div className="flex flex-wrap items-center gap-2 px-4 py-3 text-sm">
                                    <span className="text-xs text-muted-foreground">
                                        Saya (
                                        {resolveDefenseRoleLabel(item.myRole)}):
                                    </span>
                                    <MyDecisionBadge
                                        decision={item.myDecision}
                                    />
                                    {item.myScore !== null && (
                                        <span className="flex items-center gap-1 text-xs font-semibold">
                                            <Star className="size-3 text-amber-500" />
                                            {item.myScore}
                                        </span>
                                    )}
                                    {item.myDecisionNotes && (
                                        <span className="text-xs text-muted-foreground italic">
                                            — {item.myDecisionNotes}
                                        </span>
                                    )}
                                </div>

                                {/* Other examiners */}
                                {item.otherExaminers.map((ex, i) => (
                                    <div
                                        key={i}
                                        className="flex flex-wrap items-center gap-2 px-4 py-3 text-xs"
                                    >
                                        <span className="text-muted-foreground">
                                            {ex.name} (
                                            {resolveDefenseRoleLabel(ex.role)}):
                                        </span>
                                        <Badge
                                            variant="soft"
                                            className={cn(
                                                'text-xs font-medium',
                                                ex.decision === 'pass'
                                                    ? 'bg-emerald-600/10 text-emerald-600'
                                                    : ex.decision ===
                                                        'pass_with_revision'
                                                      ? 'bg-amber-600/10 text-amber-600'
                                                      : ex.decision === 'fail'
                                                        ? 'bg-destructive/10 text-destructive'
                                                        : 'bg-muted text-muted-foreground',
                                            )}
                                        >
                                            {decisionLabel[ex.decision ?? ''] ??
                                                'Pending'}
                                        </Badge>
                                        {ex.score !== null && (
                                            <span className="flex items-center gap-1 font-semibold">
                                                <Star className="size-3 text-amber-500" />
                                                {ex.score}
                                            </span>
                                        )}
                                    </div>
                                ))}

                                {/* Average */}
                                {averageScore !== null && (
                                    <div className="flex flex-wrap items-center gap-2 px-4 py-3 text-xs">
                                        <span className="text-muted-foreground">
                                            Rata-rata:
                                        </span>
                                        <Badge
                                            variant="secondary"
                                            className="text-xs font-bold"
                                        >
                                            {averageScore.toFixed(2)}
                                        </Badge>
                                        {finalGrade && (
                                            <Badge
                                                variant="soft"
                                                className={cn(
                                                    'text-xs',
                                                    academicGradeClassName(
                                                        finalGrade,
                                                    ),
                                                )}
                                            >
                                                Grade {finalGrade}
                                            </Badge>
                                        )}
                                        <span className="text-muted-foreground">
                                            · Hasil:{' '}
                                            {resultLabel[item.defenseResult] ??
                                                item.defenseResult}
                                        </span>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* ── Revisi ── */}
                        {item.revisions.length > 0 && (
                            <div>
                                <p className="mb-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    Revisi ({item.revisions.length})
                                </p>
                                <div className="space-y-3">
                                    {item.revisions.map((rev) => (
                                        <div
                                            key={rev.id}
                                            className="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/40 dark:bg-amber-950/20"
                                        >
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Badge
                                                    variant="outline"
                                                    className="text-xs"
                                                >
                                                    {rev.statusLabel}
                                                </Badge>
                                                <span className="text-xs text-amber-700 dark:text-amber-300">
                                                    {rev.requestedBy}
                                                </span>
                                                {rev.dueAt && (
                                                    <span className="text-xs font-medium text-amber-600 dark:text-amber-400">
                                                        Batas: {rev.dueAt}
                                                    </span>
                                                )}
                                            </div>
                                            <p className="mt-2 text-xs leading-relaxed text-amber-900 dark:text-amber-100">
                                                {rev.notes}
                                            </p>
                                            {rev.canResolve && (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="secondary"
                                                    className="mt-3"
                                                    disabled={
                                                        resolvingRevisionId ===
                                                        rev.id
                                                    }
                                                    onClick={() =>
                                                        resolveRevision(rev.id)
                                                    }
                                                >
                                                    {resolvingRevisionId ===
                                                    rev.id
                                                        ? 'Menyimpan...'
                                                        : 'Tandai Revisi Selesai'}
                                                </Button>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* ── Form Keputusan ── */}
                        {canDecide && (
                            <div>
                                {!showDecisionForm ? (
                                    <Button
                                        size="sm"
                                        onClick={() =>
                                            setShowDecisionForm(true)
                                        }
                                        className="w-full sm:w-auto"
                                    >
                                        <Send className="size-3.5" />
                                        Input Keputusan Saya
                                    </Button>
                                ) : (
                                    <DecisionForm
                                        defenseId={item.defenseId}
                                        onClose={handleClose}
                                    />
                                )}
                            </div>
                        )}
                    </div>
                </ScrollArea>
            </SheetContent>
        </Sheet>
    );
}

// ── Main Page ────────────────────────────────────────────────────────
type TypeFilter = 'semua' | 'sempro' | 'sidang';
type StatusFilter = 'semua' | 'pending' | 'done';
type WorkspaceFilter = 'ujian' | 'bimbingan' | 'semua';

export default function DosenSeminarProposalPage() {
    const { defenses, workspaceEvents, flashMessage, errorMessage } = usePage<
        SharedData & PageProps
    >().props;

    const [search, setSearch] = useState('');
    const [typeFilter, setTypeFilter] = useState<TypeFilter>('semua');
    const [statusFilter, setStatusFilter] = useState<StatusFilter>('semua');
    const [workspaceFilter, setWorkspaceFilter] =
        useState<WorkspaceFilter>('ujian');
    const [selectedItem, setSelectedItem] = useState<DefenseItem | null>(null);
    const [sheetOpen, setSheetOpen] = useState(false);
    const [page, setPage] = useState(1);
    const PAGE_SIZE = 15;

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
            const aTime = a.scheduledFor
                ? new Date(a.scheduledFor).getTime()
                : 0;
            const bTime = b.scheduledFor
                ? new Date(b.scheduledFor).getTime()
                : 0;
            return bTime - aTime;
        });

    const filteredDefenses = useMemo(() => {
        const q = search.trim().toLowerCase();
        return sortByScheduledForDesc(
            defenses.filter((item) => {
                const matchType =
                    typeFilter === 'semua' || item.type === typeFilter;
                const isPending =
                    item.defenseStatus === 'scheduled' &&
                    item.myDecision === 'pending';
                const matchStatus =
                    statusFilter === 'semua' ||
                    (statusFilter === 'pending' && isPending) ||
                    (statusFilter === 'done' && !isPending);
                const matchSearch =
                    !q ||
                    [
                        item.studentName,
                        item.studentNim,
                        item.titleId,
                        item.titleEn,
                    ]
                        .filter(Boolean)
                        .some((v) => v.toLowerCase().includes(q));
                return matchType && matchStatus && matchSearch;
            }),
        );
    }, [defenses, search, typeFilter, statusFilter]);

    const totalPages = Math.max(
        1,
        Math.ceil(filteredDefenses.length / PAGE_SIZE),
    );
    const safePage = Math.min(page, totalPages);
    const paginatedDefenses = filteredDefenses.slice(
        (safePage - 1) * PAGE_SIZE,
        safePage * PAGE_SIZE,
    );

    const pendingCount = defenses.filter(
        (i) => i.defenseStatus === 'scheduled' && i.myDecision === 'pending',
    ).length;

    const filteredWorkspaceEvents = workspaceEvents.filter((e) =>
        workspaceFilter === 'semua' ? true : e.category === workspaceFilter,
    );

    function openItem(item: DefenseItem) {
        setSelectedItem(item);
        setSheetOpen(true);
    }

    function resetPage() {
        setPage(1);
    }

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

    const typeFilterTabs: { label: string; value: TypeFilter }[] = [
        { label: 'Semua', value: 'semua' },
        { label: 'Sempro', value: 'sempro' },
        { label: 'Sidang', value: 'sidang' },
    ];

    const statusFilterTabs: {
        label: string;
        value: StatusFilter;
        count?: number;
    }[] = [
        { label: 'Semua', value: 'semua' },
        { label: 'Menunggu Penilaian', value: 'pending', count: pendingCount },
        { label: 'Selesai / Riwayat', value: 'done' },
    ];

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
                        <AlertTitle>
                            {errorMessage ? 'Error' : 'Berhasil'}
                        </AlertTitle>
                        <AlertDescription>
                            {errorMessage || flashMessage}
                        </AlertDescription>
                    </Alert>
                )}

                {/* ── WORKSPACE CALENDAR ── */}
                <section>
                    <div className="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 className="text-base font-semibold">
                                Workspace Jadwal
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Lihat jadwal sempro, sidang, dan bimbingan dalam
                                satu kalender
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-1.5">
                            {workspaceFilterTabs.map((tab) => (
                                <button
                                    key={tab.value}
                                    type="button"
                                    onClick={() =>
                                        setWorkspaceFilter(tab.value)
                                    }
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

                {/* ── TABEL UNIFIED ── */}
                <section>
                    <div className="mb-4 flex items-center justify-between">
                        <div>
                            <h2 className="text-base font-semibold">
                                Daftar Sempro & Sidang
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Klik baris untuk melihat detail dan menginput
                                keputusan
                            </p>
                        </div>
                        {pendingCount > 0 && (
                            <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-600/10 px-3 py-1 text-xs font-bold text-amber-700 dark:text-amber-400">
                                <AlertTriangle className="size-3" />
                                {pendingCount} menunggu penilaian
                            </span>
                        )}
                    </div>

                    {/* Toolbar: search + filters */}
                    <div className="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        {/* Search */}
                        <div className="relative w-full max-w-xs">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(e) => {
                                    setSearch(e.target.value);
                                    resetPage();
                                }}
                                placeholder="Cari nama atau judul..."
                                className="h-8 pl-8 text-sm"
                            />
                        </div>

                        {/* Filter pills */}
                        <div className="flex flex-wrap gap-2">
                            <div className="flex gap-1">
                                {typeFilterTabs.map((tab) => (
                                    <button
                                        key={tab.value}
                                        type="button"
                                        onClick={() => {
                                            setTypeFilter(tab.value);
                                            resetPage();
                                        }}
                                        className={cn(
                                            'rounded-full px-3 py-1 text-xs font-medium transition-colors',
                                            typeFilter === tab.value
                                                ? 'bg-primary text-primary-foreground shadow-sm'
                                                : 'bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground',
                                        )}
                                    >
                                        {tab.label}
                                    </button>
                                ))}
                            </div>
                            <div className="flex gap-1">
                                {statusFilterTabs.map((tab) => (
                                    <button
                                        key={tab.value}
                                        type="button"
                                        onClick={() => {
                                            setStatusFilter(tab.value);
                                            resetPage();
                                        }}
                                        className={cn(
                                            'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium transition-colors',
                                            statusFilter === tab.value
                                                ? 'bg-primary text-primary-foreground shadow-sm'
                                                : 'bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground',
                                        )}
                                    >
                                        {tab.label}
                                        {tab.count !== undefined &&
                                            tab.count > 0 && (
                                                <span
                                                    className={cn(
                                                        'rounded-full px-1.5 py-0.5 text-[10px] leading-none font-bold',
                                                        statusFilter ===
                                                            tab.value
                                                            ? 'bg-white/20 text-white'
                                                            : 'bg-amber-600/15 text-amber-700',
                                                    )}
                                                >
                                                    {tab.count}
                                                </span>
                                            )}
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Table */}
                    {filteredDefenses.length === 0 ? (
                        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-16 text-center">
                            <Inbox className="mb-3 size-10 text-muted-foreground/40" />
                            <p className="text-sm font-semibold">
                                {defenses.length === 0
                                    ? 'Belum ada tugas penguji ujian'
                                    : 'Tidak ada item yang cocok'}
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {defenses.length === 0
                                    ? 'Anda belum ditugaskan sebagai penguji sempro atau sidang manapun.'
                                    : 'Coba ubah filter atau kata kunci pencarian.'}
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b bg-muted/30">
                                        <th className="px-4 py-2.5 text-left text-xs font-medium text-muted-foreground">
                                            Mahasiswa
                                        </th>
                                        <th className="hidden px-4 py-2.5 text-left text-xs font-medium text-muted-foreground md:table-cell">
                                            Judul
                                        </th>
                                        <th className="px-4 py-2.5 text-left text-xs font-medium text-muted-foreground">
                                            Tipe
                                        </th>
                                        <th className="hidden px-4 py-2.5 text-left text-xs font-medium text-muted-foreground lg:table-cell">
                                            Jadwal
                                        </th>
                                        <th className="px-4 py-2.5 text-left text-xs font-medium text-muted-foreground">
                                            Keputusan Saya
                                        </th>
                                        <th className="hidden px-4 py-2.5 text-left text-xs font-medium text-muted-foreground sm:table-cell">
                                            Status
                                        </th>
                                        <th className="w-8 px-4 py-2.5" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {paginatedDefenses.map((item) => {
                                        const isPending =
                                            item.defenseStatus ===
                                                'scheduled' &&
                                            item.myDecision === 'pending';
                                        return (
                                            <tr
                                                key={item.defenseId}
                                                className={cn(
                                                    'cursor-pointer transition-colors hover:bg-muted/30',
                                                    isPending &&
                                                        'bg-amber-50/40 dark:bg-amber-950/10',
                                                )}
                                                onClick={() => openItem(item)}
                                            >
                                                {/* Mahasiswa */}
                                                <td className="px-4 py-3">
                                                    <p className="leading-snug font-medium">
                                                        {item.studentName}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {item.studentNim}
                                                    </p>
                                                    {/* Mobile: judul */}
                                                    <p className="mt-1 line-clamp-1 text-xs text-muted-foreground md:hidden">
                                                        {item.titleId}
                                                    </p>
                                                </td>

                                                {/* Judul */}
                                                <td className="hidden max-w-[260px] px-4 py-3 md:table-cell">
                                                    <p className="line-clamp-2 text-xs leading-relaxed">
                                                        {item.titleId}
                                                    </p>
                                                </td>

                                                {/* Tipe */}
                                                <td className="px-4 py-3">
                                                    <Badge
                                                        variant="outline"
                                                        className={cn(
                                                            'shrink-0 rounded-full text-xs whitespace-nowrap',
                                                            item.type ===
                                                                'sempro'
                                                                ? 'border-cyan-500/50 bg-cyan-500/10 text-cyan-700 dark:text-cyan-400'
                                                                : 'border-purple-500/50 bg-purple-500/10 text-purple-700 dark:text-purple-400',
                                                        )}
                                                    >
                                                        {item.typeLabel} #
                                                        {item.attemptNo}
                                                    </Badge>
                                                    {/* Mobile: status */}
                                                    <div className="mt-1 sm:hidden">
                                                        <span
                                                            className={cn(
                                                                'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                                                statusColor[
                                                                    item
                                                                        .defenseStatus
                                                                ] ??
                                                                    'bg-muted text-muted-foreground',
                                                            )}
                                                        >
                                                            {statusLabel[
                                                                item
                                                                    .defenseStatus
                                                            ] ??
                                                                item.defenseStatus}
                                                        </span>
                                                    </div>
                                                </td>

                                                {/* Jadwal */}
                                                <td className="hidden px-4 py-3 lg:table-cell">
                                                    {item.scheduledFor ? (
                                                        <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                                            <Clock className="size-3 shrink-0" />
                                                            {item.scheduledFor}
                                                        </span>
                                                    ) : (
                                                        <span className="text-xs text-muted-foreground/60 italic">
                                                            —
                                                        </span>
                                                    )}
                                                </td>

                                                {/* Keputusan Saya */}
                                                <td className="px-4 py-3">
                                                    <MyDecisionBadge
                                                        decision={
                                                            item.myDecision
                                                        }
                                                    />
                                                    {item.myScore !== null && (
                                                        <span className="ml-1 inline-flex items-center gap-0.5 text-xs font-semibold">
                                                            <Star className="size-3 text-amber-500" />
                                                            {item.myScore}
                                                        </span>
                                                    )}
                                                </td>

                                                {/* Status */}
                                                <td className="hidden px-4 py-3 sm:table-cell">
                                                    <span
                                                        className={cn(
                                                            'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                                                            statusColor[
                                                                item
                                                                    .defenseStatus
                                                            ] ??
                                                                'bg-muted text-muted-foreground',
                                                        )}
                                                    >
                                                        {statusLabel[
                                                            item.defenseStatus
                                                        ] ?? item.defenseStatus}
                                                    </span>
                                                </td>

                                                {/* Chevron */}
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    <ChevronRight className="size-4" />
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                            <div className="flex items-center justify-between border-t px-4 py-2.5">
                                <p className="text-xs text-muted-foreground">
                                    {filteredDefenses.length === 0
                                        ? 'Tidak ada item'
                                        : `${(safePage - 1) * PAGE_SIZE + 1}–${Math.min(safePage * PAGE_SIZE, filteredDefenses.length)} dari ${filteredDefenses.length} item`}
                                </p>
                                {totalPages > 1 && (
                                    <div className="flex items-center gap-2">
                                        <button
                                            type="button"
                                            disabled={safePage <= 1}
                                            onClick={() =>
                                                setPage((p) =>
                                                    Math.max(1, p - 1),
                                                )
                                            }
                                            className="rounded px-2 py-1 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted disabled:pointer-events-none disabled:opacity-40"
                                        >
                                            ← Prev
                                        </button>
                                        <span className="text-xs text-muted-foreground">
                                            Hal {safePage} / {totalPages}
                                        </span>
                                        <button
                                            type="button"
                                            disabled={safePage >= totalPages}
                                            onClick={() =>
                                                setPage((p) =>
                                                    Math.min(totalPages, p + 1),
                                                )
                                            }
                                            className="rounded px-2 py-1 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted disabled:pointer-events-none disabled:opacity-40"
                                        >
                                            Next →
                                        </button>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}
                </section>
            </div>

            {/* ── Defense Detail Sheet ── */}
            <DefenseDetailSheet
                item={selectedItem}
                open={sheetOpen}
                onOpenChange={setSheetOpen}
            />

            {/* ── Schedule Detail Modal (from calendar click) ── */}
            <ScheduleDetailModal
                open={isDetailModalOpen}
                onOpenChange={setIsDetailModalOpen}
                schedule={selectedEvent}
                currentUserRole="dosen"
            />
        </DosenLayout>
    );
}
