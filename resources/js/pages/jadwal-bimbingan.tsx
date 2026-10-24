import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Calendar,
    CheckCircle2,
    Clock,
    Inbox,
    MapPin,
    Plus,
    Repeat,
    Send,
    Users,
    XCircle,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { ArchivedMentorshipNotice } from '@/components/archived-mentorship-notice';
import { type BimbinganEvent } from '@/components/bimbingan-calendar';
import { CollapsibleCalendarSection } from '@/components/collapsible-calendar-section';
import {
    DataSection,
    DataToolbar,
    dataTh,
    stackedTable,
} from '@/components/patterns/data-section';
import { FilterPills } from '@/components/patterns/filter-pills';
import { PageHeader } from '@/components/patterns/page-header';
import { StatusBadge } from '@/components/patterns/status-badge';
import { StickyFilterBar } from '@/components/patterns/sticky-filter-bar';
import { ScheduleDetailModal } from '@/components/schedule-detail-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DataTableContainer,
    DataTableEmptyState,
    DataTablePagination,
    usePagination,
} from '@/components/ui/data-table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useUrlState } from '@/hooks/use-url-state';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { dashboard, jadwalBimbingan } from '@/routes';
import { type BreadcrumbItem, type SharedData } from '@/types';

type ScheduleStatus =
    | 'pending'
    | 'approved'
    | 'rescheduled'
    | 'rejected'
    | 'completed'
    | 'cancelled';

type UpcomingMeeting = {
    id: number;
    topic: string;
    lecturer: string;
    relationType: 'pembimbing' | 'penguji';
    requestedAt: string;
    scheduledAt: string | null;
    location: string;
    status: ScheduleStatus;
    lecturerNote: string | null;
    createdByLecturer: boolean;
};

type HistoryMeeting = {
    id: number;
    topic: string;
    lecturer: string;
    relationType: 'pembimbing' | 'penguji';
    scheduledAt: string;
    location: string;
    status: ScheduleStatus;
    lecturerNote: string | null;
    createdByLecturer: boolean;
};

type JadwalPageProps = {
    hasDosbing: boolean;
    advisors: Array<{
        assignmentId: number;
        lecturerUserId: number;
        lecturerName: string;
        advisorType: string;
    }>;
    upcomingMeetings: UpcomingMeeting[];
    historyMeetings: HistoryMeeting[];
    archivedProject: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Jadwal Bimbingan', href: jadwalBimbingan().url },
];

const statusDisplay: Record<
    ScheduleStatus,
    { label: string; icon: typeof CheckCircle2 }
> = {
    completed: { label: 'Selesai', icon: CheckCircle2 },
    cancelled: { label: 'Dibatalkan', icon: XCircle },
    approved: { label: 'Terjadwal', icon: CheckCircle2 },
    rescheduled: { label: 'Terjadwal', icon: CheckCircle2 },
    pending: { label: 'Menunggu Konfirmasi', icon: Clock },
    rejected: { label: 'Ditolak', icon: XCircle },
};

function ScheduleStatusBadge({ status }: { status: ScheduleStatus }) {
    const { label, icon: Icon } =
        statusDisplay[status] ?? statusDisplay.cancelled;

    return (
        <StatusBadge
            domain="schedule"
            // Rescheduled sessions are shown as plain "Terjadwal", like approved ones.
            status={status === 'rescheduled' ? 'approved' : status}
            className="gap-1 whitespace-nowrap"
        >
            <Icon className="size-3" />
            {label}
        </StatusBadge>
    );
}

function CreatedByLecturerBadge() {
    return (
        <Badge
            variant="outline"
            className="gap-1 rounded-full border-transparent bg-primary-muted text-primary-muted-foreground"
        >
            Dijadwalkan dosen
        </Badge>
    );
}

function RelationTypeBadge({
    relationType,
}: {
    relationType: 'pembimbing' | 'penguji';
}) {
    return (
        <Badge
            variant="outline"
            className={cn(
                'gap-1 rounded-full border-transparent',
                relationType === 'penguji'
                    ? 'bg-warning-muted text-warning-muted-foreground'
                    : 'bg-info-muted text-info-muted-foreground',
            )}
        >
            {relationType === 'penguji' ? 'Penguji' : 'Pembimbing'}
        </Badge>
    );
}

