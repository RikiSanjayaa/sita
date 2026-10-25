import { Download } from 'lucide-react';

import { DeadlineBadge } from '@/components/deadline-badge';
import { StatusBadge } from '@/components/patterns/status-badge';
import {
    type RevisionAnnotation,
    RevisionAnnotationLinks,
    type RevisionItem,
    RevisionItemList,
    showRevisionSummary,
} from '@/components/revision-items';
import { RevisionResponseForm } from '@/components/revision-response-form';
import { RevisionVerifyForm } from '@/components/revision-verify-form';
import { Button } from '@/components/ui/button';

export type RevisionLogItem = RevisionItem & {
    verifiedAt: string | null;
    verifiedBy: string | null;
};

type LogRound = {
    roundNo: number;
    status: string;
    statusLabel: string;
    openedAt: string | null;
    submittedAt: string | null;
    reviewedAt: string | null;
    itemState: 'recorded' | 'latest' | 'current';
    itemStateLabel: string;
    items: RevisionLogItem[];
    annotations: RevisionAnnotation[];
    manuscript: RevisionAnnotation | null;
};

type LogRevision = {
    id: number;
    requestedByUserId: number | null;
    status: string;
    statusLabel: string;
    notes: string | null;
    requestedAt: string | null;
    dueAt: string | null;
    dueAtIso: string | null;
    submittedAt: string | null;
    resolvedAt: string | null;
    resolvedBy: string | null;
    resolutionNotes: string | null;
    items: RevisionLogItem[];
    rounds: LogRound[];
    unassignedItems: RevisionLogItem[];
    unassignedAnnotations: RevisionAnnotation[];
};

export type DefenseRevisionLogData = {
    examiners: {
        id: number | null;
        name: string;
        revisions: LogRevision[];
    }[];
    overrides: {
        id: number;
        actor: string;
        description: string | null;
        occurredAt: string | null;
    }[];
};

const roundTone: Record<string, string> = {
    awaiting_student: 'open',
    awaiting_review: 'submitted',
    closed: 'resolved',
};

function LogItems({ items }: { items: RevisionLogItem[] }) {
    return (
        <div>
            <RevisionItemList items={items} />
            {items
                .filter((item) => item.verifiedAt)
                .map((item) => (
                    <p
                        key={item.id}
                        className="mt-2 text-xs text-muted-foreground"
                    >
                        Verifikasi {item.location ?? item.description}:{' '}
                        {item.verifiedAt}
                        {item.verifiedBy && ` oleh ${item.verifiedBy}`}
                    </p>
                ))}
        </div>
    );
}

