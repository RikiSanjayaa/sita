import { Download, FileText } from 'lucide-react';

import { StatusBadge } from '@/components/patterns/status-badge';
import { Button } from '@/components/ui/button';

export type RevisionItem = {
    id: number;
    location: string | null;
    description: string;
    priority: 'major' | 'minor' | null;
    priorityLabel: string | null;
    status: string;
    statusLabel: string;
    roundOpened: number;
};

export type RevisionAnnotation = {
    id: number;
    roundNo: number;
    fileName: string;
    viewUrl: string;
    downloadUrl: string;
};

// Item statuses reuse the revision badge tones: addressed reads as submitted,
// accepted as resolved, reopened as open.
const itemBadgeStatus: Record<string, string> = {
    open: 'open',
    addressed: 'submitted',
    accepted: 'resolved',
    reopened: 'open',
};

/**
 * Show the free-text summary unless it only repeats the single item that the
 * data migration built from it.
 */
export function showRevisionSummary(
    notes: string | null,
    items: RevisionItem[],
): boolean {
    if (!notes) return false;
    return !(items.length === 1 && items[0].description === notes);
}

export function RevisionItemList({ items }: { items: RevisionItem[] }) {
    if (items.length === 0) return null;

    return (
        <ol className="mt-2 divide-y rounded-md border text-sm">
            {items.map((item, index) => (
                <li key={item.id} className="flex flex-col gap-1.5 p-3">
                    <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span className="font-medium tabular-nums">
                            {index + 1}.
                        </span>
                        {item.location && (
                            <span className="font-medium">{item.location}</span>
                        )}
                        {item.priorityLabel && (
                            <StatusBadge
                                domain="revision"
                                status={
                                    item.priority === 'major'
                                        ? 'open'
                                        : 'submitted'
                                }
                            >
                                {item.priorityLabel}
                            </StatusBadge>
                        )}
                        <StatusBadge
                            domain="revision"
                            status={itemBadgeStatus[item.status] ?? item.status}
                            className="ml-auto"
                        >
                            {item.statusLabel}
                        </StatusBadge>
                    </div>
                    <p className="leading-relaxed text-pretty">
                        {item.description}
                    </p>
                </li>
            ))}
        </ol>
    );
}

export function RevisionAnnotationLinks({
    annotations,
}: {
    annotations: RevisionAnnotation[];
}) {
    if (annotations.length === 0) return null;

    return (
        <ul className="mt-2 flex flex-col gap-2 text-sm">
            {annotations.map((doc) => (
                <li
                    key={doc.id}
                    className="flex flex-wrap items-center gap-x-3 gap-y-1"
                >
                    <FileText
                        className="size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <a
                        href={doc.viewUrl}
                        target="_blank"
                        rel="noreferrer"
                        className="font-medium underline-offset-4 hover:underline"
                    >
                        Naskah beranotasi: {doc.fileName}
                    </a>
                    <Button
                        asChild
                        size="sm"
                        variant="ghost"
                        className="max-md:h-11"
                    >
                        <a href={doc.downloadUrl}>
                            <Download className="size-4" /> Unduh
                        </a>
                    </Button>
                </li>
            ))}
        </ul>
    );
}
