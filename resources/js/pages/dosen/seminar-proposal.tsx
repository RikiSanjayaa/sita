import { Head, router, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    Clock,
    FileWarning,
    MapPin,
    Send,
    Star,
    User,
} from 'lucide-react';
import { useState } from 'react';

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
import { type BreadcrumbItem, type SharedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dosen/dashboard' },
    { title: 'Seminar Proposal', href: '/dosen/seminar-proposal' },
];

type OtherExaminer = {
    name: string;
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

type SemproItem = {
    semproId: number;
    studentName: string;
    studentNim: string;
    titleId: string;
    titleEn: string;
    semproStatus: string;
    scheduledFor: string | null;
    location: string;
    mode: string;
    myExaminerId: number;
    myOrder: number;
    myDecision: string | null;
    myScore: number | null;
    myDecisionNotes: string | null;
    otherExaminers: OtherExaminer[];
    revisions: Revision[];
};

type PageProps = {
    sempros: SemproItem[];
    flashMessage?: string | null;
};

const statusLabel: Record<string, string> = {
    draft: 'Draft',
    scheduled: 'Dijadwalkan',
    revision_open: 'Revisi',
    approved: 'Selesai',
};

const statusColor: Record<string, string> = {
    draft: 'bg-muted text-muted-foreground',
    scheduled: 'bg-primary/10 text-primary hover:bg-primary/20',
    revision_open: 'bg-destructive/10 text-destructive hover:bg-destructive/20',
    approved: 'bg-emerald-600/10 text-emerald-600 hover:bg-emerald-600/20',
};

const decisionLabel: Record<string, string> = {
    pending: 'Pending',
    needs_revision: 'Perlu Revisi',
    approved: 'Disetujui',
};

function DecisionForm({
    semproId,
    onClose,
}: {
    semproId: number;
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
            `/dosen/seminar-proposal/${semproId}/decision`,
            {
                decision,
                score: parseFloat(score),
                decision_notes: decisionNotes || null,
                revision_notes:
                    decision === 'needs_revision' ? revisionNotes : null,
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
                                decision === 'approved' ? 'default' : 'outline'
                            }
                            onClick={() => setDecision('approved')}
                            className={
                                decision === 'approved'
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
                                decision === 'needs_revision'
                                    ? 'default'
                                    : 'outline'
                            }
                            onClick={() => setDecision('needs_revision')}
                            className={
                                decision === 'needs_revision'
                                    ? 'bg-amber-600 hover:bg-amber-700'
                                    : ''
                            }
                        >
                            <FileWarning className="mr-1.5 size-3.5" />
                            Perlu Revisi
                        </Button>
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

            {decision === 'needs_revision' && (
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

function SemproCard({ item }: { item: SemproItem }) {
    const [showForm, setShowForm] = useState(false);
    const canDecide =
        item.myDecision === 'pending' &&
        (item.semproStatus === 'scheduled' ||
            item.semproStatus === 'revision_open');

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
                        className={`font-semibold ${statusColor[item.semproStatus] ?? 'bg-muted text-muted-foreground'}`}
                    >
                        {statusLabel[item.semproStatus] ?? item.semproStatus}
                    </Badge>
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
                        Keputusan Saya (Penguji {item.myOrder})
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
                                    item.myDecision === 'approved'
                                        ? 'bg-emerald-600/10 text-emerald-600 hover:bg-emerald-600/20'
                                        : item.myDecision === 'needs_revision'
                                          ? 'bg-amber-600/10 text-amber-600 hover:bg-amber-600/20'
                                          : 'bg-muted text-muted-foreground'
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
                                            ex.decision === 'approved'
                                                ? 'bg-emerald-600/10 text-emerald-600 hover:bg-emerald-600/20'
                                                : ex.decision ===
                                                    'needs_revision'
                                                  ? 'bg-amber-600/10 text-amber-600 hover:bg-amber-600/20'
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
                        semproId={item.semproId}
                        onClose={() => setShowForm(false)}
                    />
                )}
            </CardContent>
        </Card>
    );
}

export default function DosenSeminarProposalPage() {
    const { sempros, flashMessage } = usePage<SharedData & PageProps>().props;

    return (
        <DosenLayout
            breadcrumbs={breadcrumbs}
            title="Seminar Proposal"
            subtitle="Kelola sempro yang Anda menjadi dosen penguji"
        >
            <Head title="Seminar Proposal — Dosen" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-6 lg:gap-8 lg:py-8">
                {flashMessage && (
                    <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200">
                        {flashMessage}
                    </div>
                )}

                {sempros.length === 0 ? (
                    <div className="mt-6 rounded-xl border border-dashed bg-muted/20 p-8 text-center">
                        <span className="mx-auto mb-3 inline-flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
                            <FileWarning className="size-6" />
                        </span>
                        <p className="text-sm font-semibold">
                            Belum ada tugas penguji
                        </p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Anda belum ditugaskan sebagai penguji untuk kelas
                            seminar proposal manapun.
                        </p>
                    </div>
                ) : (
                    <div className="grid gap-4">
                        {sempros.map((item) => (
                            <SemproCard key={item.semproId} item={item} />
                        ))}
                    </div>
                )}
            </div>
        </DosenLayout>
    );
}
