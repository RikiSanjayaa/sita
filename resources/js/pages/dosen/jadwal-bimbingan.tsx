import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    CalendarPlus,
    CheckCircle2,
    Inbox,
    MapPin,
    Repeat,
    XCircle,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { type BimbinganEvent } from '@/components/bimbingan-calendar';
import { CollapsibleCalendarSection } from '@/components/collapsible-calendar-section';
import {
    DosenScheduleCreateDialog,
    type SupervisedStudent,
} from '@/components/dosen-schedule-create-dialog';
import {
    ArchivedMentorshipSessionNotes,
    MentorshipSessionNotes,
    type MentorshipSessionNotesContext,
    type MentorshipSessionNotesData,
} from '@/components/mentorship-session-notes';
import {
    DataSection,
    DataToolbar,
    dataTh,
    stackedTable,
} from '@/components/patterns/data-section';
import { EmptyState } from '@/components/patterns/empty-state';
import { FilterPills } from '@/components/patterns/filter-pills';
import { PageHeader } from '@/components/patterns/page-header';
import { StatusBadge } from '@/components/patterns/status-badge';
import { StickyFilterBar } from '@/components/patterns/sticky-filter-bar';
import {
    ScheduleDetailModal,
    type ScheduleDetail,
} from '@/components/schedule-detail-modal';
import { Button } from '@/components/ui/button';
import {
    DataTableContainer,
    DataTableEmptyState,
    DataTablePagination,
    usePagination,
} from '@/components/ui/data-table';
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
import { useUrlState } from '@/hooks/use-url-state';
import DosenLayout from '@/layouts/dosen-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dosen/dashboard' },
    { title: 'Jadwal Bimbingan', href: '/dosen/jadwal-bimbingan' },
];

type PendingRequest = {
    id: number;
    mahasiswa: string;
    topic: string;
    relationType: 'pembimbing' | 'penguji';
    requestedAt: string;
    requestedForInput: string | null;
    studentNote: string | null;
    location: string | null;
    status: string;
    isRecurring: boolean;
    recurringGroupId: string | null;
    recurringIndex: number | null;
    recurringCount: number | null;
    sessionNotes: MentorshipSessionNotesData;
};

type UpcomingSchedule = {
    id: number;
    mahasiswa: string;
    topic: string;
    relationType: 'pembimbing' | 'penguji';
    date: string;
    time: string;
    location: string;
    status: string;
    lecturerNote: string | null;
    isOverdue: boolean;
    sessionNotes: MentorshipSessionNotesData;
};

type HistorySchedule = {
    id: number;
    mahasiswa: string;
    topic: string;
    relationType: 'pembimbing' | 'penguji';
    date: string;
    time: string;
    location: string;
    status: string;
    lecturerNote: string | null;
    sessionNotes: MentorshipSessionNotesData;
};

type JadwalBimbinganProps = {
    pendingRequests: PendingRequest[];
    upcomingSchedules: UpcomingSchedule[];
    historySchedules: HistorySchedule[];
    supervisedStudents: SupervisedStudent[];
    workspaceEvents: BimbinganEvent[];
    archivedSessionNotes: MentorshipSessionNotesContext[];
};

type DecisionFormState = {
    scheduled_for: string;
    location: string;
    lecturer_note: string;
};

type DecisionFormErrors = {
    scheduled_for?: string;
    lecturer_note?: string;
};

type ScheduleModalStatus =
    | 'scheduled'
    | 'pending'
    | 'approved'
    | 'rescheduled'
    | 'rejected'
    | 'completed'
    | 'cancelled';

const scheduleStatusLabels: Record<string, string> = {
    approved: 'Terjadwal',
    rescheduled: 'Terjadwal',
    completed: 'Selesai',
    pending: 'Menunggu',
    rejected: 'Ditolak',
};

function ScheduleStatusBadge({ status }: { status: string }) {
    const key = status.toLowerCase();

    return (
        <StatusBadge
            domain="schedule"
            status={key === 'rescheduled' ? 'approved' : key}
        >
            {scheduleStatusLabels[key] ?? 'Dibatalkan'}
        </StatusBadge>
    );
}

function relationLabel(relationType: 'pembimbing' | 'penguji'): string {
    return relationType === 'penguji'
        ? 'Sebagai Penguji'
        : 'Sebagai Pembimbing';
}

function defaultDecision(item: PendingRequest): DecisionFormState {
    return {
        scheduled_for: item.requestedForInput ?? '',
        location: item.location ?? 'Google Meet',
        lecturer_note: '',
    };
}

function defaultRecurring(items: PendingRequest[]): {
    location: string;
    lecturer_note: string;
} {
    return {
        location: items[0]?.location ?? 'Google Meet',
        lecturer_note: '',
    };
}

type WorkspaceFilter = 'bimbingan' | 'ujian' | 'semua';
type HistoryFilter = 'semua' | 'completed' | 'rejected' | 'cancelled';
type UpcomingFilter = 'semua' | 'lewat';

const historyFilterOptions: { label: string; value: HistoryFilter }[] = [
    { label: 'Semua', value: 'semua' },
    { label: 'Selesai', value: 'completed' },
    { label: 'Ditolak', value: 'rejected' },
    { label: 'Dibatalkan', value: 'cancelled' },
];

