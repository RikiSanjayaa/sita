import { StatusBadge } from '@/components/patterns/status-badge';
import { cn } from '@/lib/utils';

export type StudentProgressSupervisor = {
    name: string;
    roleLabel: string;
    count: number;
};

export type StudentProgressDetail = {
    statusLabel: string | null;
    supervisors: StudentProgressSupervisor[];
    lastActivityAt: string | null;
    lastActivityLabel: string;
    ticket: { suspended: boolean; validUntil: string | null };
};

/** Row payload from App\Services\StudentProgressSnapshot. `detail` is null when the viewer may not see it. */
export type StudentProgress = {
    phase: string;
    phaseLabel: string;
    detail: StudentProgressDetail | null;
};

export function StudentProgressPeek({
    progress,
    className,
    hideActivityOnPhone = false,
}: {
    progress: StudentProgress;
    className?: string;
    /** Drop the last-activity line below md when the row already shows it. */
    hideActivityOnPhone?: boolean;
}) {
    const detail = progress.detail;

    return (
        <div
            className={cn(
                'space-y-2 text-sm max-md:space-y-1 max-md:text-xs',
                className,
            )}
        >
            <div className="flex flex-wrap items-center gap-2">
                <StatusBadge domain="phase" status={progress.phase}>
                    {progress.phaseLabel}
                </StatusBadge>
                {detail?.ticket.suspended ? (
                    <StatusBadge domain="ticket" status="overdue">
                        Masa aktif habis
                    </StatusBadge>
                ) : null}
            </div>

            {detail?.statusLabel ? (
                <p
                    className="text-muted-foreground max-md:line-clamp-2"
                    title={detail.statusLabel}
                >
                    {detail.statusLabel}
                </p>
            ) : null}

            {detail ? (
                <>
                    <SupervisorCounts supervisors={detail.supervisors} />
                    <p
                        className={cn(
                            'text-muted-foreground tabular-nums',
                            hideActivityOnPhone && 'max-md:hidden',
                        )}
                        title={detail.lastActivityAt ?? undefined}
                    >
                        {detail.lastActivityLabel}
                    </p>
                </>
            ) : null}
        </div>
    );
}

export function SupervisorCounts({
    supervisors,
}: {
    supervisors: StudentProgressSupervisor[];
}) {
    if (supervisors.length === 0) {
        return (
            <p className="text-muted-foreground">Belum ada pembimbing aktif</p>
        );
    }

    return (
        <ul className="space-y-1 max-md:space-y-0">
            {supervisors.map((supervisor) => (
                <li
                    key={`${supervisor.roleLabel}-${supervisor.name}`}
                    className="text-pretty"
                >
                    <span className="text-muted-foreground">
                        {supervisor.roleLabel}:
                    </span>{' '}
                    {supervisor.name}{' '}
                    <span className="text-muted-foreground tabular-nums">
                        ({supervisor.count} bimbingan)
                    </span>
                </li>
            ))}
        </ul>
    );
}
