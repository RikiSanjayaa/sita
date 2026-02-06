import { Head } from '@inertiajs/react';
import { Download, Paperclip, Search, Send, Users } from 'lucide-react';
import { useMemo, useRef, useState, type ChangeEvent } from 'react';

import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { dashboard, pesan } from '@/routes';
import { type BreadcrumbItem } from '@/types';

type ChatRole = 'Group Bimbingan' | 'Dosen Pembimbing' | 'Penguji';
type Msg = {
    id: string;
    from: 'me' | 'them' | 'system';
    text?: string;
    time: string;
    file?: string;
};
type Thread = {
    id: string;
    name: string;
    role: ChatRole;
    unread: number;
    preview: string;
    time: string;
    members?: string[];
    messages: Msg[];
};
type GroupDocEvent = {
    id: string;
    fileName: string;
    category: string;
    uploadedAt: string;
    version: string;
};

const GROUP_DOC_EVENTS_KEY = 'sita:group-doc-events:v1';
const GROUP_DOC_LAST_SEEN_KEY = 'sita:group-doc-events:last-seen';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Pesan', href: pesan().url },
];

function initials(name: string) {
    return name
        .split(' ')
        .map((x) => x[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

function readEvents(): GroupDocEvent[] {
    if (typeof window === 'undefined') return [];
    const raw = window.localStorage.getItem(GROUP_DOC_EVENTS_KEY);
    if (!raw) return [];
    try {
        return JSON.parse(raw) as GroupDocEvent[];
    } catch {
        return [];
    }
}

function latestUnseenEvent() {
    if (typeof window === 'undefined') return null;
    const events = readEvents();
    if (!events.length) return null;
    const lastSeen = window.localStorage.getItem(GROUP_DOC_LAST_SEEN_KEY);
    const latest = events[events.length - 1];
    return latest.id === lastSeen ? null : latest;
}

function mapSystemMessages(events: GroupDocEvent[]): Msg[] {
    return events.map((e) => ({
        id: `sys-${e.id}`,
        from: 'system',
        text: `Mahasiswa upload ${e.category} (${e.version})`,
        file: e.fileName,
        time: new Date(e.uploadedAt).toLocaleString('id-ID', {
            day: '2-digit',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
        }),
    }));
}

function seedThreads(): Thread[] {
    const sys = mapSystemMessages(readEvents());
    return [
        {
            id: 'group',
            name: 'Group Chat Bimbingan',
            role: 'Group Bimbingan',
            unread: sys.length ? 1 : 0,
            preview: sys.at(-1)?.text ?? 'Diskusi bersama mahasiswa dan dua dosen pembimbing.',
            time: sys.at(-1)?.time ?? 'Baru saja',
            members: ['Mahasiswa', 'Pembimbing 1', 'Pembimbing 2'],
            messages: [{ id: 'g1', from: 'them', text: 'Gunakan grup ini untuk koordinasi dokumen dan revisi.', time: '09:10' }, ...sys],
        },
        {
            id: 'p1',
            name: 'Dr. Budi Santoso, M.Kom.',
            role: 'Dosen Pembimbing',
            unread: 1,
            preview: 'Silakan cek revisi terbaru.',
            time: '2 jam lalu',
            messages: [{ id: 'p1m1', from: 'them', text: 'Silakan cek revisi terbaru.', time: '10:30' }],
        },
        {
            id: 'p2',
            name: 'Dr. Siti Aminah, M.T.',
            role: 'Penguji',
            unread: 0,
            preview: 'Baik, kita lanjut sesuai rencana.',
            time: '1 hari lalu',
            messages: [{ id: 'p2m1', from: 'them', text: 'Baik, kita lanjut sesuai rencana.', time: '08:14' }],
        },
    ];
}

export default function Pesan() {
    const [threads, setThreads] = useState<Thread[]>(seedThreads);
    const [activeId, setActiveId] = useState('group');
    const [q, setQ] = useState('');
    const [draft, setDraft] = useState('');
    const [file, setFile] = useState<string | null>(null);
    const [notice, setNotice] = useState<GroupDocEvent | null>(latestUnseenEvent);
    const fileRef = useRef<HTMLInputElement | null>(null);

    const active = useMemo(() => threads.find((t) => t.id === activeId) ?? null, [threads, activeId]);
    const filtered = useMemo(() => threads.filter((t) => t.name.toLowerCase().includes(q.toLowerCase())), [threads, q]);

    function openThread(id: string) {
        setActiveId(id);
        setThreads((c) => c.map((t) => (t.id === id ? { ...t, unread: 0 } : t)));
    }

    function send() {
        if (!active || (!draft.trim() && !file)) return;
        setThreads((c) =>
            c.map((t) =>
                t.id === active.id
                    ? {
                          ...t,
                          preview: draft.trim() || file || t.preview,
                          time: 'Baru saja',
                          messages: [...t.messages, { id: `me-${Date.now()}`, from: 'me', text: draft.trim() || undefined, file: file ?? undefined, time: 'Baru saja' }],
                      }
                    : t,
            ),
        );
        setDraft('');
        setFile(null);
        if (fileRef.current) fileRef.current.value = '';
    }

    function closeNotice() {
        if (notice && typeof window !== 'undefined') window.localStorage.setItem(GROUP_DOC_LAST_SEEN_KEY, notice.id);
        setNotice(null);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs} title="Pesan" subtitle="Diskusi pembimbing, penguji, dan mahasiswa">
            <Head title="Pesan" />

            <Dialog open={Boolean(notice)} onOpenChange={closeNotice}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Dokumen Baru di Group Chat</DialogTitle>
                        <DialogDescription>Upload dokumen terbaru sudah masuk ke grup bimbingan.</DialogDescription>
                    </DialogHeader>
                    {notice && <div className="rounded-lg border bg-muted/30 p-3 text-sm">{notice.fileName}</div>}
                    <div className="flex justify-end gap-2">
                        <Button variant="outline" onClick={closeNotice}>Tutup</Button>
                        <Button className="bg-slate-900 text-white hover:bg-slate-900/90" onClick={() => { setActiveId('group'); closeNotice(); }}>Buka Grup</Button>
                    </div>
                </DialogContent>
            </Dialog>

            <div className="mx-auto grid w-full max-w-7xl flex-1 gap-6 px-4 py-6 lg:grid-cols-[360px_1fr] md:px-6">
                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Percakapan</CardTitle>
                        <CardDescription>Group chat + direct chat dosen</CardDescription>
                        <div className="relative">
                            <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                            <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Cari..." className="pl-9" />
                        </div>
                    </CardHeader>
                    <Separator />
                    <CardContent className="grid gap-2 pt-4">
                        {filtered.map((t) => (
                            <button key={t.id} type="button" onClick={() => openThread(t.id)} className={cn('flex items-start gap-3 rounded-lg border p-3 text-left hover:bg-muted/50', t.id === activeId && 'border-slate-900/20 bg-muted/60')}>
                                <Avatar className="size-9"><AvatarFallback>{t.id === 'group' ? 'GC' : initials(t.name)}</AvatarFallback></Avatar>
                                <div className="min-w-0 flex-1">
                                    <div className="flex justify-between gap-2">
                                        <div className="truncate text-sm font-semibold">{t.name}</div>
                                        <div className="text-xs text-muted-foreground">{t.time}</div>
                                    </div>
                                    <div className="mt-1 flex items-center gap-2">
                                        <Badge variant="outline" className="bg-background">{t.role}</Badge>
                                        {t.unread > 0 && <Badge className="bg-slate-900 text-white">{t.unread}</Badge>}
                                    </div>
                                    <div className="mt-2 line-clamp-2 text-xs text-muted-foreground">{t.preview}</div>
                                </div>
                            </button>
                        ))}
                    </CardContent>
                </Card>

                <Card className="flex min-h-[620px] flex-col">
                    <CardHeader>
                        {active && (
                            <div>
                                <div className="flex items-center gap-2">
                                    <CardTitle>{active.name}</CardTitle>
                                    <Badge variant="secondary">{active.role}</Badge>
                                </div>
                                {active.members && <div className="mt-1 flex items-center gap-1 text-xs text-muted-foreground"><Users className="size-3.5" />{active.members.join(' · ')}</div>}
                            </div>
                        )}
                    </CardHeader>
                    <Separator />
                    <CardContent className="flex-1 overflow-auto pt-4">
                        <div className="grid gap-3">
                            {active?.messages.map((m) =>
                                m.from === 'system' ? (
                                    <div key={m.id} className="rounded-lg border border-blue-100 bg-blue-50 p-3">
                                        <div className="text-sm text-blue-900">{m.text}</div>
                                        {m.file && <div className="mt-2 rounded border bg-white p-2 text-sm">{m.file}</div>}
                                        <div className="mt-2 flex justify-end"><Button size="sm" variant="outline" className="h-8 gap-2"><Download className="size-3.5" />Unduh</Button></div>
                                    </div>
                                ) : (
                                    <div key={m.id} className={cn('flex', m.from === 'me' && 'justify-end')}>
                                        <div className={cn('max-w-[78%] rounded-2xl border px-3 py-2 text-sm', m.from === 'me' ? 'bg-slate-900 text-white' : 'bg-background')}>
                                            {m.file && <div className={cn('mb-2 rounded border p-2 text-xs', m.from === 'me' ? 'border-white/20 bg-white/10' : 'bg-muted/30')}>{m.file}</div>}
                                            {m.text && <div>{m.text}</div>}
                                            <div className={cn('mt-1 text-[11px]', m.from === 'me' ? 'text-white/70' : 'text-muted-foreground')}>{m.time}</div>
                                        </div>
                                    </div>
                                ),
                            )}
                        </div>
                    </CardContent>
                    <Separator />
                    <CardFooter className="flex-col items-stretch gap-3">
                        <input ref={fileRef} type="file" className="hidden" onChange={(e: ChangeEvent<HTMLInputElement>) => setFile(e.target.files?.[0]?.name ?? null)} />
                        <div className="flex items-center gap-2">
                            <Button type="button" variant="outline" size="icon" onClick={() => fileRef.current?.click()}><Paperclip className="size-4" /></Button>
                            <Input value={draft} onChange={(e) => setDraft(e.target.value)} placeholder="Tulis pesan..." onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), send())} />
                            <Button type="button" onClick={send} className="bg-slate-900 text-white hover:bg-slate-900/90"><Send className="size-4" /></Button>
                        </div>
                        {file && <div className="text-xs text-muted-foreground">Lampiran: {file}</div>}
                    </CardFooter>
                </Card>
            </div>
        </AppLayout>
    );
}