export default function DosenJadwalBimbinganPage() {
    const {
        pendingRequests,
        upcomingSchedules,
        historySchedules,
        supervisedStudents,
        workspaceEvents,
        archivedSessionNotes,
        auth,
        academicTerminology: n,
    } = usePage<SharedData & JadwalBimbinganProps>().props;

    const [isCreateOpen, setIsCreateOpen] = useState(false);

    const [workspaceFilter, setWorkspaceFilter] = useUrlState<WorkspaceFilter>(
        'calendar',
        'bimbingan',
    );
    const [historyFilter, setHistoryFilter] = useUrlState<HistoryFilter>(
        'historyStatus',
        'semua',
    );
    const [historySearch, setHistorySearch] = useUrlState('historySearch', '');
    const historyPageState = useUrlState('historyPage', 1);
    const HISTORY_PAGE_SIZE = 15;
    const upcomingPageState = useUrlState('upcomingPage', 1);
    const UPCOMING_PAGE_SIZE = 15;
    const [upcomingSearch, setUpcomingSearch] = useState('');
    const [upcomingFilter, setUpcomingFilter] = useUrlState<UpcomingFilter>(
        'upcomingFilter',
        'semua',
    );
    const [openRequestKey, setOpenRequestKey] = useState<string | null>(null);

    // Sheet state for upcoming schedule actions
    const [selectedUpcomingSnapshot, setSelectedUpcoming] =
        useState<UpcomingSchedule | null>(null);
    const selectedUpcoming =
        upcomingSchedules.find(
            (schedule) => schedule.id === selectedUpcomingSnapshot?.id,
        ) ?? null;
    const [upcomingSheetOpen, setUpcomingSheetOpen] = useState(false);

    const form = useForm({
        decision: 'approve' as
            | 'approve'
            | 'reject'
            | 'reschedule'
            | 'complete'
            | 'cancel',
        scheduled_for: '',
        location: '',
        lecturer_note: '',
    });

    const [decisionFormById, setDecisionFormById] = useState<
        Record<number, DecisionFormState>
    >({});
    const [decisionErrorsById, setDecisionErrorsById] = useState<
        Record<number, DecisionFormErrors>
    >({});
    const [recurringFormById, setRecurringFormById] = useState<
        Record<string, { location: string; lecturer_note: string }>
    >({});
    const [recurringErrorsById, setRecurringErrorsById] = useState<
        Record<string, { lecturer_note?: string }>
    >({});

    const [selectedEvent, setSelectedEvent] = useState<ScheduleDetail | null>(
        null,
    );
    const selectedSchedule = [...upcomingSchedules, ...historySchedules].find(
        (schedule) => schedule.id === selectedEvent?.id,
    );
    const selectedDetail = selectedEvent
        ? {
              ...selectedEvent,
              notes: selectedSchedule?.lecturerNote ?? selectedEvent.notes,
              sessionNotes:
                  selectedSchedule?.sessionNotes ??
                  pendingRequests.find(
                      (request) => request.id === selectedEvent.id,
                  )?.sessionNotes ??
                  archivedSessionNotes.find(
                      (session) => session.id === selectedEvent.id,
                  )?.sessionNotes,
          }
        : null;
    const [isDetailModalOpen, setIsDetailModalOpen] = useState(false);

    useEffect(() => {
        const userId = auth.user?.id;
        if (typeof window === 'undefined' || !window.Echo || !userId) return;
        const channelName = `schedule.user.${userId}`;
        const channel = window.Echo.private(channelName).listen(
            '.schedule.updated',
            () => {
                router.reload({
                    only: [
                        'pendingRequests',
                        'upcomingSchedules',
                        'historySchedules',
                        'workspaceEvents',
                    ],
                });
            },
        );
        return () => {
            channel.stopListening('.schedule.updated');
            window.Echo?.leaveChannel(`private-${channelName}`);
        };
    }, [auth.user?.id]);

    function updateDecision(
        item: PendingRequest,
        patch: Partial<DecisionFormState>,
    ) {
        setDecisionFormById((c) => ({
            ...c,
            [item.id]: { ...defaultDecision(item), ...c[item.id], ...patch },
        }));
    }

    function updateRecurring(
        groupId: string,
        items: PendingRequest[],
        patch: Partial<{ location: string; lecturer_note: string }>,
    ) {
        setRecurringFormById((c) => ({
            ...c,
            [groupId]: {
                ...defaultRecurring(items),
                ...c[groupId],
                ...patch,
            },
        }));
    }

    function decide(
        scheduleId: number,
        decision: 'approve' | 'reject' | 'reschedule' | 'complete' | 'cancel',
        item: PendingRequest,
    ) {
        const input = decisionFormById[scheduleId] ?? defaultDecision(item);

        const nextErrors: DecisionFormErrors = {};
        if (
            (decision === 'approve' ||
                decision === 'reject' ||
                decision === 'reschedule') &&
            input.lecturer_note.trim() === ''
        ) {
            nextErrors.lecturer_note = 'Feedback wajib diisi';
        }
        if (decision === 'reschedule' && input.scheduled_for.trim() === '') {
            nextErrors.scheduled_for =
                'Tanggal/jam baru wajib diisi untuk jadwal ulang.';
        }
        if (Object.keys(nextErrors).length > 0) {
            setDecisionErrorsById((c) => ({ ...c, [scheduleId]: nextErrors }));
            return;
        }
        setDecisionErrorsById((c) => {
            const n = { ...c };
            delete n[scheduleId];
            return n;
        });

        const payload = {
            decision,
            scheduled_for:
                decision === 'reject'
                    ? ''
                    : input.scheduled_for || item.requestedForInput || '',
            location: input.location || item.location || 'Google Meet',
            lecturer_note:
                input.lecturer_note ??
                (decision === 'reject' ? '' : 'Permintaan jadwal disetujui.'),
        };

        form.transform(() => ({
            ...payload,
            scheduled_for: payload.scheduled_for || null,
            location: payload.location || null,
            lecturer_note: payload.lecturer_note || null,
        }));

        form.post(`/dosen/jadwal-bimbingan/${scheduleId}/decision`, {
            preserveScroll: true,
            onError: (errors) => {
                setDecisionErrorsById((c) => ({
                    ...c,
                    [scheduleId]: {
                        ...c[scheduleId],
                        scheduled_for:
                            errors.scheduled_for ??
                            c[scheduleId]?.scheduled_for,
                        lecturer_note:
                            errors.lecturer_note ??
                            c[scheduleId]?.lecturer_note,
                    },
                }));
            },
            onSuccess: () => {
                setDecisionErrorsById((c) => {
                    const n = { ...c };
                    delete n[scheduleId];
                    return n;
                });
            },
        });
    }

    function closeSchedule(
        scheduleId: number,
        decision: 'complete' | 'cancel',
        location: string,
    ) {
        form.transform(() => ({
            decision,
            scheduled_for: null,
            location,
            lecturer_note: null,
        }));
        form.post(`/dosen/jadwal-bimbingan/${scheduleId}/decision`, {
            preserveScroll: true,
        });
    }

    function decideRecurringGroup(
        groupId: string,
        decision: 'approve' | 'reject',
        items: PendingRequest[],
    ) {
        const input = recurringFormById[groupId] ?? defaultRecurring(items);
        if (input.lecturer_note.trim() === '') {
            setRecurringErrorsById((c) => ({
                ...c,
                [groupId]: { lecturer_note: 'Feedback wajib diisi' },
            }));
            return;
        }
        setRecurringErrorsById((c) => {
            const n = { ...c };
            delete n[groupId];
            return n;
        });
        form.transform(() => ({
            decision,
            scheduled_for: null,
            location: input.location,
            lecturer_note: input.lecturer_note,
        }));
        form.post(`/dosen/jadwal-bimbingan/recurring/${groupId}/decision`, {
            preserveScroll: true,
            onError: (errors) => {
                setRecurringErrorsById((c) => ({
                    ...c,
                    [groupId]: {
                        lecturer_note:
                            errors.lecturer_note ?? c[groupId]?.lecturer_note,
                    },
                }));
            },
            onSuccess: () => {
                setRecurringErrorsById((c) => {
                    const n = { ...c };
                    delete n[groupId];
                    return n;
                });
            },
        });
    }

    const groupedPendingRequests = useMemo(() => {
        const groups: Record<
            string,
            { isRecurringGroup: boolean; items: PendingRequest[] }
        > = {};
        pendingRequests.forEach((item) => {
            if (item.isRecurring && item.recurringGroupId) {
                if (!groups[item.recurringGroupId]) {
                    groups[item.recurringGroupId] = {
                        isRecurringGroup: true,
                        items: [],
                    };
                }
                groups[item.recurringGroupId].items.push(item);
            } else {
                groups[`single-${item.id}`] = {
                    isRecurringGroup: false,
                    items: [item],
                };
            }
        });
        return groups;
    }, [pendingRequests]);

    function formatDateInIndonesian(dateInput: string | Date): string {
        const date =
            typeof dateInput === 'string' ? new Date(dateInput) : dateInput;
        if (isNaN(date.getTime())) return '';
        return date.toLocaleDateString('id-ID', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function formatDateTime(date: string, time: string): string {
        if (date === time) {
            const p = new Date(date);
            if (!isNaN(p.getTime())) {
                return p.toLocaleString('id-ID', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                });
            }
        }
        const p = new Date(`${date}T${time}`);
        if (isNaN(p.getTime())) return `${date} pukul ${time}`;
        return (
            p.toLocaleDateString('id-ID', {
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            }) + ` pukul ${time}`
        );
    }

    const filteredWorkspaceEvents = workspaceEvents.filter((e) =>
        workspaceFilter === 'semua' ? true : e.category === workspaceFilter,
    );

    const filteredHistorySchedules = useMemo(() => {
        const q = historySearch.trim().toLowerCase();
        return historySchedules.filter((i) => {
            const matchesFilter =
                historyFilter === 'semua' || i.status === historyFilter;
            const matchesSearch =
                !q ||
                i.mahasiswa.toLowerCase().includes(q) ||
                i.topic.toLowerCase().includes(q) ||
                i.location.toLowerCase().includes(q);
            return matchesFilter && matchesSearch;
        });
    }, [historySchedules, historyFilter, historySearch]);

    const filteredUpcomingSchedules = useMemo(() => {
        const q = upcomingSearch.trim().toLowerCase();
        return upcomingSchedules.filter(
            (i) =>
                (upcomingFilter === 'semua' || i.isOverdue) &&
                (!q ||
                    i.mahasiswa.toLowerCase().includes(q) ||
                    i.topic.toLowerCase().includes(q) ||
                    i.location.toLowerCase().includes(q)),
        );
    }, [upcomingSchedules, upcomingSearch, upcomingFilter]);

    const overdueCount = upcomingSchedules.filter((i) => i.isOverdue).length;

    const historyPagination = usePagination(
        filteredHistorySchedules,
        HISTORY_PAGE_SIZE,
        [historyFilter, historySearch],
        historyPageState,
    );
    const upcomingPagination = usePagination(
        filteredUpcomingSchedules,
        UPCOMING_PAGE_SIZE,
        [upcomingSchedules.length, upcomingSearch, upcomingFilter],
        upcomingPageState,
    );

    function handleEventClick(event: BimbinganEvent) {
        if (event.category === 'ujian') {
            router.visit('/dosen/seminar-proposal');
            return;
        }
        const scheduleId =
            typeof event.id === 'string' && event.id.startsWith('schedule-')
                ? Number(event.id.replace('schedule-', ''))
                : Number(event.id);
        if (Number.isNaN(scheduleId)) return;

        const fullSchedule = [...upcomingSchedules, ...historySchedules].find(
            (s) => s.id === scheduleId,
        );
        const scheduleStatus = (
            event.status === 'scheduled' ? 'approved' : event.status
        ) as ScheduleModalStatus;

        if (fullSchedule) {
            setSelectedEvent({
                id: fullSchedule.id,
                topic: fullSchedule.topic,
                person: fullSchedule.mahasiswa,
                personRole: 'student',
                start: event.start,
                end: event.end,
                location: fullSchedule.location,
                status: fullSchedule.status.toLowerCase() as ScheduleModalStatus,
                notes: fullSchedule.lecturerNote,
                sessionNotes: fullSchedule.sessionNotes,
            });
        } else {
            setSelectedEvent({
                id: scheduleId,
                topic: event.topic,
                person: event.person,
                personRole: 'student',
                start: event.start,
                end: event.end,
                location: event.location,
                status: scheduleStatus,
                notes: null,
                sessionNotes: pendingRequests.find(
                    (request) => request.id === scheduleId,
                )?.sessionNotes,
            });
        }
        setIsDetailModalOpen(true);
    }

    function openHistorySchedule(schedule: HistorySchedule) {
        const start =
            schedule.date === schedule.time
                ? schedule.date
                : `${schedule.date}T${schedule.time}`;
        const end = new Date(new Date(start).getTime() + 60 * 60 * 1000);
        setSelectedEvent({
            id: schedule.id,
            topic: schedule.topic,
            person: schedule.mahasiswa,
            personRole: 'student',
            start,
            end: Number.isNaN(end.getTime()) ? start : end.toISOString(),
            location: schedule.location,
            status: schedule.status.toLowerCase() as ScheduleModalStatus,
            notes: schedule.lecturerNote,
            sessionNotes: schedule.sessionNotes,
        });
        setIsDetailModalOpen(true);
    }

    const workspaceFilterOptions: { label: string; value: WorkspaceFilter }[] =
        [
            { label: 'Bimbingan', value: 'bimbingan' },
            {
                label: `${n.proposalExamShort} / ${n.finalExam}`,
                value: 'ujian',
            },
            { label: 'Semua', value: 'semua' },
        ];

    return (
        <DosenLayout
            breadcrumbs={breadcrumbs}
            title="Jadwal Bimbingan"
            subtitle="Kelola permintaan jadwal dan agenda bimbingan"
        >
            <Head title="Jadwal Bimbingan Dosen" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 md:px-6 lg:py-8">
                <PageHeader
                    title="Jadwal Bimbingan"
                    description="Kelola permintaan jadwal dan agenda bimbingan"
                    actions={
                        <Button
                            type="button"
                            className="max-md:h-11 max-md:w-full"
                            onClick={() => setIsCreateOpen(true)}
                            disabled={supervisedStudents.length === 0}
                        >
                            <CalendarPlus className="size-4" />
                            Buat Jadwal
                        </Button>
                    }
                />

                <CollapsibleCalendarSection
                    storageKey="dosen-jadwal-bimbingan"
                    title="Workspace Jadwal"
                    description={
                        <>
                            Satu kalender untuk bimbingan, {n.proposalExamShort}
                            , dan {n.finalExam}
                        </>
                    }
                    toolbar={
                        <FilterPills
                            label="Jenis agenda kalender"
                            value={workspaceFilter}
                            onValueChange={setWorkspaceFilter}
                            itemClassName="max-md:h-11 max-md:px-4"
                            options={workspaceFilterOptions}
                        />
                    }
                    events={filteredWorkspaceEvents}
                    onEventClick={handleEventClick}
                    defaultView="calendar"
                    showLegend={false}
                />

                <DataSection
                    title="Permintaan Menunggu Konfirmasi"
                    summary={
                        pendingRequests.length > 0
                            ? `${pendingRequests.length} permintaan`
                            : undefined
                    }
                >
                    {pendingRequests.length > 0 ? (
                        <ul
                            aria-label="Permintaan menunggu konfirmasi"
                            className="divide-y overflow-hidden rounded-xl border bg-card"
                        >
                            {Object.entries(groupedPendingRequests).map(
                                ([groupKey, group]) => {
                                    const firstItem = group.items[0];
                                    const isOpen = openRequestKey === groupKey;
                                    const formId = `request-form-${groupKey}`;
                                    const recurringInput =
                                        recurringFormById[groupKey] ??
                                        defaultRecurring(group.items);
                                    const decisionInput =
                                        decisionFormById[firstItem.id] ??
                                        defaultDecision(firstItem);
                                    const decisionErrors =
                                        decisionErrorsById[firstItem.id];

                                    return (
                                        <li
                                            key={groupKey}
                                            className="p-4 md:px-5"
                                        >
                                            <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between md:gap-6">
                                                <div className="min-w-0 space-y-1">
                                                    <p className="flex flex-wrap items-baseline gap-x-2">
                                                        <span className="text-base font-semibold">
                                                            {
                                                                firstItem.mahasiswa
                                                            }
                                                        </span>
                                                        <span className="text-sm text-muted-foreground">
                                                            {relationLabel(
                                                                firstItem.relationType,
                                                            )}
                                                        </span>
                                                    </p>
                                                    <p className="text-sm text-pretty">
                                                        {firstItem.topic}
                                                    </p>
                                                    {group.isRecurringGroup ? (
                                                        <>
                                                            <p className="flex items-center gap-1.5 pt-1 text-sm font-medium">
                                                                <Repeat
                                                                    aria-hidden="true"
                                                                    className="size-3.5 shrink-0"
                                                                />
                                                                {
                                                                    group.items
                                                                        .length
                                                                }{' '}
                                                                Pertemuan
                                                            </p>
                                                            <ul className="space-y-0.5 text-sm text-muted-foreground tabular-nums">
                                                                {group.items.map(
                                                                    (item) => (
                                                                        <li
                                                                            key={
                                                                                item.id
                                                                            }
                                                                        >
                                                                            {formatDateInIndonesian(
                                                                                item.requestedAt,
                                                                            )}
                                                                        </li>
                                                                    ),
                                                                )}
                                                            </ul>
                                                        </>
                                                    ) : (
                                                        <p className="flex items-center gap-1.5 text-sm text-muted-foreground tabular-nums">
                                                            <CalendarClock
                                                                aria-hidden="true"
                                                                className="size-3.5 shrink-0"
                                                            />
                                                            {formatDateInIndonesian(
                                                                firstItem.requestedAt,
                                                            )}
                                                        </p>
                                                    )}
                                                    {firstItem.studentNote && (
                                                        <p className="pt-1 text-sm text-pretty text-muted-foreground">
                                                            <span className="font-medium text-foreground">
                                                                Catatan
                                                                mahasiswa:{' '}
                                                            </span>
                                                            {
                                                                firstItem.studentNote
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                                {!isOpen && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        className="shrink-0 max-md:h-11 max-md:w-full"
                                                        aria-expanded={false}
                                                        aria-controls={formId}
                                                        onClick={() =>
                                                            setOpenRequestKey(
                                                                groupKey,
                                                            )
                                                        }
                                                    >
                                                        Tanggapi
                                                    </Button>
                                                )}
                                            </div>

                                            {isOpen &&
                                                group.isRecurringGroup && (
                                                    <div
                                                        id={formId}
                                                        className="mt-4 grid max-w-2xl gap-3"
                                                    >
                                                        <div className="grid gap-1.5">
                                                            <Label
                                                                htmlFor={`recurring-location-${groupKey}`}
                                                            >
                                                                Lokasi untuk
                                                                Semua Jadwal
                                                            </Label>
                                                            <Input
                                                                id={`recurring-location-${groupKey}`}
                                                                className="max-md:h-11"
                                                                value={
                                                                    recurringInput.location
                                                                }
                                                                onChange={(e) =>
                                                                    updateRecurring(
                                                                        groupKey,
                                                                        group.items,
                                                                        {
                                                                            location:
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                        },
                                                                    )
                                                                }
                                                            />
                                                        </div>
                                                        <div className="grid gap-1.5">
                                                            <Label
                                                                htmlFor={`recurring-note-${groupKey}`}
                                                            >
                                                                Feedback ke
                                                                Mahasiswa
                                                            </Label>
                                                            <Textarea
                                                                id={`recurring-note-${groupKey}`}
                                                                value={
                                                                    recurringInput.lecturer_note
                                                                }
                                                                onChange={(
                                                                    e,
                                                                ) => {
                                                                    updateRecurring(
                                                                        groupKey,
                                                                        group.items,
                                                                        {
                                                                            lecturer_note:
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                        },
                                                                    );
                                                                    setRecurringErrorsById(
                                                                        (
                                                                            c,
                                                                        ) => ({
                                                                            ...c,
                                                                            [groupKey]:
                                                                                {
                                                                                    lecturer_note:
                                                                                        undefined,
                                                                                },
                                                                        }),
                                                                    );
                                                                }}
                                                                placeholder="Contoh: Semua jadwal disetujui..."
                                                                className="min-h-[80px] resize-none"
                                                            />
                                                            {recurringErrorsById[
                                                                groupKey
                                                            ]
                                                                ?.lecturer_note && (
                                                                <p className="text-sm text-destructive">
                                                                    {
                                                                        recurringErrorsById[
                                                                            groupKey
                                                                        ]
                                                                            ?.lecturer_note
                                                                    }
                                                                </p>
                                                            )}
                                                        </div>
                                                        <div className="flex flex-wrap gap-2">
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="ghost"
                                                                className="max-md:h-11 max-md:flex-1"
                                                                onClick={() =>
                                                                    setOpenRequestKey(
                                                                        null,
                                                                    )
                                                                }
                                                            >
                                                                Batal
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="outline"
                                                                className="text-destructive hover:text-destructive max-md:h-11 max-md:flex-1"
                                                                disabled={
                                                                    form.processing
                                                                }
                                                                onClick={() =>
                                                                    decideRecurringGroup(
                                                                        groupKey,
                                                                        'reject',
                                                                        group.items,
                                                                    )
                                                                }
                                                            >
                                                                Tolak Semua
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                className="max-md:h-11 max-md:flex-1"
                                                                disabled={
                                                                    form.processing
                                                                }
                                                                onClick={() =>
                                                                    decideRecurringGroup(
                                                                        groupKey,
                                                                        'approve',
                                                                        group.items,
                                                                    )
                                                                }
                                                            >
                                                                Konfirmasi Semua
                                                            </Button>
                                                        </div>
                                                    </div>
                                                )}

                                            {isOpen &&
                                                !group.isRecurringGroup && (
                                                    <div
                                                        id={formId}
                                                        className="mt-4 grid max-w-2xl gap-3"
                                                    >
                                                        <div className="grid gap-3 md:grid-cols-2">
                                                            <div className="grid gap-1.5">
                                                                <Label
                                                                    htmlFor={`scheduled-${firstItem.id}`}
                                                                >
                                                                    Tanggal/Jam
                                                                    Konfirmasi
                                                                    atau Jadwal
                                                                    Ulang
                                                                </Label>
                                                                <Input
                                                                    id={`scheduled-${firstItem.id}`}
                                                                    type="datetime-local"
                                                                    className="max-md:h-11"
                                                                    value={
                                                                        decisionInput.scheduled_for
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) => {
                                                                        updateDecision(
                                                                            firstItem,
                                                                            {
                                                                                scheduled_for:
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                            },
                                                                        );
                                                                        setDecisionErrorsById(
                                                                            (
                                                                                c,
                                                                            ) => ({
                                                                                ...c,
                                                                                [firstItem.id]:
                                                                                    {
                                                                                        ...c[
                                                                                            firstItem
                                                                                                .id
                                                                                        ],
                                                                                        scheduled_for:
                                                                                            undefined,
                                                                                    },
                                                                            }),
                                                                        );
                                                                    }}
                                                                />
                                                                {decisionErrors?.scheduled_for && (
                                                                    <p className="text-sm text-destructive">
                                                                        {
                                                                            decisionErrors.scheduled_for
                                                                        }
                                                                    </p>
                                                                )}
                                                            </div>
                                                            <div className="grid gap-1.5">
                                                                <Label
                                                                    htmlFor={`location-${firstItem.id}`}
                                                                >
                                                                    Lokasi
                                                                </Label>
                                                                <Input
                                                                    id={`location-${firstItem.id}`}
                                                                    className="max-md:h-11"
                                                                    value={
                                                                        decisionInput.location
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        updateDecision(
                                                                            firstItem,
                                                                            {
                                                                                location:
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                            },
                                                                        )
                                                                    }
                                                                />
                                                            </div>
                                                        </div>
                                                        <div className="grid gap-1.5">
                                                            <Label
                                                                htmlFor={`note-${firstItem.id}`}
                                                            >
                                                                Feedback ke
                                                                Mahasiswa
                                                            </Label>
                                                            <Textarea
                                                                id={`note-${firstItem.id}`}
                                                                value={
                                                                    decisionInput.lecturer_note
                                                                }
                                                                onChange={(
                                                                    e,
                                                                ) => {
                                                                    updateDecision(
                                                                        firstItem,
                                                                        {
                                                                            lecturer_note:
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                        },
                                                                    );
                                                                    setDecisionErrorsById(
                                                                        (
                                                                            c,
                                                                        ) => ({
                                                                            ...c,
                                                                            [firstItem.id]:
                                                                                {
                                                                                    ...c[
                                                                                        firstItem
                                                                                            .id
                                                                                    ],
                                                                                    lecturer_note:
                                                                                        undefined,
                                                                                },
                                                                        }),
                                                                    );
                                                                }}
                                                                placeholder="Contoh: Tolong lengkapi data Bab 3 sebelum bimbingan berikutnya."
                                                                className="min-h-[80px] resize-none"
                                                            />
                                                            {decisionErrors?.lecturer_note && (
                                                                <p className="text-sm text-destructive">
                                                                    {
                                                                        decisionErrors.lecturer_note
                                                                    }
                                                                </p>
                                                            )}
                                                        </div>
                                                        <div className="flex flex-wrap gap-2">
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="ghost"
                                                                className="max-md:h-11 max-md:flex-1"
                                                                onClick={() =>
                                                                    setOpenRequestKey(
                                                                        null,
                                                                    )
                                                                }
                                                            >
                                                                Batal
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="outline"
                                                                className="max-md:h-11 max-md:flex-1"
                                                                disabled={
                                                                    form.processing
                                                                }
                                                                onClick={() =>
                                                                    decide(
                                                                        firstItem.id,
                                                                        'reschedule',
                                                                        firstItem,
                                                                    )
                                                                }
                                                            >
                                                                Jadwalkan Ulang
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="outline"
                                                                className="text-destructive hover:text-destructive max-md:h-11 max-md:flex-1"
                                                                disabled={
                                                                    form.processing
                                                                }
                                                                onClick={() =>
                                                                    decide(
                                                                        firstItem.id,
                                                                        'reject',
                                                                        firstItem,
                                                                    )
                                                                }
                                                            >
                                                                Tolak
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                className="max-md:h-11 max-md:flex-1"
                                                                disabled={
                                                                    form.processing
                                                                }
                                                                onClick={() =>
                                                                    decide(
                                                                        firstItem.id,
                                                                        'approve',
                                                                        firstItem,
                                                                    )
                                                                }
                                                            >
                                                                Konfirmasi
                                                            </Button>
                                                        </div>
                                                    </div>
                                                )}
                                        </li>
                                    );
                                },
                            )}
                        </ul>
                    ) : (
                        <EmptyState
                            icon={Inbox}
                            title="Belum ada permintaan baru"
                            description="Permintaan bimbingan dari mahasiswa akan tampil di sini."
                        />
                    )}
                </DataSection>

                <section aria-label="Jadwal Mendatang" className="space-y-4">
                    <StickyFilterBar>
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:gap-6">
                            <PageHeader
                                title="Jadwal Mendatang"
                                level={2}
                                description={
                                    upcomingSchedules.length > 0
                                        ? `${upcomingSchedules.length} jadwal`
                                        : undefined
                                }
                                className="shrink-0"
                            />
                            {upcomingSchedules.length > 0 ? (
                                <div className="min-w-0 flex-1">
                                    <DataToolbar
                                        search={upcomingSearch}
                                        onSearchChange={setUpcomingSearch}
                                        placeholder="Cari mahasiswa, topik, atau lokasi..."
                                    />
                                </div>
                            ) : null}
                            {overdueCount > 0 || upcomingFilter === 'lewat' ? (
                                <FilterPills
                                    label="Jadwal mendatang"
                                    value={upcomingFilter}
                                    onValueChange={setUpcomingFilter}
                                    itemClassName="max-md:h-11 max-md:px-4"
                                    options={[
                                        { label: 'Semua', value: 'semua' },
                                        {
                                            label: 'Belum ditandai selesai',
                                            value: 'lewat',
                                            count: overdueCount,
                                        },
                                    ]}
                                />
                            ) : null}
                        </div>
                    </StickyFilterBar>
                    {upcomingSchedules.length > 0 ? (
                        <>
                            {upcomingPagination.totalItems > 0 ? (
                                <DataTableContainer
                                    pagination={
                                        <DataTablePagination
                                            currentPage={
                                                upcomingPagination.page
                                            }
                                            totalPages={
                                                upcomingPagination.totalPages
                                            }
                                            totalItems={
                                                upcomingPagination.totalItems
                                            }
                                            pageSize={
                                                upcomingPagination.pageSize
                                            }
                                            onPageChange={
                                                upcomingPagination.setPage
                                            }
                                            onPageSizeChange={
                                                upcomingPagination.setPageSize
                                            }
                                            itemLabel="jadwal"
                                        />
                                    }
                                >
                                    <table
                                        className={cn(
                                            stackedTable.table,
                                            'md:min-w-[900px]',
                                        )}
                                    >
                                        <thead className={stackedTable.thead}>
                                            <tr className="border-b bg-muted/40">
                                                <th className={dataTh}>
                                                    Mahasiswa
                                                </th>
                                                <th className={dataTh}>
                                                    Topik
                                                </th>
                                                <th className={dataTh}>
                                                    Waktu
                                                </th>
                                                <th className={dataTh}>
                                                    Lokasi
                                                </th>
                                                <th className={dataTh}>
                                                    Status
                                                </th>
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
                                            {upcomingPagination.paginated.map(
                                                (item) => (
                                                    <tr
                                                        key={`${item.id}-${item.mahasiswa}`}
                                                        className={cn(
                                                            stackedTable.tr,
                                                            'cursor-pointer align-top transition-colors hover:bg-muted/40',
                                                        )}
                                                        onClick={() => {
                                                            setSelectedUpcoming(
                                                                item,
                                                            );
                                                            setUpcomingSheetOpen(
                                                                true,
                                                            );
                                                        }}
                                                    >
                                                        <td
                                                            className={
                                                                stackedTable.tdPlain
                                                            }
                                                        >
                                                            <div className="font-medium text-foreground">
                                                                {item.mahasiswa}
                                                            </div>
                                                            <div className="text-xs text-muted-foreground">
                                                                {relationLabel(
                                                                    item.relationType,
                                                                )}
                                                            </div>
                                                        </td>
                                                        <td
                                                            title={item.topic}
                                                            className={cn(
                                                                stackedTable.tdDetail,
                                                                'max-w-xs text-pretty max-md:line-clamp-2 max-md:max-w-none',
                                                            )}
                                                        >
                                                            {item.topic}
                                                        </td>
                                                        <td
                                                            className={cn(
                                                                stackedTable.td,
                                                                'text-muted-foreground md:whitespace-nowrap',
                                                            )}
                                                        >
                                                            {formatDateTime(
                                                                item.date,
                                                                item.time,
                                                            )}
                                                        </td>
                                                        <td
                                                            className={cn(
                                                                stackedTable.td,
                                                                'text-muted-foreground',
                                                            )}
                                                        >
                                                            {item.location}
                                                        </td>
                                                        <td
                                                            className={
                                                                stackedTable.tdAside
                                                            }
                                                        >
                                                            <ScheduleStatusBadge
                                                                status={
                                                                    item.status
                                                                }
                                                            />
                                                        </td>
                                                        <td
                                                            className={cn(
                                                                stackedTable.tdActions,
                                                                'max-md:order-4 max-md:mt-2 max-md:basis-full',
                                                            )}
                                                        >
                                                            <div className="flex items-center justify-end gap-1 max-md:justify-start">
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    variant="outline"
                                                                    className="max-md:h-11"
                                                                    onClick={() => {
                                                                        setSelectedUpcoming(
                                                                            item,
                                                                        );
                                                                        setUpcomingSheetOpen(
                                                                            true,
                                                                        );
                                                                    }}
                                                                >
                                                                    Kelola
                                                                    <span className="sr-only">
                                                                        {' '}
                                                                        jadwal{' '}
                                                                        {
                                                                            item.mahasiswa
                                                                        }
                                                                    </span>
                                                                </Button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                </DataTableContainer>
                            ) : (
                                <DataTableEmptyState
                                    icon={CalendarClock}
                                    title="Tidak ada jadwal yang cocok"
                                    description="Coba ubah kata kunci pencarian."
                                />
                            )}
                        </>
                    ) : (
                        <EmptyState
                            icon={CalendarClock}
                            title="Belum ada jadwal mendatang"
                            description="Agenda yang sudah dikonfirmasi akan muncul di bagian ini."
                        />
                    )}
                </section>

                <section aria-label="Riwayat Jadwal" className="space-y-4">
                    <StickyFilterBar>
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:gap-6">
                            <PageHeader
                                title="Riwayat Jadwal"
                                level={2}
                                description="Sesi bimbingan yang sudah selesai, ditolak, atau dibatalkan"
                                className="lg:max-w-64 lg:shrink-0"
                            />
                            <div className="min-w-0 flex-1">
                                <DataToolbar
                                    search={historySearch}
                                    onSearchChange={setHistorySearch}
                                    placeholder="Cari mahasiswa, topik, atau lokasi..."
                                >
                                    <FilterPills
                                        label="Status riwayat"
                                        value={historyFilter}
                                        onValueChange={setHistoryFilter}
                                        itemClassName="max-md:h-11 max-md:px-4"
                                        options={historyFilterOptions}
                                    />
                                </DataToolbar>
                            </div>
                        </div>
                    </StickyFilterBar>

                    {historyPagination.totalItems > 0 ? (
                        <DataTableContainer
                            pagination={
                                <DataTablePagination
                                    currentPage={historyPagination.page}
                                    totalPages={historyPagination.totalPages}
                                    totalItems={historyPagination.totalItems}
                                    pageSize={historyPagination.pageSize}
                                    onPageChange={historyPagination.setPage}
                                    onPageSizeChange={
                                        historyPagination.setPageSize
                                    }
                                    itemLabel="riwayat"
                                />
                            }
                        >
                            <table
                                className={cn(
                                    stackedTable.table,
                                    'md:min-w-[900px]',
                                )}
                            >
                                <thead className={stackedTable.thead}>
                                    <tr className="border-b bg-muted/40">
                                        <th className={dataTh}>Mahasiswa</th>
                                        <th className={dataTh}>Topik</th>
                                        <th className={dataTh}>Waktu</th>
                                        <th className={dataTh}>Lokasi</th>
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
                                    {historyPagination.paginated.map((item) => (
                                        <tr
                                            key={`${item.id}-${item.status}`}
                                            className={cn(
                                                stackedTable.tr,
                                                'align-top',
                                            )}
                                        >
                                            <td
                                                className={stackedTable.tdPlain}
                                            >
                                                <div className="font-medium text-foreground">
                                                    {item.mahasiswa}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {relationLabel(
                                                        item.relationType,
                                                    )}
                                                </div>
                                            </td>
                                            <td
                                                title={
                                                    item.lecturerNote
                                                        ? `${item.topic} — ${item.lecturerNote}`
                                                        : item.topic
                                                }
                                                className={cn(
                                                    stackedTable.tdDetail,
                                                    'max-w-xs text-pretty max-md:line-clamp-2 max-md:max-w-none',
                                                )}
                                            >
                                                {item.topic}
                                                {item.lecturerNote && (
                                                    <p className="mt-1 text-sm text-muted-foreground">
                                                        {item.lecturerNote}
                                                    </p>
                                                )}
                                            </td>
                                            <td
                                                className={cn(
                                                    stackedTable.td,
                                                    'text-muted-foreground md:whitespace-nowrap',
                                                )}
                                            >
                                                {formatDateTime(
                                                    item.date,
                                                    item.time,
                                                )}
                                            </td>
                                            <td
                                                className={cn(
                                                    stackedTable.td,
                                                    'text-muted-foreground',
                                                )}
                                            >
                                                {item.location}
                                            </td>
                                            <td
                                                className={stackedTable.tdAside}
                                            >
                                                <ScheduleStatusBadge
                                                    status={item.status}
                                                />
                                            </td>
                                            <td
                                                className={
                                                    stackedTable.tdActions
                                                }
                                            >
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    className="max-md:h-11"
                                                    aria-label={`Detail sesi ${item.topic}`}
                                                    onClick={() =>
                                                        openHistorySchedule(
                                                            item,
                                                        )
                                                    }
                                                >
                                                    Detail sesi
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </DataTableContainer>
                    ) : (
                        <DataTableEmptyState
                            icon={CalendarClock}
                            title={
                                historySearch || historyFilter !== 'semua'
                                    ? 'Tidak ada riwayat yang cocok'
                                    : 'Belum ada riwayat jadwal'
                            }
                            description={
                                historySearch || historyFilter !== 'semua'
                                    ? 'Coba ubah kata kunci atau filter yang dipilih.'
                                    : 'Riwayat sesi selesai, ditolak, atau dibatalkan akan muncul di sini.'
                            }
                        />
                    )}
                </section>
                <ArchivedMentorshipSessionNotes
                    sessions={archivedSessionNotes}
                />
            </div>

            <ScheduleDetailModal
                open={isDetailModalOpen}
                onOpenChange={setIsDetailModalOpen}
                schedule={selectedDetail}
                currentUserRole="dosen"
            />

            <Sheet open={upcomingSheetOpen} onOpenChange={setUpcomingSheetOpen}>
                <SheetContent
                    side="right"
                    className="w-full gap-0 p-0 sm:max-w-md"
                >
                    <SheetHeader className="border-b px-6 py-4">
                        <div className="flex items-center gap-2 pr-6">
                            <ScheduleStatusBadge
                                status={selectedUpcoming?.status ?? 'approved'}
                            />
                        </div>
                        <SheetTitle className="text-lg leading-snug [overflow-wrap:anywhere]">
                            {selectedUpcoming?.mahasiswa}
                        </SheetTitle>
                        <SheetDescription className="[overflow-wrap:anywhere]">
                            {selectedUpcoming?.topic}
                        </SheetDescription>
                    </SheetHeader>

                    <ScrollArea className="min-h-0 flex-1">
                        <div className="space-y-5 px-6 py-5">
                            {selectedUpcoming && (
                                <>
                                    <div className="grid gap-2 text-sm">
                                        <div className="flex items-center gap-2">
                                            <CalendarClock
                                                aria-hidden="true"
                                                className="size-4 shrink-0 text-muted-foreground"
                                            />
                                            <span>
                                                {formatDateTime(
                                                    selectedUpcoming.date,
                                                    selectedUpcoming.time,
                                                )}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <MapPin
                                                aria-hidden="true"
                                                className="size-4 shrink-0 text-muted-foreground"
                                            />
                                            <span className="min-w-0 [overflow-wrap:anywhere]">
                                                {selectedUpcoming.location}
                                            </span>
                                        </div>
                                        <p className="text-muted-foreground">
                                            {relationLabel(
                                                selectedUpcoming.relationType,
                                            )}
                                        </p>
                                    </div>

                                    {selectedUpcoming.lecturerNote && (
                                        <p className="text-sm [overflow-wrap:anywhere] whitespace-pre-wrap text-muted-foreground">
                                            <span className="font-medium text-foreground">
                                                Catatan penjadwalan:{' '}
                                            </span>
                                            {selectedUpcoming.lecturerNote}
                                        </p>
                                    )}

                                    <div className="min-w-0 border-t pt-5">
                                        <MentorshipSessionNotes
                                            sessionId={selectedUpcoming.id}
                                            notes={
                                                selectedUpcoming.sessionNotes
                                            }
                                        />
                                    </div>

                                    <div className="border-t pt-5">
                                        <h3 className="mb-3 text-sm font-semibold">
                                            Tindakan
                                        </h3>
                                        <div className="flex flex-col gap-2">
                                            <Button
                                                className="w-full max-md:h-11"
                                                disabled={form.processing}
                                                onClick={() => {
                                                    closeSchedule(
                                                        selectedUpcoming.id,
                                                        'complete',
                                                        selectedUpcoming.location,
                                                    );
                                                    setUpcomingSheetOpen(false);
                                                }}
                                            >
                                                <CheckCircle2 className="size-4" />
                                                Tandai Selesai
                                            </Button>
                                            <Button
                                                variant="outline"
                                                className="w-full text-destructive hover:text-destructive max-md:h-11"
                                                disabled={form.processing}
                                                onClick={() => {
                                                    closeSchedule(
                                                        selectedUpcoming.id,
                                                        'cancel',
                                                        selectedUpcoming.location,
                                                    );
                                                    setUpcomingSheetOpen(false);
                                                }}
                                            >
                                                <XCircle className="size-4" />
                                                Batalkan Jadwal
                                            </Button>
                                        </div>
                                    </div>
                                </>
                            )}
                        </div>
                    </ScrollArea>
                </SheetContent>
            </Sheet>
            <DosenScheduleCreateDialog
                open={isCreateOpen}
                onOpenChange={setIsCreateOpen}
                students={supervisedStudents}
            />
        </DosenLayout>
    );
}
