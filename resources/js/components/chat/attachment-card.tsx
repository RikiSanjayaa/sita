import { Download, FileText } from 'lucide-react';
import { type ReactNode } from 'react';

import { StatusBadge } from '@/components/patterns/status-badge';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

import { formatFileSize } from './attachment';
import { FilenameLabel } from './highlight-text';

/** Labels for MentorshipDocument.status, matching the upload page wording. */
const REVIEW_STATUS_LABELS: Record<string, string> = {
    draft: 'Draf',
    submitted: 'Menunggu Review',
    needs_revision: 'Perlu Revisi',
    approved: 'Disetujui',
    rejected: 'Ditolak',
};

/** Icon-only action next to the download icon; matches it on both bubble colours. */
export function attachmentIconActionClassName(inverse: boolean): string {
    return cn(
        'inline-flex size-8 shrink-0 items-center justify-center rounded-md transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
        inverse
            ? 'hover:bg-primary-foreground/15'
            : 'text-muted-foreground hover:bg-muted hover:text-foreground',
    );
}

type AttachmentCardProps = {
    /** Optional icon-only action shown before the download icon. */
    action?: ReactNode;
    /** Small line above the name, e.g. who sent the file. */
    caption?: string;
    /** Download URL; null while the message is still being sent. */
    href: string | null;
    inverse: boolean;
    name: string;
    reviewStatus: string | null;
    searchTerm?: string;
    sizeKb: number | null;
};

/** Document attachment inside a bubble: icon, name, size, review status, download. */
export function AttachmentCard({
    action,
    caption,
    href,
    inverse,
    name,
    reviewStatus,
    searchTerm,
    sizeKb,
}: AttachmentCardProps) {
    const body = (
        <>
            <div
                className={cn(
                    'flex size-9 shrink-0 items-center justify-center rounded-md',
                    inverse
                        ? 'bg-primary-foreground/15'
                        : 'bg-primary-muted text-primary-muted-foreground',
                )}
            >
                <FileText className="size-4" />
            </div>
            <div className="min-w-0 flex-1">
                {caption && (
                    <p className="text-[11px] font-medium opacity-75">
                        {caption}
                    </p>
                )}
                <FilenameLabel
                    name={name}
                    className="block truncate text-sm font-semibold"
                    searchTerm={searchTerm}
                    inverse={inverse}
                />
                <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs opacity-80">
                    {sizeKb !== null && (
                        <span>{formatFileSize(sizeKb * 1024)}</span>
                    )}
                    {reviewStatus !== null && (
                        <StatusBadge
                            domain="document"
                            status={reviewStatus}
                            className="px-2 py-0 text-[11px]"
                        >
                            {REVIEW_STATUS_LABELS[reviewStatus] ?? reviewStatus}
                        </StatusBadge>
                    )}
                </div>
            </div>
        </>
    );

    const bodyClassName = 'flex min-w-0 flex-1 items-center gap-3';

    return (
        <div
            className={cn(
                'mb-2 flex min-w-0 items-center gap-1 rounded-xl border p-2.5',
                inverse
                    ? 'border-primary-foreground/25 bg-primary-foreground/10 text-primary-foreground'
                    : 'border-border bg-muted/30 text-foreground',
            )}
        >
            {href === null ? (
                <div className={bodyClassName}>{body}</div>
            ) : (
                <a
                    href={href}
                    target="_blank"
                    rel="noopener noreferrer"
                    className={cn(
                        bodyClassName,
                        'transition-opacity hover:opacity-80',
                    )}
                >
                    {body}
                </a>
            )}
            {action}
            {href !== null && (
                <Tooltip>
                    <TooltipTrigger asChild>
                        <a
                            href={href}
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label={`Unduh ${name}`}
                            className={attachmentIconActionClassName(inverse)}
                        >
                            <Download className="size-4" />
                        </a>
                    </TooltipTrigger>
                    <TooltipContent>Unduh</TooltipContent>
                </Tooltip>
            )}
        </div>
    );
}
