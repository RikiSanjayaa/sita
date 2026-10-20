export type StatusTone =
    | 'neutral'
    | 'primary'
    | 'success'
    | 'warning'
    | 'danger'
    | 'info';

export type StatusDomain =
    | 'phase'
    | 'state'
    | 'defense'
    | 'result'
    | 'schedule'
    | 'document'
    | 'revision'
    | 'ticket'
    | 'active';

/**
 * Raw backend status keys per domain. Keys are never localized labels.
 * Keep in sync with App\Support\Filament\BadgeStyles::DOMAIN_TONES.
 */
const statusTones: Record<StatusDomain, Record<string, StatusTone>> = {
    // ThesisProject.phase
    phase: {
        title_review: 'neutral',
        sempro: 'info',
        research: 'warning',
        sidang: 'primary',
        completed: 'success',
        cancelled: 'danger',
    },
    // ThesisProject.state
    state: {
        active: 'success',
        on_hold: 'warning',
        completed: 'neutral',
        cancelled: 'danger',
    },
    // ThesisDefense.status (sempro and sidang)
    defense: {
        draft: 'neutral',
        scheduled: 'info',
        awaiting_finalization: 'warning',
        completed: 'success',
        cancelled: 'danger',
    },
    // ThesisDefense.result
    result: {
        pending: 'warning',
        pass: 'success',
        pass_with_revision: 'warning',
        fail: 'danger',
    },
    // MentorshipSchedule.status
    schedule: {
        pending: 'warning',
        approved: 'success',
        scheduled: 'info',
        rescheduled: 'info',
        rejected: 'danger',
        completed: 'neutral',
        cancelled: 'neutral',
    },
    // MentorshipDocument.status, ThesisDocument.status, ThesisProjectTitle.status
    document: {
        draft: 'neutral',
        submitted: 'info',
        needs_revision: 'warning',
        approved: 'success',
        rejected: 'danger',
        active: 'success',
    },
    // ThesisRevision.status
    revision: {
        open: 'warning',
        submitted: 'info',
        resolved: 'success',
    },
    // UI-only follow-up ticket vocabulary: the backend has no ticket/deadline status column,
    // so these keys are not tied to a model; callers map their own state onto them.
    ticket: {
        open: 'warning',
        submitted: 'info',
        overdue: 'danger',
        resolved: 'success',
        closed: 'neutral',
    },
    // Boolean-like activation flags and assignment status
    active: {
        active: 'success',
        inactive: 'neutral',
        ended: 'neutral',
        true: 'success',
        false: 'neutral',
    },
};

const toneClassNames: Record<StatusTone, string> = {
    neutral: 'bg-muted text-muted-foreground',
    primary: 'bg-primary-muted text-primary-muted-foreground',
    success: 'bg-success-muted text-success-muted-foreground',
    warning: 'bg-warning-muted text-warning-muted-foreground',
    danger: 'bg-danger-muted text-danger-muted-foreground',
    info: 'bg-info-muted text-info-muted-foreground',
};

export function statusTone(
    domain: StatusDomain,
    status: string | null | undefined,
): StatusTone {
    if (status === null || status === undefined) {
        return 'neutral';
    }

    const tones = statusTones[domain];

    return Object.hasOwn(tones, status) ? tones[status] : 'neutral';
}

export function statusClassName(
    domain: StatusDomain,
    status: string | null | undefined,
): string {
    return toneClassNames[statusTone(domain, status)];
}
