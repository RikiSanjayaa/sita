import { Head, router, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    ChevronRight,
    Clock,
    FileWarning,
    Send,
    XCircle,
} from 'lucide-react';
import { useMemo, useState } from 'react';

import { type BimbinganEvent } from '@/components/bimbingan-calendar';
import { CollapsibleCalendarSection } from '@/components/collapsible-calendar-section';
import { DeadlineBadge } from '@/components/deadline-badge';
import {
    DataSection,
    DataToolbar,
    dataTh,
    stackedTable,
} from '@/components/patterns/data-section';
import { FilterPills } from '@/components/patterns/filter-pills';
import { KeyValueList } from '@/components/patterns/key-value-list';
import { PageHeader } from '@/components/patterns/page-header';
import { StatusBadge } from '@/components/patterns/status-badge';
import { StickyFilterBar } from '@/components/patterns/sticky-filter-bar';
import {
    type RevisionAnnotation,
    RevisionAnnotationLinks,
    type RevisionItem,
    RevisionItemList,
    type RevisionRound,
    RevisionRoundHistory,
    showRevisionSummary,
} from '@/components/revision-items';
import {
    type DraftItem,
    RevisionItemsEditor,
} from '@/components/revision-items-editor';
import { RevisionVerifyForm } from '@/components/revision-verify-form';
import { ScheduleDetailModal } from '@/components/schedule-detail-modal';
import {
    type ProjectNotes,
    ThesisNotesPanel,
} from '@/components/thesis-notes-panel';
import { Button } from '@/components/ui/button';
import {
    DataTableContainer,
    DataTableEmptyState,
    DataTablePagination,
    usePagination,
} from '@/components/ui/data-table';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { useUrlState } from '@/hooks/use-url-state';
import DosenLayout from '@/layouts/dosen-layout';
import {
    calculateAverageAcademicScore,
    resolveAcademicGrade,
} from '@/lib/academic-grade';
import { neutralAcademicTerminology as n } from '@/lib/academic-terminology';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dosen/dashboard' },
    {
        title: `${n.proposalExamShort} & ${n.finalExam}`,
        href: '/dosen/seminar-proposal',
    },
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
    /** ISO 8601, for the deadline badge; dueAt is the Indonesian display text. */
    dueAtIso: string | null;
    resolvedAt: string | null;
    resolutionNotes: string | null;
    requestedBy: string;
    canVerify: boolean;
    rounds: RevisionRound[];
    items: RevisionItem[];
    annotations: RevisionAnnotation[];
};

type MyRevisionItem = {
    location: string | null;
    description: string;
    priority: 'major' | 'minor' | null;
    priorityLabel: string | null;
};

type DefenseItem = {
    defenseId: number;
    projectId: number | null;
    type: 'sempro' | 'sidang';
    typeLabel: string;
    scoreRequired: boolean;
    attemptNo: number;
    studentName: string;
    studentNim: string;
    titleId: string;
    titleEn: string;
    defenseStatus: string;
    defenseResult: string;
    scheduledFor: string | null;
    /** ISO 8601, for sorting; scheduledFor is the Indonesian display text. */
    scheduledAt: string | null;
    location: string;
    mode: string;
    myExaminerId: number;
    myRole: string;
    myOrder: number;
    myDecision: string | null;
    myScore: number | null;
    myDecisionNotes: string | null;
    myRevisionNotes: string | null;
    myRevisionItems: MyRevisionItem[];
    myAnnotation: RevisionAnnotation | null;
    otherExaminers: OtherExaminer[];
    revisions: Revision[];
};

type PageProps = {
    defenses: DefenseItem[];
    projectNotes: Record<string, ProjectNotes>;
    workspaceEvents: BimbinganEvent[];
};

const statusLabel: Record<string, string> = {
    scheduled: 'Dijadwalkan',
    awaiting_finalization: 'Menunggu Finalisasi',
    completed: 'Selesai',
    cancelled: 'Dibatalkan',
};