const historyFilterOptions = [
    { value: 'semua', label: 'Semua' },
    { value: 'completed', label: 'Selesai' },
    { value: 'rejected', label: 'Ditolak' },
    { value: 'cancelled', label: 'Dibatalkan' },
] as const;

function FieldError({ id, message }: { id: string; message?: string }) {
    if (!message) return null;

    return (
        <p id={id} role="alert" className="text-sm text-destructive">
            {message}
        </p>
    );
}

export default function JadwalBimbinganPage() {
    const page = usePage<SharedData & JadwalPageProps>();
    const terms = page.props.academicTerminology;
    const query = page.url.split('?')[1] ?? '';
    const defaultLecturerUserId = page.props.advisors[0]?.lecturerUserId;
    const [isAjukanOpen, setIsAjukanOpen] = useState(
        new URLSearchParams(query).get('open') === 'ajukan',
    );

    const PAGE_SIZE = 15;

    // Upcoming pagination
    const upcomingPageState = useUrlState('upcomingPage', 1);
    const upcomingPagination = usePagination(
        page.props.upcomingMeetings,
        PAGE_SIZE,
        [],
        upcomingPageState,
    );

    // History search + status filter + pagination
    const [historySearch, setHistorySearch] = useUrlState('historySearch', '');
    const [historyStatusFilter, setHistoryStatusFilter] = useUrlState<
        'semua' | ScheduleStatus
    >('historyStatus', 'semua');
    const historyPageState = useUrlState('historyPage', 1);
    const filteredHistory = useMemo(() => {
        const q = historySearch.trim().toLowerCase();
        return page.props.historyMeetings.filter((m) => {
            const matchesStatus =
                historyStatusFilter === 'semua' ||
                m.status === historyStatusFilter;
            const matchesSearch =
                !q ||
                m.topic.toLowerCase().includes(q) ||
                m.lecturer.toLowerCase().includes(q) ||
                m.location.toLowerCase().includes(q);
            return matchesStatus && matchesSearch;
        });
    }, [page.props.historyMeetings, historySearch, historyStatusFilter]);
    const historyPagination = usePagination(
        filteredHistory,
        PAGE_SIZE,
        [historySearch, historyStatusFilter],
        historyPageState,
    );

    const form = useForm({
        topic: '',
        lecturer_user_id:
            defaultLecturerUserId === undefined
                ? ''
                : String(defaultLecturerUserId),
        requested_for: '',
        meeting_type: 'offline',
        student_note: '',
        is_recurring: false,
        recurring_pattern: 'weekly',
        recurring_count: 4,
    });

    useEffect(() => {
        const userId = page.props.auth.user?.id;
        if (typeof window === 'undefined' || !window.Echo || !userId) return;

        const channelName = `schedule.user.${userId}`;
        const channel = window.Echo.private(channelName).listen(
            '.schedule.updated',
            () => {
                router.reload({
                    only: ['upcomingMeetings', 'historyMeetings'],
                });
            },
        );

        return () => {
            channel.stopListening('.schedule.updated');
            window.Echo?.leaveChannel(`private-${channelName}`);
        };
    }, [page.props.auth.user?.id]);

    function submitRequest() {
        form.post('/mahasiswa/jadwal-bimbingan', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setIsAjukanOpen(false);
            },
        });
    }

    const [selectedEvent, setSelectedEvent] = useState<{
        id: number;
        topic: string;
        person: string;
        personRole: 'lecturer' | 'student';
        start: string;
        end: string;
        location: string;
        status: 'pending' | 'approved' | 'rejected' | 'completed' | 'cancelled';
        notes?: string | null;
    } | null>(null);

    const [isDetailModalOpen, setIsDetailModalOpen] = useState(false);

    const formatDate = (dateStr: string | null) => {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleString('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    function formatDateForCalendar(dateInput: string | Date): string {
        if (typeof dateInput === 'string') return dateInput;
        if (isNaN(dateInput.getTime())) return '';
        return dateInput.toISOString();
    }

    const calendarEvents: BimbinganEvent[] = [
        ...page.props.upcomingMeetings.flatMap((meeting) => {
            const startDate = meeting.scheduledAt || meeting.requestedAt;
            if (!startDate) return [];
            const start = formatDateForCalendar(startDate);
            if (!start) return [];
            const endDate = new Date(
                new Date(startDate).getTime() + 60 * 60 * 1000,
            );
            return [
                {
                    id: meeting.id,
                    title: meeting.topic,
                    topic: meeting.topic,
                    person: meeting.lecturer,
                    start,
                    end: formatDateForCalendar(endDate),
                    location: meeting.location,
                    status: meeting.status as BimbinganEvent['status'],
                    personRole: 'lecturer' as const,
                },
            ];
        }),
        ...page.props.historyMeetings.flatMap((meeting) => {
            if (!meeting.scheduledAt) return [];
            const start = formatDateForCalendar(meeting.scheduledAt);
            if (!start) return [];
            const endDate = new Date(
                new Date(meeting.scheduledAt).getTime() + 60 * 60 * 1000,
            );
            return [
                {
                    id: meeting.id,
                    title: meeting.topic,
                    topic: meeting.topic,
                    person: meeting.lecturer,
                    start,
                    end: formatDateForCalendar(endDate),
                    location: meeting.location,
                    status: meeting.status as BimbinganEvent['status'],
                    personRole: 'lecturer' as const,
                },
            ];
        }),
    ];

    function handleEventClick(event: BimbinganEvent) {
        const fullMeeting = [
            ...page.props.upcomingMeetings,
            ...page.props.historyMeetings,
        ].find((m) => m.id === event.id);
        if (!fullMeeting) return;
        setSelectedEvent({
            id: fullMeeting.id,
            topic: fullMeeting.topic,
            person: fullMeeting.lecturer,
            personRole: 'lecturer',
            start: event.start,
            end: event.end,
            location: fullMeeting.location,
            status: fullMeeting.status as
                | 'pending'
                | 'approved'
                | 'rejected'
                | 'completed'
                | 'cancelled',
            notes: fullMeeting.lecturerNote,
        });
        setIsDetailModalOpen(true);
    }

    const selectTrigger = 'max-md:h-11';
    const hasHistoryFilter =
        historySearch !== '' || historyStatusFilter !== 'semua';

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Jadwal Bimbingan"
            subtitle={`Kelola jadwal bimbingan ${terms.finalWorkLower} dengan dosen pembimbing`}
        >
            <Head title="Jadwal Bimbingan" />

            {/* Request dialog */}
            <Dialog open={isAjukanOpen} onOpenChange={setIsAjukanOpen}>
                <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Ajukan Jadwal Bimbingan</DialogTitle>
                        <DialogDescription>
                            Isi formulir untuk mengajukan jadwal bimbingan
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        className="grid gap-5"
                        onSubmit={(e) => {
                            e.preventDefault();
                            submitRequest();
                        }}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="topic">Topik Bimbingan</Label>
                            <Input
                                id="topic"
                                className="max-md:h-11"
                                value={form.data.topic}
                                onChange={(e) =>
                                    form.setData('topic', e.target.value)
                                }
                                required
                                aria-invalid={!!form.errors.topic}
                                aria-describedby={
                                    form.errors.topic
                                        ? 'topic-error'
                                        : undefined
                                }
                                placeholder="Contoh: Review Bab 3 - Metodologi Penelitian"
                            />
                            <FieldError
                                id="topic-error"
                                message={form.errors.topic}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="lecturer_user_id">
                                Dosen Tujuan
                            </Label>
                            <Select
                                value={form.data.lecturer_user_id}
                                onValueChange={(v) =>
                                    form.setData('lecturer_user_id', v)
                                }
                            >
                                <SelectTrigger
                                    id="lecturer_user_id"
                                    className={selectTrigger}
                                    aria-invalid={
                                        !!form.errors.lecturer_user_id
                                    }
                                    aria-describedby={
                                        form.errors.lecturer_user_id
                                            ? 'lecturer-error'
                                            : undefined
                                    }
                                >
                                    <SelectValue placeholder="Pilih dosen pembimbing" />
                                </SelectTrigger>
                                <SelectContent>
                                    {page.props.advisors.map((a) => (
                                        <SelectItem
                                            key={`${a.assignmentId}-${a.lecturerUserId}`}
                                            value={String(a.lecturerUserId)}
                                        >
                                            {a.lecturerName} (
                                            {a.advisorType === 'primary' &&
                                                'Pembimbing 1'}
                                            {a.advisorType === 'secondary' &&
                                                'Pembimbing 2'}
                                            {a.advisorType === 'penguji' &&
                                                'Penguji'}
                                            )
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <FieldError
                                id="lecturer-error"
                                message={form.errors.lecturer_user_id}
                            />
                        </div>

                        <div className="grid gap-5 sm:grid-cols-2">
                            <div className="grid content-start gap-2">
                                <Label htmlFor="requested_for">
                                    Tanggal &amp; Waktu Preferensi
                                </Label>
                                <Input
                                    id="requested_for"
                                    className="max-md:h-11"
                                    type="datetime-local"
                                    value={form.data.requested_for}
                                    onChange={(e) =>
                                        form.setData(
                                            'requested_for',
                                            e.target.value,
                                        )
                                    }
                                    required
                                    aria-invalid={!!form.errors.requested_for}
                                    aria-describedby={
                                        form.errors.requested_for
                                            ? 'requested-for-error'
                                            : undefined
                                    }
                                />
                                <FieldError
                                    id="requested-for-error"
                                    message={form.errors.requested_for}
                                />
                            </div>

                            <div className="grid content-start gap-2">
                                <Label htmlFor="meeting_type">
                                    Tipe Bimbingan
                                </Label>
                                <Select
                                    value={form.data.meeting_type}
                                    onValueChange={(v) =>
                                        form.setData('meeting_type', v)
                                    }
                                >
                                    <SelectTrigger
                                        id="meeting_type"
                                        className={selectTrigger}
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="offline">
                                            Offline (Tatap Muka)
                                        </SelectItem>
                                        <SelectItem value="online">
                                            Online
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <FieldError
                                    id="meeting-type-error"
                                    message={form.errors.meeting_type}
                                />
                            </div>
                        </div>

                        <div className="grid gap-3 border-t pt-5">
                            <div className="flex min-h-11 items-center gap-3 md:min-h-0">
                                <input
                                    type="checkbox"
                                    id="is_recurring"
                                    checked={form.data.is_recurring}
                                    onChange={(e) =>
                                        form.setData(
                                            'is_recurring',
                                            e.target.checked,
                                        )
                                    }
                                    className="size-5 rounded border-input md:size-4"
                                />
                                <Label
                                    htmlFor="is_recurring"
                                    className="flex flex-1 items-center gap-2 py-2 font-medium md:py-0"
                                >
                                    <Repeat className="size-4" />
                                    Jadwalkan Berulang
                                </Label>
                            </div>

                            {form.data.is_recurring && (
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="recurring_pattern">
                                            Pola Pengulangan
                                        </Label>
                                        <Select
                                            value={form.data.recurring_pattern}
                                            onValueChange={(v) =>
                                                form.setData(
                                                    'recurring_pattern',
                                                    v,
                                                )
                                            }
                                        >
                                            <SelectTrigger
                                                id="recurring_pattern"
                                                className={selectTrigger}
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="weekly">
                                                    Mingguan
                                                </SelectItem>
                                                <SelectItem value="biweekly">
                                                    2 Mingguan
                                                </SelectItem>
                                                <SelectItem value="monthly">
                                                    Bulanan
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="recurring_count">
                                            Jumlah Pertemuan
                                        </Label>
                                        <Select
                                            value={String(
                                                form.data.recurring_count,
                                            )}
                                            onValueChange={(v) =>
                                                form.setData(
                                                    'recurring_count',
                                                    parseInt(v, 10),
                                                )
                                            }
                                        >
                                            <SelectTrigger
                                                id="recurring_count"
                                                className={selectTrigger}
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {[2, 3, 4, 5, 6, 7, 8].map(
                                                    (n) => (
                                                        <SelectItem
                                                            key={n}
                                                            value={String(n)}
                                                        >
                                                            {n} pertemuan
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <p className="text-sm text-muted-foreground sm:col-span-2">
                                        Akan dibuat {form.data.recurring_count}{' '}
                                        jadwal dengan pola{' '}
                                        {form.data.recurring_pattern ===
                                            'weekly' && 'mingguan'}
                                        {form.data.recurring_pattern ===
                                            'biweekly' && '2 mingguan'}
                                        {form.data.recurring_pattern ===
                                            'monthly' && 'bulanan'}
                                        .
                                    </p>
                                </div>
                            )}
                        </div>

                        <div className="grid gap-2 border-t pt-5">
                            <Label htmlFor="student_note">
                                Catatan Tambahan (Opsional)
                            </Label>
                            <Textarea
                                id="student_note"
                                value={form.data.student_note}
                                onChange={(e) =>
                                    form.setData('student_note', e.target.value)
                                }
                                aria-describedby={
                                    form.errors.student_note
                                        ? 'student-note-error'
                                        : undefined
                                }
                                placeholder="Jelaskan hal-hal yang ingin didiskusikan..."
                            />
                            <FieldError
                                id="student-note-error"
                                message={form.errors.student_note}
                            />
                        </div>

                        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full max-md:h-11 sm:w-auto"
                                onClick={() => setIsAjukanOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                className="w-full max-md:h-11 sm:w-auto"
                                disabled={
                                    form.processing ||
                                    page.props.mentorshipTicket?.suspended ===
                                        true
                                }
                            >
                                <Send className="size-4" />
                                Kirim Permintaan
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-8 px-4 py-6 md:px-6">
                <PageHeader
                    title="Jadwal Bimbingan"
                    description={`Kelola jadwal bimbingan ${terms.finalWorkLower} dengan dosen pembimbing`}
                    actions={
                        page.props.hasDosbing ? (
                            <Button
                                type="button"
                                className="w-full gap-2 max-md:h-11 sm:w-auto"
                                disabled={
                                    page.props.mentorshipTicket?.suspended ===
                                    true
                                }
                                onClick={() => setIsAjukanOpen(true)}
                            >
                                <Plus className="size-4" />
                                Ajukan Bimbingan
                            </Button>
                        ) : undefined
                    }
                />

                {page.props.archivedProject ? (
                    <ArchivedMentorshipNotice finalWork={terms.finalWork} />
                ) : !page.props.auth.activeRole || !page.props.hasDosbing ? (
                    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed px-4 py-16 text-center">
                        <Users
                            aria-hidden="true"
                            className="mb-4 size-12 text-muted-foreground/50"
                        />
                        <h2 className="mb-2 text-lg font-semibold text-balance">
                            Fitur Bimbingan Belum Aktif
                        </h2>
                        <p className="max-w-md text-sm text-pretty text-muted-foreground">
                            Anda belum memiliki Dosen Pembimbing yang
                            ditugaskan. Fitur jadwal bimbingan akan otomatis
                            aktif setelah admin menetapkan dosen pembimbing
                            untuk Anda.
                        </p>
                    </div>
                ) : (
                    <>
                        {/* Calendar */}
                        <CollapsibleCalendarSection
                            storageKey="jadwal-bimbingan-mahasiswa"
                            title="Kalender Bimbingan"
                            description="Jadwal bimbingan Anda dalam tampilan kalender atau daftar"
                            events={calendarEvents}
                            onEventClick={handleEventClick}
                            defaultView="list"
                        />

                        {/* Upcoming */}
                        <DataSection
                            title="Bimbingan Akan Datang"
                            summary={`${page.props.upcomingMeetings.length} jadwal diajukan atau terkonfirmasi`}
                        >
                            {page.props.upcomingMeetings.length > 0 ? (
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
                                            'md:min-w-[960px]',
                                        )}
                                    >
                                        <thead className={stackedTable.thead}>
                                            <tr className="border-b bg-muted/40">
                                                <th className={dataTh}>
                                                    Topik
                                                </th>
                                                <th className={dataTh}>
                                                    Dosen
                                                </th>
                                                <th className={dataTh}>
                                                    Waktu Preferensi
                                                </th>
                                                <th className={dataTh}>
                                                    Waktu Terkonfirmasi
                                                </th>
                                                <th className={dataTh}>
                                                    Lokasi
                                                </th>
                                                <th className={dataTh}>
                                                    Status
                                                </th>
                                                <th className={dataTh}>
                                                    Catatan Dosen
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
                                                (meeting) => (
                                                    <tr
                                                        key={meeting.id}
                                                        className={cn(
                                                            stackedTable.tr,
                                                            'align-top',
                                                        )}
                                                    >
                                                        <td
                                                            className={
                                                                stackedTable.tdPlain
                                                            }
                                                        >
                                                            <p
                                                                title={
                                                                    meeting.topic
                                                                }
                                                                className="min-w-40 text-sm leading-snug font-medium text-pretty max-md:line-clamp-2 max-md:min-w-0"
                                                            >
                                                                {meeting.topic}
                                                            </p>
                                                            {meeting.createdByLecturer && (
                                                                <div className="mt-1">
                                                                    <CreatedByLecturerBadge />
                                                                </div>
                                                            )}
                                                        </td>
                                                        <td
                                                            className={
                                                                stackedTable.td
                                                            }
                                                        >
                                                            <p className="text-sm max-md:inline">
                                                                {
                                                                    meeting.lecturer
                                                                }
                                                            </p>
                                                            <div className="mt-1 max-md:mt-0 max-md:ml-1 max-md:inline-block">
                                                                <RelationTypeBadge
                                                                    relationType={
                                                                        meeting.relationType
                                                                    }
                                                                />
                                                            </div>
                                                        </td>
                                                        <td
                                                            className={cn(
                                                                stackedTable.td,
                                                                'text-sm md:whitespace-nowrap',
                                                            )}
                                                            data-label="Waktu preferensi"
                                                        >
                                                            {formatDate(
                                                                meeting.requestedAt,
                                                            )}
                                                        </td>
                                                        <td
                                                            className={cn(
                                                                stackedTable.td,
                                                                'text-sm',
                                                            )}
                                                            data-label="Waktu terkonfirmasi"
                                                        >
                                                            {meeting.scheduledAt ? (
                                                                <span className="md:whitespace-nowrap">
                                                                    {formatDate(
                                                                        meeting.scheduledAt,
                                                                    )}
                                                                </span>
                                                            ) : (
                                                                <span className="inline-flex items-center gap-1 text-warning-muted-foreground">
                                                                    <Clock
                                                                        aria-hidden="true"
                                                                        className="size-3.5 shrink-0"
                                                                    />
                                                                    Menunggu
                                                                    konfirmasi
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td
                                                            className={cn(
                                                                stackedTable.td,
                                                                'text-sm',
                                                            )}
                                                        >
                                                            <span className="inline-flex items-start gap-1.5">
                                                                <MapPin
                                                                    aria-hidden="true"
                                                                    className="mt-0.5 size-3.5 shrink-0 text-muted-foreground"
                                                                />
                                                                {
                                                                    meeting.location
                                                                }
                                                            </span>
                                                        </td>
                                                        <td
                                                            className={
                                                                stackedTable.tdAside
                                                            }
                                                        >
                                                            <ScheduleStatusBadge
                                                                status={
                                                                    meeting.status
                                                                }
                                                            />
                                                        </td>
                                                        <td
                                                            className={cn(
                                                                stackedTable.tdDetail,
                                                                'text-sm',
                                                                !meeting.lecturerNote &&
                                                                    'max-md:hidden',
                                                            )}
                                                        >
                                                            {meeting.lecturerNote ? (
                                                                <p
                                                                    title={
                                                                        meeting.lecturerNote
                                                                    }
                                                                    className="min-w-40 text-pretty max-md:line-clamp-2 max-md:min-w-0"
                                                                >
                                                                    <span className="font-medium md:hidden">
                                                                        Catatan
                                                                        dosen:{' '}
                                                                    </span>
                                                                    {
                                                                        meeting.lecturerNote
                                                                    }
                                                                </p>
                                                            ) : (
                                                                <span className="text-muted-foreground">
                                                                    —
                                                                </span>
                                                            )}
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                </DataTableContainer>
                            ) : (
                                <DataTableEmptyState
                                    icon={Inbox}
                                    title="Belum ada jadwal mendatang"
                                    description="Ajukan jadwal baru untuk mulai sesi bimbingan berikutnya."
                                />
                            )}
                        </DataSection>

                        {/* History */}
                        <DataSection
                            title="Riwayat Bimbingan"
                            summary={`${filteredHistory.length} riwayat selesai atau ditutup`}
                        >
                            <StickyFilterBar>
                                <DataToolbar
                                    search={historySearch}
                                    onSearchChange={setHistorySearch}
                                    placeholder="Cari topik, dosen, atau lokasi..."
                                />
                                <FilterPills
                                    label="Filter status riwayat"
                                    value={historyStatusFilter}
                                    onValueChange={setHistoryStatusFilter}
                                    options={historyFilterOptions}
                                    itemClassName="max-md:h-11 max-md:px-4"
                                />
                            </StickyFilterBar>

                            {historyPagination.totalItems > 0 ? (
                                <DataTableContainer
                                    pagination={
                                        <DataTablePagination
                                            currentPage={historyPagination.page}
                                            totalPages={
                                                historyPagination.totalPages
                                            }
                                            totalItems={
                                                historyPagination.totalItems
                                            }
                                            pageSize={
                                                historyPagination.pageSize
                                            }
                                            onPageChange={
                                                historyPagination.setPage
                                            }
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
                                            'md:min-w-[760px]',
                                        )}
                                    >
                                        <thead className={stackedTable.thead}>
                                            <tr className="border-b bg-muted/40">
                                                <th className={dataTh}>
                                                    Topik
                                                </th>
                                                <th className={dataTh}>
                                                    Dosen
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
                                            </tr>
                                        </thead>
                                        <tbody
                                            className={cn(
                                                stackedTable.tbody,
                                                'md:divide-y',
                                            )}
                                        >
                                            {historyPagination.paginated.map(
                                                (row) => (
                                                    <tr
                                                        key={row.id}
                                                        className={cn(
                                                            stackedTable.tr,
                                                            'align-top',
                                                        )}
                                                    >
                                                        <td
                                                            className={
                                                                stackedTable.tdPlain
                                                            }
                                                        >
                                                            <p
                                                                title={
                                                                    row.topic
                                                                }
                                                                className="min-w-40 text-sm leading-snug font-medium text-pretty max-md:line-clamp-2 max-md:min-w-0"
                                                            >
                                                                {row.topic}
                                                            </p>
                                                            {row.createdByLecturer && (
                                                                <div className="mt-1">
                                                                    <CreatedByLecturerBadge />
                                                                </div>
                                                            )}
                                                            {row.lecturerNote && (
                                                                <p className="mt-1 line-clamp-2 text-xs text-pretty text-muted-foreground">
                                                                    {
                                                                        row.lecturerNote
                                                                    }
                                                                </p>
                                                            )}
                                                        </td>
                                                        <td
                                                            className={
                                                                stackedTable.td
                                                            }
                                                        >
                                                            <p className="text-sm max-md:inline">
                                                                {row.lecturer}
                                                            </p>
                                                            <div className="mt-1 max-md:mt-0 max-md:ml-1 max-md:inline-block">
                                                                <RelationTypeBadge
                                                                    relationType={
                                                                        row.relationType
                                                                    }
                                                                />
                                                            </div>
                                                        </td>
                                                        <td
                                                            className={cn(
                                                                stackedTable.td,
                                                                'text-sm md:whitespace-nowrap',
                                                            )}
                                                        >
                                                            {formatDate(
                                                                row.scheduledAt,
                                                            )}
                                                        </td>
                                                        <td
                                                            className={cn(
                                                                stackedTable.td,
                                                                'text-sm',
                                                            )}
                                                        >
                                                            <span className="inline-flex items-start gap-1.5">
                                                                <MapPin
                                                                    aria-hidden="true"
                                                                    className="mt-0.5 size-3.5 shrink-0 text-muted-foreground"
                                                                />
                                                                {row.location}
                                                            </span>
                                                        </td>
                                                        <td
                                                            className={
                                                                stackedTable.tdAside
                                                            }
                                                        >
                                                            <ScheduleStatusBadge
                                                                status={
                                                                    row.status
                                                                }
                                                            />
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                </DataTableContainer>
                            ) : (
                                <DataTableEmptyState
                                    icon={Calendar}
                                    title={
                                        hasHistoryFilter
                                            ? 'Tidak ada riwayat yang cocok'
                                            : 'Belum ada riwayat bimbingan'
                                    }
                                    description={
                                        hasHistoryFilter
                                            ? 'Coba ubah kata kunci atau filter yang dipilih.'
                                            : 'Riwayat akan muncul setelah sesi pertama selesai.'
                                    }
                                />
                            )}
                        </DataSection>
                    </>
                )}
            </div>

            <ScheduleDetailModal
                open={isDetailModalOpen}
                onOpenChange={setIsDetailModalOpen}
                schedule={selectedEvent}
                currentUserRole="mahasiswa"
            />
        </AppLayout>
    );
}