export function DefenseRevisionLog({
    log,
    action,
}: {
    log: DefenseRevisionLogData;
    action?: { type: 'respond' | 'verify'; revisionIds: number[] };
}) {
    return (
        <section
            aria-label="Log revisi ujian"
            className="min-w-0 space-y-4 [overflow-wrap:anywhere]"
        >
            <h3 className="text-base font-semibold">Log revisi</h3>
            {log.examiners.length === 0 && log.overrides.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Belum ada catatan revisi untuk ujian ini.
                </p>
            ) : (
                <p className="text-xs text-muted-foreground">
                    Riwayat per putaran dan dokumen, dikelompokkan per penguji.
                </p>
            )}
            {log.examiners.map((examiner) => (
                <div
                    key={examiner.id ?? 'deleted'}
                    className="min-w-0 space-y-3"
                >
                    <h4 className="text-sm font-semibold">{examiner.name}</h4>
                    {examiner.revisions.map((revision) => {
                        const actionable =
                            action?.revisionIds.includes(revision.id) ?? false;
                        const visibleItems = (items: RevisionLogItem[]) =>
                            items.filter(
                                (item) =>
                                    !actionable ||
                                    (action?.type === 'respond'
                                        ? !['open', 'reopened'].includes(
                                              item.status,
                                          )
                                        : item.status !== 'addressed'),
                            );
                        return (
                            <div
                                key={revision.id}
                                className="min-w-0 space-y-3 border-y py-3 text-sm"
                            >
                                <div className="flex flex-wrap items-center gap-2">
                                    <StatusBadge
                                        domain="revision"
                                        status={revision.status}
                                    >
                                        {revision.statusLabel}
                                    </StatusBadge>
                                    {revision.requestedAt && (
                                        <span className="text-xs text-muted-foreground">
                                            Diminta {revision.requestedAt}
                                        </span>
                                    )}
                                    {revision.dueAt &&
                                        revision.status !== 'resolved' && (
                                            <DeadlineBadge
                                                dueAt={revision.dueAtIso}
                                                status={revision.status}
                                            />
                                        )}
                                </div>
                                {revision.dueAt && (
                                    <p className="text-xs text-muted-foreground">
                                        Batas revisi: {revision.dueAt}
                                    </p>
                                )}
                                {showRevisionSummary(
                                    revision.notes,
                                    revision.items,
                                ) && (
                                    <p className="leading-relaxed whitespace-pre-line">
                                        {revision.notes}
                                    </p>
                                )}
                                {revision.rounds.map((round) => (
                                    <div
                                        key={round.roundNo}
                                        className="min-w-0 space-y-2 border-t pt-3"
                                    >
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h5 className="font-medium">
                                                Putaran {round.roundNo}
                                            </h5>
                                            <StatusBadge
                                                domain="revision"
                                                status={
                                                    roundTone[round.status] ??
                                                    round.status
                                                }
                                            >
                                                {round.statusLabel}
                                            </StatusBadge>
                                        </div>
                                        <dl className="grid gap-1 text-xs text-muted-foreground">
                                            {round.openedAt && (
                                                <div>
                                                    <dt className="inline">
                                                        Dibuka:{' '}
                                                    </dt>
                                                    <dd className="inline">
                                                        {round.openedAt}
                                                    </dd>
                                                </div>
                                            )}
                                            {round.submittedAt && (
                                                <div>
                                                    <dt className="inline">
                                                        Dikirim:{' '}
                                                    </dt>
                                                    <dd className="inline">
                                                        {round.submittedAt}
                                                    </dd>
                                                </div>
                                            )}
                                            {round.reviewedAt && (
                                                <div>
                                                    <dt className="inline">
                                                        Diverifikasi:{' '}
                                                    </dt>
                                                    <dd className="inline">
                                                        {round.reviewedAt}
                                                    </dd>
                                                </div>
                                            )}
                                        </dl>
                                        <p className="text-xs text-muted-foreground">
                                            {round.itemStateLabel}
                                        </p>
                                        <LogItems
                                            items={
                                                round.itemState === 'recorded'
                                                    ? round.items
                                                    : visibleItems(round.items)
                                            }
                                        />
                                        <RevisionAnnotationLinks
                                            annotations={round.annotations}
                                        />
                                        {round.manuscript && (
                                            <div className="flex flex-wrap items-center gap-2">
                                                <a
                                                    href={
                                                        round.manuscript.viewUrl
                                                    }
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="min-w-0 underline-offset-4 hover:underline"
                                                >
                                                    Naskah perbaikan:{' '}
                                                    {round.manuscript.fileName}
                                                </a>
                                                <Button
                                                    asChild
                                                    variant="ghost"
                                                    size="sm"
                                                    className="max-md:h-11"
                                                >
                                                    <a
                                                        href={
                                                            round.manuscript
                                                                .downloadUrl
                                                        }
                                                        aria-label={`Unduh naskah perbaikan ${round.manuscript.fileName}`}
                                                    >
                                                        <Download className="size-4" />{' '}
                                                        Unduh
                                                    </a>
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                ))}
                                <LogItems
                                    items={visibleItems(
                                        revision.unassignedItems,
                                    )}
                                />
                                <RevisionAnnotationLinks
                                    annotations={revision.unassignedAnnotations}
                                />
                                {revision.rounds.length === 0 &&
                                    revision.submittedAt && (
                                        <p className="text-xs text-muted-foreground">
                                            Dikirim {revision.submittedAt}
                                        </p>
                                    )}
                                {revision.resolvedAt && (
                                    <p className="text-xs text-muted-foreground">
                                        Diselesaikan {revision.resolvedAt}
                                        {revision.resolvedBy &&
                                            ` oleh ${revision.resolvedBy}`}
                                    </p>
                                )}
                                {revision.resolutionNotes && (
                                    <p className="whitespace-pre-line text-muted-foreground">
                                        Penyelesaian: {revision.resolutionNotes}
                                    </p>
                                )}
                                {actionable &&
                                    (action?.type === 'respond' ? (
                                        <RevisionResponseForm
                                            revisionId={revision.id}
                                            items={revision.items}
                                        />
                                    ) : (
                                        <RevisionVerifyForm
                                            revisionId={revision.id}
                                            items={revision.items}
                                        />
                                    ))}
                            </div>
                        );
                    })}
                </div>
            ))}
            {log.overrides.length > 0 && (
                <div className="space-y-2 border-t pt-3">
                    <h4 className="text-sm font-semibold">Override admin</h4>
                    <ol className="divide-y text-sm">
                        {log.overrides.map((entry) => (
                            <li key={entry.id} className="space-y-1 py-2">
                                <p className="font-medium">{entry.actor}</p>
                                <p className="text-xs text-muted-foreground">
                                    {entry.occurredAt}
                                </p>
                                {entry.description && (
                                    <p className="whitespace-pre-line">
                                        {entry.description}
                                    </p>
                                )}
                            </li>
                        ))}
                    </ol>
                </div>
            )}
        </section>
    );
}