const decisionLabel: Record<string, string> = {
    pending: 'Pending',
    pass: 'Lulus',
    fail: 'Tidak Lulus',
};

const resultLabel: Record<string, string> = {
    pending: 'Menunggu Finalisasi',
    pass: 'Lulus',
    fail: 'Tidak Lulus',
};

function resolveDefenseRoleLabel(role: string) {
    if (role === 'primary_supervisor') return 'Pembimbing 1';
    if (role === 'secondary_supervisor') return 'Pembimbing 2';
    return 'Penguji';
}

function getDecisionLabel(decision: string | null, defenseType: string) {
    if (!decision || decision === 'pending') return 'Pending';
    if (decision === 'pass_with_revision') {
        return defenseType === 'sidang' ? 'Lulus dengan Syarat' : 'Lulus';
    }

    return decisionLabel[decision] ?? 'Pending';
}

function getResultLabel(result: string, defenseType: string) {
    if (result === 'pass_with_revision') {
        return defenseType === 'sidang' ? 'Lulus dengan Syarat' : 'Lulus';
    }

    return resultLabel[result] ?? result;
}

function DefenseStatusBadge({ status }: { status: string }) {
    return (
        <StatusBadge domain="defense" status={status}>
            {statusLabel[status] ?? status}
        </StatusBadge>
    );
}

function DecisionBadge({
    decision,
    defenseType,
    own = false,
}: {
    decision: string | null;
    defenseType: string;
    own?: boolean;
}) {
    const status = decision ?? 'pending';
    const Icon =
        status === 'pending'
            ? Clock
            : status === 'fail'
              ? XCircle
              : CheckCircle2;

    return (
        <StatusBadge domain="result" status={status}>
            <Icon aria-hidden="true" className="size-3.5" />
            {own && status === 'pending'
                ? 'Belum Diputus'
                : getDecisionLabel(decision, defenseType)}
        </StatusBadge>
    );
}

