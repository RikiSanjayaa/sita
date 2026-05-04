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

import {
    BimbinganCalendar,
    type BimbinganEvent,
} from '@/components/bimbingan-calendar';
import { ScheduleDetailModal } from '@/components/schedule-detail-modal';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DataTableContainer,
    DataTableEmptyState,
    DataTablePagination,
    DataTableToolbar,
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
    flashMessage?: string | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Jadwal Bimbingan', href: jadwalBimbingan().url },
];

function StatusBadge({ status }: { status: ScheduleStatus }) {
    const map: Record<
        ScheduleStatus,
        { label: string; className: string; icon: React.ReactNode }
    > = {
        completed: {
            label: 'Selesai',
            className: 'bg-blue-600/10 text-blue-600',
            icon: <CheckCircle2 className="size-3" />,
        },
        cancelled: {
            label: 'Dibatalkan',
            className: 'bg-muted text-muted-foreground',
            icon: <XCircle className="size-3" />,
        },
        approved: {
            label: 'Terjadwal',
            className: 'bg-emerald-600/10 text-emerald-700',
            icon: <CheckCircle2 className="size-3" />,
        },
        rescheduled: {
            label: 'Terjadwal',
            className: 'bg-emerald-600/10 text-emerald-700',
            icon: <CheckCircle2 className="size-3" />,
        },
        pending: {
            label: 'Menunggu Konfirmasi',
            className: 'bg-amber-600/10 text-amber-700',
            icon: <Clock className="size-3" />,
        },
        rejected: {
            label: 'Ditolak',
            className: 'bg-destructive/10 text-destructive',
            icon: <XCircle className="size-3" />,
        },
    };

    const { label, className, icon } = map[status] ?? map.cancelled;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap',
                className,
            )}
        >
            {icon}
            {label}
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
                Penguji
            </Badge>
        );
    }
    return (
        <Badge
            variant="outline"
            className="gap-1 rounded-full border-cyan-500/50 bg-cyan-500/10 text-cyan-600 hover:bg-cyan-500/20 dark:text-cyan-400"
        >
            Pembimbing
        </Badge>
    );
}

