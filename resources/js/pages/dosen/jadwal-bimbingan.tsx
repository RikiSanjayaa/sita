import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    CheckCircle2,
    ChevronRight,
    Clock,
    Inbox,
    MapPin,
    Repeat,
    XCircle,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import {
    BimbinganCalendar,
    type BimbinganEvent,
} from '@/components/bimbingan-calendar';
import { EmptyState } from '@/components/empty-state';
import {
    DataTableContainer,
    DataTableEmptyState,
    DataTablePagination,
    DataTableToolbar,
    type FilterGroup,
    usePagination,
} from '@/components/ui/data-table';
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
import { cn } from '@/lib/utils';
import DosenLayout from '@/layouts/dosen-layout';
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
};

type JadwalBimbinganProps = {
    pendingRequests: PendingRequest[];
    upcomingSchedules: UpcomingSchedule[];
    historySchedules: HistorySchedule[];
    workspaceEvents: BimbinganEvent[];
    flashMessage?: string | null;
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

function StatusBadge({ status }: { status: string }) {
    const s = status.toLowerCase();
    if (s === 'approved' || s === 'rescheduled') {
        return (
            <span className="inline-flex items-center gap-1 rounded-full bg-emerald-600/10 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-400">
                <CheckCircle2 className="size-3" /> Terjadwal
            </span>
        );
    }
    if (s === 'completed') {
        return (
            <span className="inline-flex items-center gap-1 rounded-full bg-blue-600/10 px-2.5 py-0.5 text-xs font-medium text-blue-600">
                <CheckCircle2 className="size-3" /> Selesai
            </span>
        );
    }
    if (s === 'pending') {
        return (
            <span className="inline-flex items-center gap-1 rounded-full bg-amber-600/10 px-2.5 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-400">
                <Clock className="size-3" /> Menunggu
            </span>
        );
    }
    if (s === 'rejected') {
        return (
            <span className="inline-flex items-center gap-1 rounded-full bg-destructive/10 px-2.5 py-0.5 text-xs font-medium text-destructive">
                <XCircle className="size-3" /> Ditolak
            </span>
        );
    }
    return (
        <span className="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground">
            <XCircle className="size-3" /> Dibatalkan
        </span>
    );
}

function RelationTypeBadge({
    relationType,
}: {
    relationType: 'pembimbing' | 'penguji';
}) {
    if (relationType === 'penguji') {
        return (
            <Badge
                variant="outline"
                className="gap-1 rounded-full border-purple-500/50 bg-purple-500/10 text-purple-600 hover:bg-purple-500/20 dark:text-purple-400"
            >
                Sebagai Penguji
            </Badge>
        );
    }
    return (
        <Badge
            variant="outline"
            className="gap-1 rounded-full border-cyan-500/50 bg-cyan-500/10 text-cyan-600 hover:bg-cyan-500/20 dark:text-cyan-400"
        >
            Sebagai Pembimbing
        </Badge>
    );
}

type WorkspaceFilter = 'bimbingan' | 'ujian' | 'semua';
type HistoryFilter = 'semua' | 'completed' | 'rejected' | 'cancelled';

export default function DosenJadwalBimbinganPage() {
    const {
        pendingRequests,
        upcomingSchedules,
        historySchedules,
        flashMessage,
        workspaceEvents,
        auth,
    } = usePage<SharedData & JadwalBimbinganProps>().props;

    const [workspaceFilter, setWorkspaceFilter] =
        useState<WorkspaceFilter>('bimbingan');
    const [historyFilter, setHistoryFilter] = useState<HistoryFilter>('semua');
    const [historySearch, setHistorySearch] = useState('');
    const HISTORY_PAGE_SIZE = 15;

    // Sheet state for upcoming schedule actions
    const [selectedUpcoming, setSelectedUpcoming] =
        useState<UpcomingSchedule | null>(null);
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

    const [selectedEvent, setSelectedEvent] = useState<{
        id: number;
        topic: string;
        person: string;
        personRole: 'lecturer' | 'student';
        start: string;
        end: string;
        location: string;
        status: ScheduleModalStatus;
        notes?: string | null;
    } | null>(null);
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
            window.Echo.leaveChannel(`private-${channelName}`);
        };
    }, [auth.user?.id]);

    function decide(
        scheduleId: number,
        decision: 'approve' | 'reject' | 'reschedule' | 'complete' | 'cancel',
        item: PendingRequest,
    ) {
        const input = decisionFormById[scheduleId] ?? {
            scheduled_for: item.requestedForInput ?? '',
            location: item.location ?? 'Google Meet',
            lecturer_note: '',
        };

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
        const input = recurringFormById[groupId] ?? {
            location: items[0]?.location ?? 'Google Meet',
            lecturer_note: '',
        };
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
        const p = new Date(`${date} ${time}`);
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

    const historyPagination = usePagination(
        filteredHistorySchedules,
        HISTORY_PAGE_SIZE,
        [historyFilter, historySearch],
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
            });
        }
        setIsDetailModalOpen(true);
    }

    const workspaceFilterTabs: { label: string; value: WorkspaceFilter }[] = [
        { label: 'Bimbingan', value: 'bimbingan' },
        { label: 'Sempro / Sidang', value: 'ujian' },
        { label: 'Semua', value: 'semua' },
    ];

    const historyFilterGroups: FilterGroup[] = [
        {
            value: historyFilter,
            onChange: (v) => setHistoryFilter(v as HistoryFilter),
            tabs: [
                { label: 'Semua', value: 'semua' },
                { label: 'Selesai', value: 'completed' },
                { label: 'Ditolak', value: 'rejected' },
                { label: 'Dibatalkan', value: 'cancelled' },
            ],
        },
    ];

    return (
        <DosenLayout
            breadcrumbs={breadcrumbs}
            title="Jadwal Bimbingan"
            subtitle="Kelola permintaan jadwal dan agenda bimbingan"
        >
            <Head title="Jadwal Bimbingan Dosen" />

            <div className="mx-auto flex w-full max-w-7xl flex-col gap-8 px-4 py-6 md:px-6 lg:py-8">
                {/* Flash */}
                {flashMessage && (
                    <Alert>
                        <AlertTitle>Berhasil</AlertTitle>
                        <AlertDescription>{flashMessage}</AlertDescription>
                    </Alert>
                )}

                {/* ---------------- WORKSPACE CALENDAR ---------------- */}
                <section>
                    <div className="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 className="text-base font-semibold">
                                Workspace Jadwal
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Satu kalender untuk bimbingan, sempro, dan
                                sidang
                            </p>
                        </div>
                        {/* filter pills */}
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

                {/* ---------------- TWO-COLUMN: PENDING + UPCOMING ---------------- */}
                <div className="grid gap-8 lg:grid-cols-2">
                    {/* --- Permintaan Pending --- */}
                    <section>
                        <div className="mb-3 flex items-center justify-between">
                            <div>
                                <h2 className="text-base font-semibold">
                                    Permintaan Menunggu Konfirmasi
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Konfirmasi, jadwalkan ulang, atau tolak
                                    permintaan
                                </p>
                            </div>
                            {pendingRequests.length > 0 && (
                                <span className="inline-flex size-6 items-center justify-center rounded-full bg-amber-600/10 text-xs font-bold text-amber-700">
                                    {pendingRequests.length}
                                </span>
                            )}
                        </div>

                        {pendingRequests.length > 0 ? (
                            <div className="flex flex-col gap-4">
                                {Object.entries(groupedPendingRequests).map(
                                    ([groupKey, group]) => {
                                        const firstItem = group.items[0];

                                        if (group.isRecurringGroup) {
                                            return (
                                                <div
                                                    key={groupKey}
                                                    className="rounded-xl border bg-card p-5 shadow-sm"
                                                >
                                                    {/* Header */}
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <p className="text-sm font-semibold">
                                                            {
                                                                firstItem.mahasiswa
                                                            }
                                                        </p>
                                                        <Badge
                                                            variant="soft"
                                                            className="gap-1 rounded-full bg-blue-600/10 text-blue-600"
                                                        >
                                                            <Repeat className="size-3" />
                                                            {group.items.length}{' '}
                                                            Pertemuan
                                                        </Badge>
                                                        <RelationTypeBadge
                                                            relationType={
                                                                firstItem.relationType
                                                            }
                                                        />
                                                    </div>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        {firstItem.topic}
                                                    </p>

                                                    {/* Schedule list */}
                                                    <div className="mt-3 rounded-lg border bg-muted/30 p-3">
                                                        <p className="mb-1.5 text-[11px] font-medium tracking-wider text-muted-foreground uppercase">
                                                            Jadwal diajukan
                                                        </p>
                                                        <div className="space-y-1">
                                                            {group.items.map(
                                                                (item) => (
                                                                    <p
                                                                        key={
                                                                            item.id
                                                                        }
                                                                        className="text-xs"
                                                                    >
                                                                        {formatDateInIndonesian(
                                                                            item.requestedAt,
                                                                        )}
                                                                    </p>
                                                                ),
                                                            )}
                                                        </div>
                                                    </div>

                                                    {firstItem.studentNote && (
                                                        <div className="mt-3 rounded-md bg-muted/40 px-3 py-1.5 text-xs text-muted-foreground">
                                                            <span className="font-medium text-foreground">
                                                                Catatan
                                                                mahasiswa:{' '}
                                                            </span>
                                                            {
                                                                firstItem.studentNote
                                                            }
                                                        </div>
                                                    )}

                                                    {/* Decision form */}
                                                    <div className="mt-4 grid gap-2.5">
                                                        <div className="grid gap-1">
                                                            <Label
                                                                htmlFor={`recurring-location-${groupKey}`}
                                                            >
                                                                Lokasi untuk
                                                                Semua Jadwal
                                                            </Label>
                                                            <Input
                                                                id={`recurring-location-${groupKey}`}
                                                                value={
                                                                    recurringFormById[
                                                                        groupKey
                                                                    ]
                                                                        ?.location ??
                                                                    firstItem.location ??
                                                                    'Google Meet'
                                                                }
                                                                onChange={(e) =>
                                                                    setRecurringFormById(
                                                                        (
                                                                            c,
                                                                        ) => ({
                                                                            ...c,
                                                                            [groupKey]:
                                                                                {
                                                                                    location:
                                                                                        e
                                                                                            .target
                                                                                            .value,
                                                                                    lecturer_note:
                                                                                        c[
                                                                                            groupKey
                                                                                        ]
                                                                                            ?.lecturer_note ??
                                                                                        '',
                                                                                },
                                                                        }),
                                                                    )
                                                                }
                                                            />
                                                        </div>
                                                        <div className="grid gap-1">
                                                            <Label
                                                                htmlFor={`recurring-note-${groupKey}`}
                                                            >
                                                                Feedback ke
                                                                Mahasiswa
                                                            </Label>
                                                            <Textarea
                                                                id={`recurring-note-${groupKey}`}
                                                                value={
                                                                    recurringFormById[
                                                                        groupKey
                                                                    ]
                                                                        ?.lecturer_note ??
                                                                    ''
                                                                }
                                                                onChange={(
                                                                    e,
                                                                ) => {
                                                                    setRecurringFormById(
                                                                        (
                                                                            c,
                                                                        ) => ({
                                                                            ...c,
                                                                            [groupKey]:
                                                                                {
                                                                                    location:
                                                                                        c[
                                                                                            groupKey
                                                                                        ]
                                                                                            ?.location ??
                                                                                        firstItem.location ??
                                                                                        'Google Meet',
                                                                                    lecturer_note:
                                                                                        e
                                                                                            .target
                                                                                            .value,
                                                                                },
                                                                        }),
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
                                                                <p className="text-xs text-destructive">
                                                                    {
                                                                        recurringErrorsById[
                                                                            groupKey
                                                                        ]
                                                                            ?.lecturer_note
                                                                    }
                                                                </p>
                                                            )}
                                                        </div>
                                                        <div className="flex flex-wrap gap-2 pt-1">
                                                            <Button
                                                                size="sm"
                                                                variant="soft"
                                                                className="flex-1 bg-destructive/10 text-destructive hover:bg-destructive/20 sm:flex-none"
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
                                                                size="sm"
                                                                className="flex-1 sm:flex-none"
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
                                                </div>
                                            );
                                        }

                                        const item = firstItem;
                                        return (
                                            <div
                                                key={`${item.id}-${item.mahasiswa}`}
                                                className="rounded-xl border bg-card p-5 shadow-sm"
                                            >
                                                {/* Header */}
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="text-sm font-semibold">
                                                        {item.mahasiswa}
                                                    </p>
                                                    <RelationTypeBadge
                                                        relationType={
                                                            item.relationType
                                                        }
                                                    />
                                                </div>
                                                <p className="mt-0.5 text-xs text-muted-foreground">
                                                    {item.topic}
                                                </p>
                                                <p className="mt-2 flex items-center gap-1.5 text-xs text-muted-foreground">
                                                    <CalendarClock className="size-3.5 shrink-0" />
                                                    {formatDateInIndonesian(
                                                        item.requestedAt,
                                                    )}
                                                </p>

                                                {item.studentNote && (
                                                    <div className="mt-3 rounded-md bg-muted/40 px-3 py-1.5 text-xs text-muted-foreground">
                                                        <span className="font-medium text-foreground">
                                                            Catatan
                                                            mahasiswa:{' '}
                                                        </span>
                                                        {item.studentNote}
                                                    </div>
                                                )}

                                                {/* Decision form */}
                                                <div className="mt-4 grid gap-2.5">
                                                    <div className="grid gap-1">
                                                        <Label
                                                            htmlFor={`scheduled-${item.id}`}
                                                        >
                                                            Tanggal/Jam
                                                            Konfirmasi atau
                                                            Jadwal Ulang
                                                        </Label>
                                                        <Input
                                                            id={`scheduled-${item.id}`}
                                                            type="datetime-local"
                                                            value={
                                                                decisionFormById[
                                                                    item.id
                                                                ]
                                                                    ?.scheduled_for ??
                                                                item.requestedForInput ??
                                                                ''
                                                            }
                                                            onChange={(e) => {
                                                                setDecisionFormById(
                                                                    (c) => ({
                                                                        ...c,
                                                                        [item.id]:
                                                                            {
                                                                                scheduled_for:
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                location:
                                                                                    c[
                                                                                        item
                                                                                            .id
                                                                                    ]
                                                                                        ?.location ??
                                                                                    item.location ??
                                                                                    'Google Meet',
                                                                                lecturer_note:
                                                                                    c[
                                                                                        item
                                                                                            .id
                                                                                    ]
                                                                                        ?.lecturer_note ??
                                                                                    '',
                                                                            },
                                                                    }),
                                                                );
                                                                setDecisionErrorsById(
                                                                    (c) => ({
                                                                        ...c,
                                                                        [item.id]:
                                                                            {
                                                                                ...c[
                                                                                    item
                                                                                        .id
                                                                                ],
                                                                                scheduled_for:
                                                                                    undefined,
                                                                            },
                                                                    }),
                                                                );
                                                            }}
                                                        />
                                                        {decisionErrorsById[
                                                            item.id
                                                        ]?.scheduled_for && (
                                                            <p className="text-xs text-destructive">
                                                                {
                                                                    decisionErrorsById[
                                                                        item.id
                                                                    ]
                                                                        ?.scheduled_for
                                                                }
                                                            </p>
                                                        )}
                                                    </div>
                                                    <div className="grid gap-1">
                                                        <Label
                                                            htmlFor={`location-${item.id}`}
                                                        >
                                                            Lokasi
                                                        </Label>
                                                        <Input
                                                            id={`location-${item.id}`}
                                                            value={
                                                                decisionFormById[
                                                                    item.id
                                                                ]?.location ??
                                                                item.location ??
                                                                'Google Meet'
                                                            }
                                                            onChange={(e) =>
                                                                setDecisionFormById(
                                                                    (c) => ({
                                                                        ...c,
                                                                        [item.id]:
                                                                            {
                                                                                scheduled_for:
                                                                                    c[
                                                                                        item
                                                                                            .id
                                                                                    ]
                                                                                        ?.scheduled_for ??
                                                                                    item.requestedForInput ??
                                                                                    '',
                                                                                location:
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                lecturer_note:
                                                                                    c[
                                                                                        item
                                                                                            .id
                                                                                    ]
                                                                                        ?.lecturer_note ??
                                                                                    '',
                                                                            },
                                                                    }),
                                                                )
                                                            }
                                                        />
                                                    </div>
                                                    <div className="grid gap-1">
                                                        <Label
                                                            htmlFor={`note-${item.id}`}
                                                        >
                                                            Feedback ke
                                                            Mahasiswa
                                                        </Label>
                                                        <Textarea
                                                            id={`note-${item.id}`}
                                                            value={
                                                                decisionFormById[
                                                                    item.id
                                                                ]
                                                                    ?.lecturer_note ??
                                                                ''
                                                            }
                                                            onChange={(e) => {
                                                                setDecisionFormById(
                                                                    (c) => ({
                                                                        ...c,
                                                                        [item.id]:
                                                                            {
                                                                                scheduled_for:
                                                                                    c[
                                                                                        item
                                                                                            .id
                                                                                    ]
                                                                                        ?.scheduled_for ??
                                                                                    item.requestedForInput ??
                                                                                    '',
                                                                                location:
                                                                                    c[
                                                                                        item
                                                                                            .id
                                                                                    ]
                                                                                        ?.location ??
                                                                                    item.location ??
                                                                                    'Google Meet',
                                                                                lecturer_note:
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                            },
                                                                    }),
                                                                );
                                                                setDecisionErrorsById(
                                                                    (c) => ({
                                                                        ...c,
                                                                        [item.id]:
                                                                            {
                                                                                ...c[
                                                                                    item
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
                                                        {decisionErrorsById[
                                                            item.id
                                                        ]?.lecturer_note && (
                                                            <p className="text-xs text-destructive">
                                                                {
                                                                    decisionErrorsById[
                                                                        item.id
                                                                    ]
                                                                        ?.lecturer_note
                                                                }
                                                            </p>
                                                        )}
                                                    </div>
                                                    <div className="flex flex-wrap gap-2 pt-1">
                                                        <Button
                                                            size="sm"
                                                            variant="soft"
                                                            className="flex-1 sm:flex-none"
                                                            disabled={
                                                                form.processing
                                                            }
                                                            onClick={() =>
                                                                decide(
                                                                    item.id,
                                                                    'reschedule',
                                                                    item,
                                                                )
                                                            }
                                                        >
                                                            Jadwalkan Ulang
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="soft"
                                                            className="flex-1 bg-destructive/10 text-destructive hover:bg-destructive/20 sm:flex-none"
                                                            disabled={
                                                                form.processing
                                                            }
                                                            onClick={() =>
                                                                decide(
                                                                    item.id,
                                                                    'reject',
                                                                    item,
                                                                )
                                                            }
                                                        >
                                                            Tolak
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            className="flex-1 sm:flex-none"
                                                            disabled={
                                                                form.processing
                                                            }
                                                            onClick={() =>
                                                                decide(
                                                                    item.id,
                                                                    'approve',
                                                                    item,
                                                                )
                                                            }
                                                        >
                                                            Konfirmasi
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>
                                        );
                                    },
                                )}
                            </div>
                        ) : (
                            <EmptyState
                                icon={Inbox}
                                title="Belum ada permintaan baru"
                                description="Permintaan bimbingan dari mahasiswa akan tampil di sini."
                            />
                        )}
                    </section>

                    {/* --- Jadwal Mendatang (compact table + sheet) --- */}
                    <section>
                        <div className="mb-3 flex items-center justify-between">
                            <div>
                                <h2 className="text-base font-semibold">
                                    Jadwal Mendatang
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Agenda bimbingan terkonfirmasi
                                </p>
                            </div>
                            {upcomingSchedules.length > 0 && (
                                <span className="text-xs text-muted-foreground">
                                    {upcomingSchedules.length} jadwal
                                </span>
                            )}
                        </div>

                        {upcomingSchedules.length > 0 ? (
                            <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b bg-muted/30">
                                            <th className="px-4 py-2.5 text-left text-xs font-medium text-muted-foreground">
                                                Mahasiswa
                                            </th>
                                            <th className="hidden px-4 py-2.5 text-left text-xs font-medium text-muted-foreground md:table-cell">
                                                Topik
                                            </th>
                                            <th className="px-4 py-2.5 text-left text-xs font-medium text-muted-foreground">
                                                Waktu
                                            </th>
                                            <th className="hidden px-4 py-2.5 text-left text-xs font-medium text-muted-foreground sm:table-cell">
                                                Lokasi
                                            </th>
                                            <th className="w-8 px-4 py-2.5" />
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {upcomingSchedules.map((item) => (
                                            <tr
                                                key={`${item.id}-${item.mahasiswa}`}
                                                className="cursor-pointer transition-colors hover:bg-muted/30"
                                                onClick={() => {
                                                    setSelectedUpcoming(item);
                                                    setUpcomingSheetOpen(true);
                                                }}
                                            >
                                                <td className="px-4 py-3">
                                                    <p className="font-medium">
                                                        {item.mahasiswa}
                                                    </p>
                                                    <RelationTypeBadge
                                                        relationType={
                                                            item.relationType
                                                        }
                                                    />
                                                </td>
                                                <td className="hidden max-w-[200px] px-4 py-3 md:table-cell">
                                                    <p className="line-clamp-2 text-xs text-muted-foreground">
                                                        {item.topic}
                                                    </p>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span className="flex items-center gap-1.5 text-xs whitespace-nowrap text-muted-foreground">
                                                        <CalendarClock className="size-3 shrink-0" />
                                                        {formatDateTime(
                                                            item.date,
                                                            item.time,
                                                        )}
                                                    </span>
                                                </td>
                                                <td className="hidden px-4 py-3 sm:table-cell">
                                                    <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                                        <MapPin className="size-3 shrink-0" />
                                                        {item.location}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    <ChevronRight className="size-4" />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                <div className="border-t px-4 py-2 text-right text-xs text-muted-foreground">
                                    {upcomingSchedules.length} jadwal mendatang
                                </div>
                            </div>
                        ) : (
                            <EmptyState
                                icon={CalendarClock}
                                title="Belum ada jadwal mendatang"
                                description="Agenda yang sudah dikonfirmasi akan muncul di bagian ini."
                            />
                        )}
                    </section>
                </div>

                {/* ---------------- HISTORY TABLE ---------------- */}
                <section>
                    <div className="mb-3">
                        <h2 className="text-base font-semibold">
                            Riwayat Jadwal
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Sesi bimbingan yang sudah selesai, ditolak, atau
                            dibatalkan
                        </p>
                    </div>

                    {/* Toolbar: search (left) + filter pills (right) */}
                    <DataTableToolbar
                        search={historySearch}
                        onSearchChange={setHistorySearch}
                        searchPlaceholder="Cari mahasiswa, topik, atau lokasi..."
                        filterGroups={historyFilterGroups}
                        className="mb-3"
                    />

                    {historyPagination.totalItems > 0 ? (
                        <DataTableContainer>
                            <table className="w-full text-left text-sm">
                                <thead className="border-b bg-muted/30">
                                    <tr>
                                        <th className="px-5 py-3 text-xs font-medium text-muted-foreground">
                                            Mahasiswa
                                        </th>
                                        <th className="px-5 py-3 text-xs font-medium text-muted-foreground">
                                            Topik
                                        </th>
                                        <th className="px-5 py-3 text-xs font-medium text-muted-foreground">
                                            Waktu
                                        </th>
                                        <th className="px-5 py-3 text-xs font-medium text-muted-foreground">
                                            Lokasi
                                        </th>
                                        <th className="px-5 py-3 text-xs font-medium text-muted-foreground">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y bg-card">
                                    {historyPagination.paginated.map((item) => (
                                        <tr
                                            key={`${item.id}-${item.status}`}
                                            className="transition-colors hover:bg-muted/20"
                                        >
                                            <td className="px-5 py-3.5 align-middle">
                                                <p className="text-sm font-medium">
                                                    {item.mahasiswa}
                                                </p>
                                                <RelationTypeBadge
                                                    relationType={
                                                        item.relationType
                                                    }
                                                />
                                            </td>
                                            <td className="max-w-[180px] px-5 py-3.5 align-middle text-xs text-muted-foreground">
                                                <p className="truncate">
                                                    {item.topic}
                                                </p>
                                                {item.lecturerNote && (
                                                    <p className="mt-0.5 line-clamp-1 italic opacity-70">
                                                        {item.lecturerNote}
                                                    </p>
                                                )}
                                            </td>
                                            <td className="px-5 py-3.5 align-middle text-xs whitespace-nowrap text-muted-foreground">
                                                {formatDateTime(
                                                    item.date,
                                                    item.time,
                                                )}
                                            </td>
                                            <td className="px-5 py-3.5 align-middle text-xs text-muted-foreground">
                                                <span className="flex items-center gap-1.5">
                                                    <MapPin className="size-3 shrink-0" />
                                                    {item.location}
                                                </span>
                                            </td>
                                            <td className="px-5 py-3.5 align-middle">
                                                <StatusBadge
                                                    status={item.status}
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            <DataTablePagination
                                currentPage={historyPagination.page}
                                totalPages={historyPagination.totalPages}
                                totalItems={historyPagination.totalItems}
                                pageSize={HISTORY_PAGE_SIZE}
                                onPageChange={historyPagination.setPage}
                                itemLabel="riwayat"
                            />
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
            </div>

            <ScheduleDetailModal
                open={isDetailModalOpen}
                onOpenChange={setIsDetailModalOpen}
                schedule={selectedEvent}
                currentUserRole="dosen"
            />

            {/* ── Upcoming Schedule Action Sheet ── */}
            <Sheet open={upcomingSheetOpen} onOpenChange={setUpcomingSheetOpen}>
                <SheetContent
                    side="right"
                    className="w-full gap-0 p-0 sm:max-w-md"
                >
                    <SheetHeader className="border-b bg-muted/20 px-6 py-4">
                        <div className="flex items-center gap-2 pr-6">
                            <StatusBadge
                                status={selectedUpcoming?.status ?? 'approved'}
                            />
                        </div>
                        <SheetTitle className="text-base leading-snug">
                            {selectedUpcoming?.mahasiswa}
                        </SheetTitle>
                        <SheetDescription className="line-clamp-2">
                            {selectedUpcoming?.topic}
                        </SheetDescription>
                    </SheetHeader>

                    <ScrollArea className="h-[calc(100vh-8rem)]">
                        <div className="space-y-5 px-6 py-5">
                            {selectedUpcoming && (
                                <>
                                    {/* Info */}
                                    <div className="grid gap-2 rounded-xl border bg-muted/20 p-4 text-sm">
                                        <div className="flex items-center gap-2 text-muted-foreground">
                                            <CalendarClock className="size-3.5 shrink-0" />
                                            <span>
                                                {formatDateTime(
                                                    selectedUpcoming.date,
                                                    selectedUpcoming.time,
                                                )}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2 text-muted-foreground">
                                            <MapPin className="size-3.5 shrink-0" />
                                            <span>
                                                {selectedUpcoming.location}
                                            </span>
                                        </div>
                                        <RelationTypeBadge
                                            relationType={
                                                selectedUpcoming.relationType
                                            }
                                        />
                                    </div>

                                    {selectedUpcoming.lecturerNote && (
                                        <div className="rounded-xl border bg-muted/20 px-4 py-3 text-xs text-muted-foreground">
                                            <span className="font-medium text-foreground">
                                                Catatan:{' '}
                                            </span>
                                            {selectedUpcoming.lecturerNote}
                                        </div>
                                    )}

                                    {/* Actions */}
                                    <div>
                                        <p className="mb-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                            Tindakan
                                        </p>
                                        <div className="flex flex-col gap-2">
                                            <Button
                                                className="w-full"
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
                                                variant="soft"
                                                className="w-full bg-destructive/10 text-destructive hover:bg-destructive/20"
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
        </DosenLayout>
    );
}