// ── Decision Form (inside sheet) ────────────────────────────────────
function DecisionForm({
    defenseId,
    defenseType,
    scoreRequired,
    onClose,
}: {
    defenseId: number;
    defenseType: string;
    scoreRequired: boolean;
    onClose: () => void;
}) {
    const [decision, setDecision] = useState<string>('');
    const [score, setScore] = useState<string>('');
    const [decisionNotes, setDecisionNotes] = useState<string>('');
    const [revisionNotes, setRevisionNotes] = useState<string>('');
    const [revisionItems, setRevisionItems] = useState<DraftItem[]>([]);
    const [annotation, setAnnotation] = useState<File | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const { errors } = usePage<SharedData>().props;
    const revisionErrors = Object.entries(errors ?? {})
        .filter(([key]) => key.startsWith('revision_'))
        .map(([, message]) => String(message));
    const filledItems = revisionItems.filter(
        (item) => item.description.trim() !== '',
    );
    const showRevisionNotes =
        decision === 'pass_with_revision' ||
        (defenseType === 'sempro' && decision === 'pass');
    const revisionNotesRequired =
        decision === 'pass_with_revision' && filledItems.length === 0;
    const notesRequired = scoreRequired && defenseType === 'sempro';

    function submit() {
        if (!decision || (scoreRequired && !score)) return;
        if (notesRequired && decisionNotes.trim() === '') return;
        if (revisionNotesRequired && revisionNotes.trim() === '') return;

        setSubmitting(true);
        router.post(
            `/dosen/seminar-proposal/${defenseId}/decision`,
            {
                decision,
                ...(scoreRequired ? { score: parseFloat(score) } : {}),
                decision_notes: decisionNotes.trim() || null,
                revision_notes: showRevisionNotes
                    ? revisionNotes.trim() || null
                    : null,
                ...(showRevisionNotes && filledItems.length > 0
                    ? {
                          revision_items: filledItems.map((item) => ({
                              location: item.location.trim() || null,
                              description: item.description.trim(),
                              priority:
                                  item.priority === 'none'
                                      ? null
                                      : item.priority,
                          })),
                      }
                    : {}),
                ...(showRevisionNotes && annotation
                    ? { revision_annotation: annotation }
                    : {}),
            },
            {
                forceFormData: annotation !== null,
                onFinish: () => setSubmitting(false),
                onSuccess: () => onClose(),
            },
        );
    }

    return (
        <section className="border-t pt-5">
            <h3 className="mb-4 text-base font-semibold">
                Input Keputusan Saya
            </h3>

            <div className="space-y-4">
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-1.5">
                        <Label id="sheet-decision-label">Keputusan *</Label>
                        <div
                            role="group"
                            aria-labelledby="sheet-decision-label"
                            className="flex flex-wrap gap-2"
                        >
                            <Button
                                type="button"
                                size="sm"
                                variant={
                                    decision === 'pass' ? 'default' : 'outline'
                                }
                                aria-pressed={decision === 'pass'}
                                onClick={() => setDecision('pass')}
                                className="max-md:h-11"
                            >
                                <CheckCircle2 className="size-4" /> Lulus
                            </Button>
                            {defenseType !== 'sempro' && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant={
                                        decision === 'pass_with_revision'
                                            ? 'default'
                                            : 'outline'
                                    }
                                    aria-pressed={
                                        decision === 'pass_with_revision'
                                    }
                                    onClick={() =>
                                        setDecision('pass_with_revision')
                                    }
                                    className="max-md:h-11"
                                >
                                    <FileWarning className="size-4" /> Lulus
                                    dengan Syarat
                                </Button>
                            )}
                            <Button
                                type="button"
                                size="sm"
                                variant={
                                    decision === 'fail'
                                        ? 'destructive'
                                        : 'outline'
                                }
                                aria-pressed={decision === 'fail'}
                                onClick={() => setDecision('fail')}
                                className="max-md:h-11"
                            >
                                <XCircle className="size-4" /> Tidak Lulus
                            </Button>
                        </div>
                    </div>

                    {scoreRequired && (
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
                                className="max-md:h-11"
                            />
                        </div>
                    )}
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor="sheet-decision-notes">
                        Catatan Keputusan{notesRequired ? ' *' : ''}
                    </Label>
                    <Textarea
                        id="sheet-decision-notes"
                        rows={3}
                        value={decisionNotes}
                        onChange={(e) => setDecisionNotes(e.target.value)}
                        placeholder="Catatan umum untuk mahasiswa..."
                        className="resize-none"
                    />
                </div>

                {showRevisionNotes && (
                    <div className="grid gap-1.5">
                        <Label htmlFor="sheet-revision-notes">
                            Catatan Revisi
                            {revisionNotesRequired ? ' *' : ' (opsional)'}
                        </Label>
                        <Textarea
                            id="sheet-revision-notes"
                            rows={3}
                            value={revisionNotes}
                            onChange={(e) => setRevisionNotes(e.target.value)}
                            placeholder="Ringkasan revisi untuk mahasiswa..."
                            className="resize-none"
                        />
                    </div>
                )}

                {showRevisionNotes && (
                    <>
                        <RevisionItemsEditor
                            items={revisionItems}
                            onChange={setRevisionItems}
                        />

                        <div className="grid gap-1.5">
                            <Label htmlFor="sheet-revision-annotation">
                                Naskah Beranotasi (PDF, opsional)
                            </Label>
                            <Input
                                id="sheet-revision-annotation"
                                type="file"
                                accept="application/pdf,.pdf"
                                onChange={(e) =>
                                    setAnnotation(
                                        e.currentTarget.files?.[0] ?? null,
                                    )
                                }
                                className="cursor-pointer max-md:h-11 max-md:py-2"
                            />
                            <p className="text-xs text-muted-foreground">
                                Unggah naskah yang sudah Anda beri catatan.
                                Ukuran maksimal 20 MB.
                            </p>
                        </div>
                    </>
                )}

                {revisionErrors.length > 0 && (
                    <ul role="alert" className="text-sm text-destructive">
                        {revisionErrors.map((message) => (
                            <li key={message}>{message}</li>
                        ))}
                    </ul>
                )}
            </div>

            <div className="sticky bottom-0 -mx-6 mt-5 -mb-5 flex gap-2 border-t bg-background px-6 py-3">
                <Button
                    onClick={submit}
                    disabled={
                        submitting ||
                        !decision ||
                        (scoreRequired && !score) ||
                        (notesRequired && decisionNotes.trim() === '') ||
                        (revisionNotesRequired && revisionNotes.trim() === '')
                    }
                    className="flex-1 max-md:h-11 sm:flex-none"
                >
                    <Send className="size-4" />
                    {submitting ? 'Menyimpan...' : 'Submit Keputusan'}
                </Button>
                <Button
                    variant="ghost"
                    onClick={onClose}
                    className="max-md:h-11"
                >
                    Batal
                </Button>
            </div>
        </section>
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
    const { projectNotes } = usePage<SharedData & PageProps>().props;
    const [showDecisionForm, setShowDecisionForm] = useState(false);

    if (!item) return null;

    const canDecide =
        item.myDecision === 'pending' && item.defenseStatus === 'scheduled';
    const averageScore = calculateAverageAcademicScore([
        item.myScore,
        ...item.otherExaminers.map((e) => e.score),
    ]);
    const finalGrade = resolveAcademicGrade(averageScore);

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
                <SheetHeader className="gap-2 border-b px-6 py-4 pr-12">
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <span className="text-sm font-medium">
                            {item.typeLabel} #{item.attemptNo}
                        </span>
                        <DefenseStatusBadge status={item.defenseStatus} />
                    </div>
                    <SheetTitle className="text-lg leading-snug text-pretty">
                        {item.titleId}
                    </SheetTitle>
                    {item.titleEn && (
                        <SheetDescription className="text-pretty italic">
                            {item.titleEn}
                        </SheetDescription>
                    )}
                </SheetHeader>

                <div className="min-h-0 flex-1 overflow-y-auto">
                    <div className="space-y-6 px-6 py-5">
                        <KeyValueList
                            layout="inline"
                            items={[
                                {
                                    key: 'mahasiswa',
                                    label: 'Mahasiswa',
                                    value: `${item.studentName} (${item.studentNim})`,
                                },
                                {
                                    key: 'jadwal',
                                    label: 'Jadwal',
                                    value: item.scheduledFor ?? (
                                        <span className="text-muted-foreground">
                                            -
                                        </span>
                                    ),
                                },
                                {
                                    key: 'lokasi',
                                    label: 'Lokasi',
                                    value: `${item.location} · ${item.mode}`,
                                },
                            ]}
                        />

                        <section>
                            <h3 className="mb-2 text-base font-semibold">
                                Keputusan Penguji
                            </h3>
                            <ul className="divide-y border-y text-sm">
                                <li className="space-y-1 py-3">
                                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                                        <span className="font-medium">
                                            Saya (
                                            {resolveDefenseRoleLabel(
                                                item.myRole,
                                            )}
                                            )
                                        </span>
                                        <DecisionBadge
                                            own
                                            decision={item.myDecision}
                                            defenseType={item.type}
                                        />
                                        {item.myScore !== null && (
                                            <span className="font-medium tabular-nums">
                                                Nilai {item.myScore}
                                            </span>
                                        )}
                                    </div>
                                    {item.myDecisionNotes && (
                                        <p className="text-pretty text-muted-foreground">
                                            {item.myDecisionNotes}
                                        </p>
                                    )}
                                    {item.myRevisionNotes && (
                                        <p className="text-pretty">
                                            <span className="font-medium">
                                                Catatan revisi:
                                            </span>{' '}
                                            {item.myRevisionNotes}
                                        </p>
                                    )}
                                    {item.myRevisionItems.length > 0 && (
                                        <ol className="list-decimal space-y-1 pl-5">
                                            {item.myRevisionItems.map(
                                                (entry, index) => (
                                                    <li
                                                        key={index}
                                                        className="text-pretty"
                                                    >
                                                        {entry.location && (
                                                            <span className="font-medium">
                                                                {entry.location}
                                                                {': '}
                                                            </span>
                                                        )}
                                                        {entry.description}
                                                        {entry.priorityLabel &&
                                                            ` (${entry.priorityLabel})`}
                                                    </li>
                                                ),
                                            )}
                                        </ol>
                                    )}
                                    {item.myAnnotation && (
                                        <RevisionAnnotationLinks
                                            annotations={[item.myAnnotation]}
                                        />
                                    )}
                                </li>

                                {item.otherExaminers.map((ex) => (
                                    <li
                                        key={`${ex.order}-${ex.name}`}
                                        className="flex flex-wrap items-center gap-x-3 gap-y-1.5 py-3"
                                    >
                                        <span className="font-medium">
                                            {ex.name} (
                                            {resolveDefenseRoleLabel(ex.role)})
                                        </span>
                                        <DecisionBadge
                                            decision={ex.decision}
                                            defenseType={item.type}
                                        />
                                        {ex.score !== null && (
                                            <span className="font-medium tabular-nums">
                                                Nilai {ex.score}
                                            </span>
                                        )}
                                    </li>
                                ))}

                                {averageScore !== null && (
                                    <li className="flex flex-wrap items-center gap-x-3 gap-y-1.5 py-3">
                                        <span className="font-medium tabular-nums">
                                            Rata-rata {averageScore.toFixed(2)}
                                            {finalGrade
                                                ? ` (Grade ${finalGrade})`
                                                : ''}
                                        </span>
                                        <StatusBadge
                                            domain="result"
                                            status={item.defenseResult}
                                        >
                                            Hasil:{' '}
                                            {getResultLabel(
                                                item.defenseResult,
                                                item.type,
                                            )}
                                        </StatusBadge>
                                    </li>
                                )}
                            </ul>
                        </section>

                        {item.revisions.length > 0 && (
                            <section>
                                <h3 className="mb-2 text-base font-semibold">
                                    Revisi ({item.revisions.length})
                                </h3>
                                <ul className="divide-y border-y text-sm">
                                    {item.revisions.map((rev) => (
                                        <li key={rev.id} className="py-3">
                                            <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                                                <StatusBadge
                                                    domain="revision"
                                                    status={rev.status}
                                                >
                                                    {rev.statusLabel}
                                                </StatusBadge>
                                                <span className="text-muted-foreground">
                                                    {rev.requestedBy}
                                                </span>
                                                {rev.dueAt &&
                                                    rev.status !==
                                                        'resolved' && (
                                                        <DeadlineBadge
                                                            dueAt={rev.dueAtIso}
                                                            status={rev.status}
                                                        />
                                                    )}
                                            </div>
                                            {showRevisionSummary(
                                                rev.notes,
                                                rev.items,
                                            ) && (
                                                <p className="mt-2 leading-relaxed text-pretty">
                                                    {rev.notes}
                                                </p>
                                            )}
                                            <RevisionItemList
                                                items={
                                                    rev.canVerify
                                                        ? rev.items.filter(
                                                              (item) =>
                                                                  item.status !==
                                                                  'addressed',
                                                          )
                                                        : rev.items
                                                }
                                            />
                                            <RevisionAnnotationLinks
                                                annotations={rev.annotations}
                                            />
                                            <RevisionRoundHistory
                                                rounds={rev.rounds}
                                            />
                                            {rev.canVerify && (
                                                <RevisionVerifyForm
                                                    revisionId={rev.id}
                                                    items={rev.items}
                                                />
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        )}

                        <section>
                            <h3 className="mb-2 text-base font-semibold">
                                Catatan
                            </h3>
                            <ThesisNotesPanel
                                projectId={item.projectId}
                                data={
                                    item.projectId === null
                                        ? undefined
                                        : projectNotes[item.projectId]
                                }
                            />
                        </section>

                        {canDecide &&
                            (!showDecisionForm ? (
                                <Button
                                    onClick={() => setShowDecisionForm(true)}
                                    className="w-full max-md:h-11 sm:w-auto"
                                >
                                    <Send className="size-4" />
                                    Input Keputusan Saya
                                </Button>
                            ) : (
                                <DecisionForm
                                    defenseId={item.defenseId}
                                    defenseType={item.type}
                                    scoreRequired={item.scoreRequired}
                                    onClose={handleClose}
                                />
                            ))}
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    );
}

// ── Main Page ────────────────────────────────────────────────────────
type TypeFilter = 'semua' | 'sempro' | 'sidang';
type StatusFilter = 'semua' | 'pending' | 'revisi' | 'done';
type WorkspaceFilter = 'ujian' | 'bimbingan' | 'semua';

export default function DosenSeminarProposalPage() {
    const {
        defenses,
        workspaceEvents,
        academicTerminology: n,
    } = usePage<SharedData & PageProps>().props;

    const [search, setSearch] = useUrlState('search', '');
    const [typeFilter, setTypeFilter] = useUrlState<TypeFilter>(
        'type',
        'semua',
    );
    const [statusFilter, setStatusFilter] = useUrlState<StatusFilter>(
        'status',
        'semua',
    );
    const [workspaceFilter, setWorkspaceFilter] = useUrlState<WorkspaceFilter>(
        'calendar',
        'ujian',
    );
    const [selectedItem, setSelectedItem] = useState<DefenseItem | null>(null);
    const [sheetOpen, setSheetOpen] = useState(false);
    const pageState = useUrlState('page', 1);

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

    const filteredDefenses = useMemo(() => {
        const q = search.trim().toLowerCase();
        const time = (item: DefenseItem) =>
            item.scheduledAt ? new Date(item.scheduledAt).getTime() : 0;

        return defenses
            .filter((item) => {
                const matchType =
                    typeFilter === 'semua' || item.type === typeFilter;
                const isPending =
                    item.defenseStatus === 'scheduled' &&
                    item.myDecision === 'pending';
                const awaitsVerification = item.revisions.some(
                    (revision) =>
                        revision.canVerify && revision.status === 'submitted',
                );
                const matchStatus =
                    statusFilter === 'semua' ||
                    (statusFilter === 'pending' && isPending) ||
                    (statusFilter === 'revisi' && awaitsVerification) ||
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
            })
            .sort((a, b) => time(b) - time(a));
    }, [defenses, search, typeFilter, statusFilter]);

    const {
        page,
        setPage,
        pageSize,
        setPageSize,
        totalPages,
        paginated,
        totalItems,
    } = usePagination(
        filteredDefenses,
        15,
        [search, typeFilter, statusFilter, defenses.length],
        pageState,
    );

    const pendingCount = defenses.filter(
        (i) => i.defenseStatus === 'scheduled' && i.myDecision === 'pending',
    ).length;
    const verificationCount = defenses.filter((i) =>
        i.revisions.some(
            (revision) => revision.canVerify && revision.status === 'submitted',
        ),
    ).length;

    const filteredWorkspaceEvents = workspaceEvents.filter((e) =>
        workspaceFilter === 'semua' ? true : e.category === workspaceFilter,
    );

    function openItem(item: DefenseItem) {
        setSelectedItem(item);
        setSheetOpen(true);
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
        { label: `${n.proposalExamShort} / ${n.finalExam}`, value: 'ujian' },
        { label: 'Bimbingan', value: 'bimbingan' },
        { label: 'Semua', value: 'semua' },
    ];

    const typeFilterTabs: { label: string; value: TypeFilter }[] = [
        { label: 'Semua', value: 'semua' },
        { label: n.proposalExamShort, value: 'sempro' },
        { label: n.finalExam, value: 'sidang' },
    ];

    const statusFilterTabs: {
        label: string;
        value: StatusFilter;
        count?: number;
    }[] = [
        { label: 'Semua', value: 'semua' },
        {
            label: 'Menunggu Penilaian',
            value: 'pending',
            count: pendingCount > 0 ? pendingCount : undefined,
        },
        {
            label: 'Revisi Menunggu Verifikasi',
            value: 'revisi',
            count: verificationCount > 0 ? verificationCount : undefined,
        },
        { label: 'Selesai / Riwayat', value: 'done' },
    ];

    return (
        <DosenLayout
            breadcrumbs={breadcrumbs}
            title={`${n.proposalExamShort} & ${n.finalExam}`}
            subtitle={`Jadwal dan penilaian ${n.proposalExamShort} serta ${n.finalExam}`}
        >
            <Head title={`${n.proposalExamShort} & ${n.finalExam} — Dosen`} />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 md:px-6 lg:py-8">
                <PageHeader
                    title={`${n.proposalExamShort} & ${n.finalExam}`}
                    description={`Jadwal dan penilaian ${n.proposalExamShort} serta ${n.finalExam}`}
                />

                <CollapsibleCalendarSection
                    storageKey="dosen-seminar-proposal"
                    title="Workspace Jadwal"
                    description={
                        <>
                            Lihat jadwal {n.proposalExamShort}, {n.finalExam},
                            dan bimbingan dalam satu kalender
                        </>
                    }
                    toolbar={
                        <FilterPills
                            label="Jenis jadwal di kalender"
                            value={workspaceFilter}
                            onValueChange={setWorkspaceFilter}
                            itemClassName="max-md:h-11 max-md:px-4"
                            options={workspaceFilterTabs}
                        />
                    }
                    events={filteredWorkspaceEvents}
                    onEventClick={handleEventClick}
                    defaultView="calendar"
                    showLegend={false}
                />

                <DataSection
                    title="Daftar ujian"
                    summary={
                        pendingCount > 0
                            ? `${pendingCount} menunggu penilaian`
                            : undefined
                    }
                >
                    <StickyFilterBar>
                        <DataToolbar
                            search={search}
                            onSearchChange={setSearch}
                            placeholder="Cari nama, NIM, atau judul..."
                        />
                        <div className="flex flex-col gap-3 md:flex-row md:flex-wrap md:items-center md:gap-x-4 md:gap-y-2">
                            <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span className="text-xs font-medium text-muted-foreground max-md:w-full">
                                    Jenis
                                </span>
                                <FilterPills
                                    label="Jenis ujian"
                                    value={typeFilter}
                                    onValueChange={setTypeFilter}
                                    itemClassName="max-md:h-11 max-md:px-4"
                                    options={typeFilterTabs}
                                />
                            </div>
                            <span
                                aria-hidden="true"
                                className="h-5 w-px bg-border max-md:hidden"
                            />
                            <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span className="text-xs font-medium text-muted-foreground max-md:w-full">
                                    Status
                                </span>
                                <FilterPills
                                    label="Status keputusan"
                                    value={statusFilter}
                                    onValueChange={setStatusFilter}
                                    itemClassName="max-md:h-11 max-md:px-4"
                                    options={statusFilterTabs}
                                />
                            </div>
                        </div>
                    </StickyFilterBar>

                    {filteredDefenses.length === 0 ? (
                        <DataTableEmptyState
                            title={
                                defenses.length === 0
                                    ? 'Belum ada tugas penguji ujian'
                                    : 'Tidak ada item yang cocok'
                            }
                            description={
                                defenses.length === 0
                                    ? `Anda belum ditugaskan sebagai penguji ${n.proposalExamShort} atau ${n.finalExam} manapun.`
                                    : 'Coba ubah filter atau kata kunci pencarian.'
                            }
                        />
                    ) : (
                        <DataTableContainer
                            pagination={
                                <DataTablePagination
                                    currentPage={page}
                                    totalPages={totalPages}
                                    totalItems={totalItems}
                                    pageSize={pageSize}
                                    onPageChange={setPage}
                                    onPageSizeChange={setPageSize}
                                    itemLabel="ujian"
                                />
                            }
                        >
                            <table
                                className={cn(
                                    stackedTable.table,
                                    'md:min-w-[880px]',
                                )}
                            >
                                <thead className={stackedTable.thead}>
                                    <tr className="border-b bg-muted/40">
                                        <th className={dataTh}>Mahasiswa</th>
                                        <th className={dataTh}>Judul</th>
                                        <th className={dataTh}>Ujian</th>
                                        <th className={dataTh}>Jadwal</th>
                                        <th className={dataTh}>
                                            Keputusan saya
                                        </th>
                                        <th className={dataTh}>Status</th>
                                        <th className={dataTh}>
                                            <span className="sr-only">
                                                Aksi
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody
                                    className={cn(
                                        stackedTable.tbody,
                                        'md:divide-y',
                                    )}
                                >
                                    {paginated.map((item) => (
                                        <tr
                                            key={item.defenseId}
                                            className={cn(
                                                stackedTable.tr,
                                                'cursor-pointer align-top transition-colors hover:bg-muted/40',
                                            )}
                                            onClick={() => openItem(item)}
                                        >
                                            <td
                                                className={stackedTable.tdPlain}
                                            >
                                                <div className="font-medium text-foreground">
                                                    {item.studentName}
                                                </div>
                                                <div className="text-muted-foreground tabular-nums">
                                                    {item.studentNim}
                                                </div>
                                            </td>
                                            <td
                                                className={cn(
                                                    stackedTable.tdDetail,
                                                    'md:max-w-[280px]',
                                                )}
                                            >
                                                <span
                                                    title={item.titleId}
                                                    className="line-clamp-2 text-pretty"
                                                >
                                                    {item.titleId}
                                                </span>
                                            </td>
                                            <td
                                                className={cn(
                                                    stackedTable.td,
                                                    'whitespace-nowrap',
                                                )}
                                            >
                                                {item.typeLabel} #
                                                {item.attemptNo}
                                            </td>
                                            <td
                                                className={cn(
                                                    stackedTable.td,
                                                    'text-muted-foreground',
                                                    !item.scheduledFor &&
                                                        'max-md:hidden',
                                                )}
                                            >
                                                {item.scheduledFor ?? '-'}
                                            </td>
                                            <td
                                                data-label="Keputusan saya"
                                                className={stackedTable.td}
                                            >
                                                <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                                    <DecisionBadge
                                                        own
                                                        decision={
                                                            item.myDecision
                                                        }
                                                        defenseType={item.type}
                                                    />
                                                    {item.myScore !== null && (
                                                        <span className="font-medium tabular-nums">
                                                            <span className="sr-only">
                                                                Nilai{' '}
                                                            </span>
                                                            {item.myScore}
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                            <td
                                                className={stackedTable.tdAside}
                                            >
                                                <DefenseStatusBadge
                                                    status={item.defenseStatus}
                                                />
                                            </td>
                                            <td
                                                className={
                                                    stackedTable.tdActions
                                                }
                                            >
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        aria-label={`Buka detail ${item.studentName}`}
                                                        className="max-md:size-11 max-md:px-0"
                                                        onClick={(event) => {
                                                            event.stopPropagation();
                                                            openItem(item);
                                                        }}
                                                    >
                                                        <span className="max-md:sr-only">
                                                            Buka
                                                        </span>
                                                        <ChevronRight
                                                            aria-hidden="true"
                                                            className="size-4"
                                                        />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </DataTableContainer>
                    )}
                </DataSection>
            </div>

            <DefenseDetailSheet
                item={selectedItem}
                open={sheetOpen}
                onOpenChange={setSheetOpen}
            />

            <ScheduleDetailModal
                open={isDetailModalOpen}
                onOpenChange={setIsDetailModalOpen}
                schedule={selectedEvent}
                currentUserRole="dosen"
            />
        </DosenLayout>
    );
}