export default function JadwalBimbinganPage() {
    const page = usePage<SharedData & JadwalPageProps>();
    const query = page.url.split('?')[1] ?? '';
    const defaultLecturerUserId = page.props.advisors[0]?.lecturerUserId;
    const [isAjukanOpen, setIsAjukanOpen] = useState(
        new URLSearchParams(query).get('open') === 'ajukan',
    );

    const PAGE_SIZE = 15;

    // Upcoming pagination
    const upcomingPagination = usePagination(
        page.props.upcomingMeetings,
        PAGE_SIZE,
    );

    // History search + status filter + pagination
    const [historySearch, setHistorySearch] = useState('');
    const [historyStatusFilter, setHistoryStatusFilter] = useState<
        'semua' | ScheduleStatus
    >('semua');
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
    const historyPagination = usePagination(filteredHistory, PAGE_SIZE, [
        historySearch,
        historyStatusFilter,
    ]);

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
            window.Echo.leaveChannel(`private-${channelName}`);
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

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Jadwal Bimbingan"
            subtitle="Kelola jadwal bimbingan skripsi dengan dosen pembimbing"
        >
            <Head title="Jadwal Bimbingan" />

            {/* Request Dialog */}
            <Dialog open={isAjukanOpen} onOpenChange={setIsAjukanOpen}>
                <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-xl">
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
                                value={form.data.topic}
                                onChange={(e) =>
                                    form.setData('topic', e.target.value)
                                }
                                required
                                placeholder="Contoh: Review Bab 3 - Metodologi Penelitian"
                            />
                            {form.errors.topic && (
                                <p className="text-xs text-destructive">
                                    {form.errors.topic}
                                </p>
                            )}
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
                                <SelectTrigger id="lecturer_user_id">
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
                            {form.errors.lecturer_user_id && (
                                <p className="text-xs text-destructive">
                                    {form.errors.lecturer_user_id}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="requested_for">
                                Tanggal &amp; Waktu Preferensi
                            </Label>
                            <Input
                                id="requested_for"
                                type="datetime-local"
                                value={form.data.requested_for}
                                onChange={(e) =>
                                    form.setData(
                                        'requested_for',
                                        e.target.value,
                                    )
                                }
                                required
                            />
                            {form.errors.requested_for && (
                                <p className="text-xs text-destructive">
                                    {form.errors.requested_for}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="meeting_type">Tipe Bimbingan</Label>
                            <Select
                                value={form.data.meeting_type}
                                onValueChange={(v) =>
                                    form.setData('meeting_type', v)
                                }
                            >
                                <SelectTrigger id="meeting_type">
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
                        </div>

                        <div className="grid gap-3 rounded-lg border p-4">
                            <div className="flex items-center gap-2">
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
                                    className="size-4 rounded border-input"
                                />
                                <Label
                                    htmlFor="is_recurring"
                                    className="flex items-center gap-2 font-medium"
                                >
                                    <Repeat className="size-4" />
                                    Jadwalkan Berulang
                                </Label>
                            </div>

                            {form.data.is_recurring && (
                                <div className="grid gap-3 pl-6">
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
                                            <SelectTrigger id="recurring_pattern">
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
                                            <SelectTrigger id="recurring_count">
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
                                    <p className="text-xs text-muted-foreground">
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

                        <div className="grid gap-2">
                            <Label htmlFor="student_note">
                                Catatan Tambahan (Opsional)
                            </Label>
                            <Textarea
                                id="student_note"
                                value={form.data.student_note}
                                onChange={(e) =>
                                    form.setData('student_note', e.target.value)
                                }
                                placeholder="Jelaskan hal-hal yang ingin didiskusikan..."
                            />
                        </div>

                        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full sm:w-auto"
                                onClick={() => setIsAjukanOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                className="bg-primary text-primary-foreground hover:bg-primary/90"
                                disabled={form.processing}
                            >
                                <Send className="size-4" />
                                Kirim Permintaan
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-8 px-4 py-6 md:px-6">
                {/* Page header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            Jadwal Bimbingan
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Kelola jadwal bimbingan skripsi dengan dosen
                            pembimbing
                        </p>
                    </div>
                    {page.props.hasDosbing && (
                        <Button
                            type="button"
                            className="h-10 w-full gap-2 sm:w-auto"
                            onClick={() => setIsAjukanOpen(true)}
                        >
                            <Plus className="size-4" />
                            Ajukan Bimbingan
                        </Button>
                    )}
                </div>

                {/* No dosbing state */}
                {!page.props.auth.activeRole || !page.props.hasDosbing ? (
                    <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed py-20 text-center text-muted-foreground">
                        <Users className="mb-4 size-12 opacity-20" />
                        <h2 className="mb-2 text-lg font-semibold text-foreground">
                            Fitur Bimbingan Belum Aktif
                        </h2>
                        <p className="max-w-md text-sm">
                            Anda belum memiliki Dosen Pembimbing yang
                            ditugaskan. Fitur jadwal bimbingan akan otomatis
                            aktif setelah admin menetapkan dosen pembimbing
                            untuk Anda.
                        </p>
                    </div>
                ) : (
                    <>
                        {page.props.flashMessage && (
                            <Alert>
                                <AlertTitle>Berhasil</AlertTitle>
                                <AlertDescription>
                                    {page.props.flashMessage}
                                </AlertDescription>
                            </Alert>
                        )}

                        {/* Calendar */}
                        <section>
                            <BimbinganCalendar
                                events={calendarEvents}
                                onEventClick={handleEventClick}
                                defaultView="list"
                            />
                        </section>

                        {/* Upcoming — full data table with horizontal scroll + pagination */}
                        <section>
                            <div className="mb-1 flex items-center justify-between">
                                <div>
                                    <h2 className="text-base font-semibold">
                                        Bimbingan Akan Datang
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Jadwal yang diajukan atau telah
                                        dikonfirmasi
                                    </p>
                                </div>
                                <span className="text-xs text-muted-foreground">
                                    {page.props.upcomingMeetings.length} jadwal
                                </span>
                            </div>

                            {page.props.upcomingMeetings.length > 0 ? (
                                <DataTableContainer>
                                    <table className="w-full min-w-[700px] text-sm">
                                        <thead>
                                            <tr className="border-b bg-muted/30">
                                                <th className="px-5 py-2.5 text-left text-xs font-medium text-muted-foreground">
                                                    Topik
                                                </th>
                                                <th className="px-5 py-2.5 text-left text-xs font-medium text-muted-foreground">
                                                    Dosen
                                                </th>
                                                <th className="px-5 py-2.5 text-left text-xs font-medium whitespace-nowrap text-muted-foreground">
                                                    Waktu Preferensi
                                                </th>
                                                <th className="px-5 py-2.5 text-left text-xs font-medium whitespace-nowrap text-muted-foreground">
                                                    Waktu Terkonfirmasi
                                                </th>
                                                <th className="px-5 py-2.5 text-left text-xs font-medium text-muted-foreground">
                                                    Lokasi
                                                </th>
                                                <th className="px-5 py-2.5 text-left text-xs font-medium text-muted-foreground">
                                                    Status
                                                </th>
                                                <th className="px-5 py-2.5 text-left text-xs font-medium text-muted-foreground">
                                                    Catatan Dosen
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {upcomingPagination.paginated.map(
                                                (meeting) => (
                                                    <tr
                                                        key={meeting.id}
                                                        className="transition-colors hover:bg-muted/20"
                                                    >
                                                        <td className="px-5 py-3.5 align-top">
                                                            <p className="max-w-[200px] min-w-[140px] text-xs leading-snug font-medium">
                                                                {meeting.topic}
                                                            </p>
                                                        </td>
                                                        <td className="px-5 py-3.5 align-top">
                                                            <p className="text-xs whitespace-nowrap text-muted-foreground">
                                                                {
                                                                    meeting.lecturer
                                                                }
                                                            </p>
                                                            <RelationTypeBadge
                                                                relationType={
                                                                    meeting.relationType
                                                                }
                                                            />
                                                        </td>
                                                        <td className="px-5 py-3.5 align-top text-xs whitespace-nowrap text-muted-foreground">
                                                            {formatDate(
                                                                meeting.requestedAt,
                                                            )}
                                                        </td>
                                                        <td className="px-5 py-3.5 align-top">
                                                            {meeting.scheduledAt ? (
                                                                <span className="text-xs whitespace-nowrap text-muted-foreground">
                                                                    {formatDate(
                                                                        meeting.scheduledAt,
                                                                    )}
                                                                </span>
                                                            ) : (
                                                                <span className="inline-flex items-center gap-1 text-xs whitespace-nowrap text-amber-600 dark:text-amber-400">
                                                                    <Clock className="size-3 shrink-0" />
                                                                    Menunggu
                                                                    konfirmasi
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td className="px-5 py-3.5 align-top">
                                                            <span className="flex items-center gap-1.5 text-xs whitespace-nowrap text-muted-foreground">
                                                                <MapPin className="size-3 shrink-0" />
                                                                {
                                                                    meeting.location
                                                                }
                                                            </span>
                                                        </td>
                                                        <td className="px-5 py-3.5 align-top">
                                                            <StatusBadge
                                                                status={
                                                                    meeting.status
                                                                }
                                                            />
                                                        </td>
                                                        <td className="px-5 py-3.5 align-top">
                                                            {meeting.lecturerNote ? (
                                                                <p className="max-w-[240px] min-w-[160px] text-xs leading-relaxed text-muted-foreground">
                                                                    {
                                                                        meeting.lecturerNote
                                                                    }
                                                                </p>
                                                            ) : (
                                                                <span className="text-xs text-muted-foreground/40 italic">
                                                                    —
                                                                </span>
                                                            )}
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                    <DataTablePagination
                                        currentPage={upcomingPagination.page}
                                        totalPages={
                                            upcomingPagination.totalPages
                                        }
                                        totalItems={
                                            upcomingPagination.totalItems
                                        }
                                        pageSize={PAGE_SIZE}
                                        onPageChange={
                                            upcomingPagination.setPage
                                        }
                                        itemLabel="jadwal"
                                    />
                                </DataTableContainer>
                            ) : (
                                <DataTableEmptyState
                                    icon={Inbox}
                                    title="Belum ada jadwal mendatang"
                                    description="Ajukan jadwal baru untuk mulai sesi bimbingan berikutnya."
                                />
                            )}
                        </section>

                        {/* History — search + table + pagination */}
                        <section>
                            <div className="mb-3">
                                <h2 className="text-base font-semibold">
                                    Riwayat Bimbingan
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Sesi bimbingan yang sudah selesai atau
                                    ditutup
                                </p>
                            </div>

                            <DataTableToolbar
                                search={historySearch}
                                onSearchChange={setHistorySearch}
                                searchPlaceholder="Cari topik, dosen, atau lokasi..."
                                filterGroups={[
                                    {
                                        value: historyStatusFilter,
                                        onChange: (v) =>
                                            setHistoryStatusFilter(
                                                v as 'semua' | ScheduleStatus,
                                            ),
                                        tabs: [
                                            {
                                                label: 'Semua',
                                                value: 'semua',
                                            },
                                            {
                                                label: 'Selesai',
                                                value: 'completed',
                                            },
                                            {
                                                label: 'Ditolak',
                                                value: 'rejected',
                                            },
                                            {
                                                label: 'Dibatalkan',
                                                value: 'cancelled',
                                            },
                                        ],
                                    },
                                ]}
                                className="mb-3"
                            />

                            {historyPagination.totalItems > 0 ? (
                                <DataTableContainer>
                                    <table className="w-full min-w-[600px] text-left text-sm">
                                        <thead className="border-b bg-muted/30">
                                            <tr>
                                                <th className="px-5 py-3 text-xs font-medium text-muted-foreground">
                                                    Topik
                                                </th>
                                                <th className="px-5 py-3 text-xs font-medium text-muted-foreground">
                                                    Dosen
                                                </th>
                                                <th className="px-5 py-3 text-xs font-medium whitespace-nowrap text-muted-foreground">
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
                                            {historyPagination.paginated.map(
                                                (row) => (
                                                    <tr
                                                        key={row.id}
                                                        className="transition-colors hover:bg-muted/20"
                                                    >
                                                        <td className="px-5 py-3.5 align-middle">
                                                            <p className="max-w-[180px] truncate text-sm font-medium">
                                                                {row.topic}
                                                            </p>
                                                            {row.lecturerNote && (
                                                                <p className="mt-0.5 line-clamp-1 text-xs text-muted-foreground italic opacity-70">
                                                                    {
                                                                        row.lecturerNote
                                                                    }
                                                                </p>
                                                            )}
                                                        </td>
                                                        <td className="px-5 py-3.5 align-middle">
                                                            <p className="text-xs whitespace-nowrap text-muted-foreground">
                                                                {row.lecturer}
                                                            </p>
                                                            <RelationTypeBadge
                                                                relationType={
                                                                    row.relationType
                                                                }
                                                            />
                                                        </td>
                                                        <td className="px-5 py-3.5 align-middle text-xs whitespace-nowrap text-muted-foreground">
                                                            {formatDate(
                                                                row.scheduledAt,
                                                            )}
                                                        </td>
                                                        <td className="px-5 py-3.5 align-middle">
                                                            <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                                                <MapPin className="size-3 shrink-0" />
                                                                {row.location}
                                                            </span>
                                                        </td>
                                                        <td className="px-5 py-3.5 align-middle">
                                                            <StatusBadge
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
                                    <DataTablePagination
                                        currentPage={historyPagination.page}
                                        totalPages={
                                            historyPagination.totalPages
                                        }
                                        totalItems={
                                            historyPagination.totalItems
                                        }
                                        pageSize={PAGE_SIZE}
                                        onPageChange={historyPagination.setPage}
                                        itemLabel="riwayat"
                                    />
                                </DataTableContainer>
                            ) : (
                                <DataTableEmptyState
                                    icon={Calendar}
                                    title={
                                        historySearch ||
                                        historyStatusFilter !== 'semua'
                                            ? 'Tidak ada riwayat yang cocok'
                                            : 'Belum ada riwayat bimbingan'
                                    }
                                    description={
                                        historySearch ||
                                        historyStatusFilter !== 'semua'
                                            ? 'Coba ubah kata kunci atau filter yang dipilih.'
                                            : 'Riwayat akan muncul setelah sesi pertama selesai.'
                                    }
                                />
                            )}
                        </section>
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
