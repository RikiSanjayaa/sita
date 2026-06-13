import {
    AlertCircle,
    AlertTriangle,
    CalendarClock,
    CheckCircle2,
} from 'lucide-react';
import { useMemo } from 'react';

interface DeadlineBadgeProps {
    dueAt: string | null;
    status?: string;
    className?: string;
    showLabel?: boolean;
}

type DeadlineState = 'safe' | 'warning' | 'overdue' | 'resolved' | null;

function getDeadlineState(
    dueAt: string | null,
    status?: string,
): DeadlineState {
    if (!dueAt || status === 'resolved') {
        return null;
    }

    const now = new Date();
    const due = new Date(dueAt);
    const diffMs = due.getTime() - now.getTime();
    const diffDays = diffMs / (1000 * 60 * 60 * 24);

    if (diffDays < 0) {
        return 'overdue';
    }

    if (diffDays <= 3) {
        return 'warning';
    }

    return 'safe';
}

function getDaysLabel(dueAt: string): string {
    const now = new Date();
    const due = new Date(dueAt);
    const diffMs = due.getTime() - now.getTime();
    const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24));

    if (diffDays < 0) {
        const overdueDays = Math.abs(diffDays);
        return overdueDays === 1
            ? 'Terlewat 1 hari'
            : `Terlewat ${overdueDays} hari`;
    }

    if (diffDays === 0) {
        return 'Hari ini';
    }

    return diffDays === 1 ? 'Sisa 1 hari' : `Sisa ${diffDays} hari`;
}

const stateConfig: Record<
    'safe' | 'warning' | 'overdue',
    {
        bg: string;
        text: string;
        border: string;
        icon: typeof CheckCircle2;
        label: string;
    }
> = {
    safe: {
        bg: 'bg-emerald-50 dark:bg-emerald-950/30',
        text: 'text-emerald-700 dark:text-emerald-400',
        border: 'border-emerald-200 dark:border-emerald-800',
        icon: CalendarClock,
        label: 'Deadline aktif',
    },
    warning: {
        bg: 'bg-amber-50 dark:bg-amber-950/30',
        text: 'text-amber-700 dark:text-amber-400',
        border: 'border-amber-200 dark:border-amber-800',
        icon: AlertTriangle,
        label: 'Deadline mendekati',
    },
    overdue: {
        bg: 'bg-red-50 dark:bg-red-950/30',
        text: 'text-red-700 dark:text-red-400',
        border: 'border-red-200 dark:border-red-800',
        icon: AlertCircle,
        label: 'Deadline terlewat',
    },
};

export function DeadlineBadge({
    dueAt,
    status,
    className = '',
    showLabel = true,
}: DeadlineBadgeProps) {
    const state = useMemo(
        () => getDeadlineState(dueAt, status),
        [dueAt, status],
    );

    if (!state || state === 'resolved' || !dueAt) {
        return null;
    }

    const config = stateConfig[state];
    const Icon = config.icon;
    const daysLabel = getDaysLabel(dueAt);

    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-md border px-2 py-0.5 text-xs font-medium ${config.bg} ${config.text} ${config.border} ${className}`}
        >
            <Icon className="h-3.5 w-3.5 shrink-0" />
            {showLabel && <span>{daysLabel}</span>}
        </span>
    );
}

export function DeadlineBanner({
    dueAt,
    status,
    revisionNotes,
}: {
    dueAt: string | null;
    status?: string;
    revisionNotes?: string;
}) {
    const state = useMemo(
        () => getDeadlineState(dueAt, status),
        [dueAt, status],
    );

    if (!state || state === 'safe' || state === 'resolved' || !dueAt) {
        return null;
    }

    const config = stateConfig[state];
    const Icon = config.icon;
    const daysLabel = getDaysLabel(dueAt);

    return (
        <div
            className={`flex items-start gap-3 rounded-lg border p-3 ${config.bg} ${config.border}`}
        >
            <Icon className={`mt-0.5 h-5 w-5 shrink-0 ${config.text}`} />
            <div className="min-w-0 flex-1">
                <p className={`text-sm font-medium ${config.text}`}>
                    {state === 'overdue'
                        ? 'Batas revisi telah terlewat'
                        : 'Batas revisi mendekati'}{' '}
                    - {daysLabel}
                </p>
                {revisionNotes && (
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        {revisionNotes}
                    </p>
                )}
            </div>
        </div>
    );
}

export { getDeadlineState, getDaysLabel };
