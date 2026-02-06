import { Head, Link } from '@inertiajs/react';
import {
    CalendarClock,
    ChevronDown,
    FileDown,
    FileText,
    ListChecks,
    MessageSquareText,
    Search,
    UploadCloud,
} from 'lucide-react';
import { useMemo, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import {
    dashboard,
    panduan,
    pesan,
    uploadDokumen,
} from '@/routes';
import { create as jadwalBimbinganCreate } from '@/routes/jadwal-bimbingan';
import { type BreadcrumbItem } from '@/types';

type GuidanceCard = {
    id: 'alur' | 'jadwal' | 'upload' | 'komunikasi';
    title: string;
    description: string;
    badge: string;
    icon: typeof ListChecks;
    bullets: string[];
    keywords: string[];
};

type FaqItem = {
    id: string;
    question: string;
    answer: string;
    tags: string[];
};

type TemplateDoc = {
    id: string;
    title: string;
    description: string;
    format: string;
    badge?: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Panduan',
        href: panduan().url,
    },
];

const guidanceCards: GuidanceCard[] = [
    {
        id: 'alur',
        title: 'Alur Pengajuan',
        description: 'Dari ide sampai disetujui, tanpa bolak-balik.',
        badge: 'Langkah awal',
        icon: ListChecks,
        bullets: [
            'Siapkan judul, ringkasan 3-5 paragraf, dan rencana metode singkat.',
            'Ajukan judul terlebih dulu, lalu tunggu status (Disetujui/Revisi).',
            'Jika revisi, fokus ke poin catatan: perbaiki dan ajukan versi berikutnya.',
            'Jaga penamaan versi: v1, v2, v3 agar riwayat rapi.',
        ],
        keywords: ['judul', 'pengajuan', 'revisi', 'status', 'versi'],
    },
    {
        id: 'jadwal',
        title: 'Jadwal & Bimbingan',
        description: 'Cara minta bimbingan yang cepat disetujui.',
        badge: 'Biar cepat',
        icon: CalendarClock,
        bullets: [
            'Tawarkan 2-3 opsi waktu dan cantumkan topik yang spesifik.',
            'Lampirkan progres terakhir (tautan atau dokumen) sebelum mengajukan.',
            'Tulis tujuan pertemuan: butuh keputusan, review, atau diskusi.',
            'Catat hasil bimbingan, lalu tindak lanjuti maksimal 1-3 hari.',
        ],
        keywords: ['jadwal', 'bimbingan', 'pertemuan', 'topik', 'waktu'],
    },
    {
        id: 'upload',
        title: 'Upload & Revisi',
        description: 'Unggah dokumen dengan rapi dan mudah ditinjau.',
        badge: 'Dokumen',
        icon: UploadCloud,
        bullets: [
            'Pastikan format sesuai (mis. PDF) dan ukuran file wajar.',
            'Gunakan nama file konsisten: Bab_1_Nama_v2.pdf.',
            'Tulis ringkasan perubahan singkat pada revisi (3-5 poin).',
            'Unggah hanya yang relevan; jangan campur dokumen mentah di final.',
        ],
        keywords: ['upload', 'unggah', 'dokumen', 'pdf', 'nama file'],
    },
    {
        id: 'komunikasi',
        title: 'Komunikasi',
        description: 'Sopan, jelas, dan enak ditindaklanjuti pembimbing.',
        badge: 'Etika',
        icon: MessageSquareText,
        bullets: [
            'Mulai dengan konteks 1 kalimat: tahap apa dan tujuan pesan.',
            'Ajukan pertanyaan yang bisa dijawab ya/tidak atau pilihan A/B.',
            'Sertakan tautan/dokumen yang dirujuk, jangan hanya "sudah saya kirim".',
            'Akhiri dengan permintaan tindakan dan tenggat yang realistis.',
        ],
        keywords: ['pesan', 'komunikasi', 'pembimbing', 'tenggat', 'konteks'],
    },
];

const faqItems: FaqItem[] = [
    {
        id: 'faq-1',
        question: 'Saya harus mulai dari mana?',
        answer: 'Mulai dari Alur Pengajuan. Siapkan judul dan ringkasan singkat, lalu ajukan judul. Setelah status jelas, baru susun dokumen bab per bab dan ajukan bimbingan berkala.',
        tags: ['alur', 'judul'],
    },
    {
        id: 'faq-2',
        question: 'Berapa sering sebaiknya bimbingan?',
        answer: 'Idealnya 1-2 minggu sekali, atau setiap ada perubahan besar yang butuh keputusan. Yang penting: selalu bawa progres yang bisa ditinjau agar pertemuan efektif.',
        tags: ['bimbingan', 'jadwal'],
    },
    {
        id: 'faq-3',
        question: 'Apa yang perlu ditulis saat mengunggah revisi?',
        answer: 'Tulis ringkasan perubahan 3-5 poin: bagian apa yang diubah, alasan singkat, dan apa yang ingin ditinjau. Ini membuat pembimbing cepat menemukan perbaikan.',
        tags: ['revisi', 'upload'],
    },
    {
        id: 'faq-4',
        question: 'Bagaimana kalau pembimbing lama merespons?',
        answer: 'Kirim follow-up singkat setelah 2-3 hari kerja dengan konteks dan tautan dokumen. Jika tetap belum ada respons, ajukan jadwal bimbingan dengan opsi waktu yang jelas.',
        tags: ['komunikasi', 'follow-up'],
    },
    {
        id: 'faq-5',
        question: 'Nama file yang rapi seperti apa?',
        answer: 'Gunakan pola konsisten: Tahap_Bab_Nama_vX.ext, misalnya Bab_2_MuhammadAkbar_v3.pdf. Hindari spasi ganda dan gunakan versi agar riwayat tidak membingungkan.',
        tags: ['dokumen', 'versi'],
    },
];

const templateDocs: TemplateDoc[] = [
    {
        id: 'tpl-1',
        title: 'Template Proposal',
        description: 'Kerangka proposal sesuai format kampus.',
        format: 'DOCX',
        badge: 'Wajib',
    },
    {
        id: 'tpl-2',
        title: 'Template Bab 1-3',
        description: 'Struktur penulisan awal (Pendahuluan sampai Metode).',
        format: 'DOCX',
    },
    {
        id: 'tpl-3',
        title: 'Template Logbook Bimbingan',
        description: 'Catatan pertemuan dan tindak lanjut.',
        format: 'XLSX',
    },
    {
        id: 'tpl-4',
        title: 'Template Slide Seminar',
        description: 'Slide ringkas untuk presentasi proposal/sidang.',
        format: 'PPTX',
    },
];

function normalize(text: string) {
    return text.toLowerCase();
}

function FaqAccordionItem({
    item,
    defaultOpen,
}: {
    item: FaqItem;
    defaultOpen?: boolean;
}) {
    return (
        <details
            className="group rounded-xl border bg-background open:bg-muted/30"
            open={defaultOpen}
        >
            <summary className="flex cursor-pointer list-none items-start justify-between gap-4 px-4 py-3 outline-none focus-visible:ring-2 focus-visible:ring-ring/50">
                <div className="min-w-0">
                    <div className="text-sm font-semibold">{item.question}</div>
                    <div className="mt-1 flex flex-wrap gap-1">
                        {item.tags.map((t) => (
                            <Badge
                                key={t}
                                variant="secondary"
                                className="rounded-full"
                            >
                                {t}
                            </Badge>
                        ))}
                    </div>
                </div>
                <ChevronDown className="mt-0.5 size-4 shrink-0 text-muted-foreground transition-transform group-open:rotate-180" />
            </summary>
            <div className="px-4 pb-4 text-sm text-muted-foreground">
                {item.answer}
            </div>
        </details>
    );
}

export default function Panduan() {
    const [query, setQuery] = useState('');

    const q = query.trim();

    const filteredGuidance = useMemo(() => {
        if (!q) return guidanceCards;
        const nq = normalize(q);

        return guidanceCards.filter((card) => {
            const hay = normalize(
                [
                    card.title,
                    card.description,
                    card.badge,
                    card.bullets.join(' '),
                    card.keywords.join(' '),
                ].join(' '),
            );
            return hay.includes(nq);
        });
    }, [q]);

    const filteredFaq = useMemo(() => {
        if (!q) return faqItems;
        const nq = normalize(q);

        return faqItems.filter((item) => {
            const hay = normalize(
                [item.question, item.answer, item.tags.join(' ')].join(' '),
            );
            return hay.includes(nq);
        });
    }, [q]);

    const hasResults = filteredGuidance.length > 0 || filteredFaq.length > 0;

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Panduan"
            subtitle="Ringkas, bisa dicari, dan siap membantu kamu bergerak"
        >
            <Head title="Panduan" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">Panduan</h1>
                        <p className="text-sm text-muted-foreground">
                            Temukan alur, tips bimbingan, dan FAQ tanpa harus
                            mencari chat lama.
                        </p>
                    </div>

                    <div className="w-full sm:max-w-md">
                        <div className="relative">
                            <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                            <Input
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder="Cari panduan atau FAQ..."
                                aria-label="Cari panduan atau FAQ"
                                className="pl-9"
                            />
                        </div>
                        <div className="mt-2 flex items-center justify-between gap-3 text-xs text-muted-foreground">
                            <span>
                                {q
                                    ? hasResults
                                        ? `Hasil untuk "${q}"`
                                        : `Tidak ada hasil untuk "${q}"`
                                    : 'Ketik kata kunci: revisi, bimbingan, upload, judul'}
                            </span>
                            {q && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-7 px-2 text-xs"
                                    onClick={() => setQuery('')}
                                >
                                    Reset
                                </Button>
                            )}
                        </div>
                    </div>
                </div>

                <div className="flex flex-wrap gap-2">
                    <Button type="button" variant="secondary" size="sm" asChild>
                        <a href="#alur">Alur</a>
                    </Button>
                    <Button type="button" variant="secondary" size="sm" asChild>
                        <a href="#faq">FAQ</a>
                    </Button>
                    <Button type="button" variant="secondary" size="sm" asChild>
                        <a href="#template">Dokumen Template</a>
                    </Button>
                    <Button type="button" variant="secondary" size="sm" asChild>
                        <a href="#bantuan">Bantuan</a>
                    </Button>
                </div>

                <section id="alur" className="space-y-3">
                    <div className="flex items-end justify-between gap-3">
                        <div>
                            <div className="text-sm font-semibold">
                                Panduan utama
                            </div>
                            <div className="text-sm text-muted-foreground">
                                Empat hal yang paling sering bikin macet.
                            </div>
                        </div>
                        <Badge variant="outline" className="rounded-full">
                            {filteredGuidance.length}/{guidanceCards.length}
                        </Badge>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        {filteredGuidance.map((card) => {
                            const Icon = card.icon;
                            return (
                                <Card
                                    key={card.id}
                                    className={cn(
                                        'scroll-mt-24',
                                        card.id === 'alur' && 'ring-0',
                                    )}
                                >
                                    <CardHeader className="gap-2">
                                        <div className="flex items-start justify-between gap-4">
                                            <div className="min-w-0">
                                                <div className="flex items-center gap-2">
                                                    <span className="inline-flex size-9 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                                                        <Icon className="size-4" />
                                                    </span>
                                                    <CardTitle className="text-base">
                                                        {card.title}
                                                    </CardTitle>
                                                </div>
                                                <CardDescription className="mt-2">
                                                    {card.description}
                                                </CardDescription>
                                            </div>
                                            <Badge
                                                variant="secondary"
                                                className="shrink-0 rounded-full"
                                            >
                                                {card.badge}
                                            </Badge>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <ul className="grid gap-2 text-sm text-muted-foreground">
                                            {card.bullets.map((b) => (
                                                <li
                                                    key={b}
                                                    className="flex gap-2"
                                                >
                                                    <span className="mt-2 size-1.5 shrink-0 rounded-full bg-muted-foreground/40" />
                                                    <span className="min-w-0">
                                                        {b}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>

                                        <Separator />

                                        <div className="flex flex-wrap gap-2">
                                            {card.id === 'jadwal' && (
                                                <Button
                                                    type="button"
                                                    className="h-9 bg-primary text-primary-foreground hover:bg-primary/90"
                                                    asChild
                                                >
                                                    <Link
                                                        href={
                                                            jadwalBimbinganCreate()
                                                                .url
                                                        }
                                                    >
                                                        <CalendarClock className="size-4" />
                                                        Ajukan Bimbingan
                                                    </Link>
                                                </Button>
                                            )}
                                            {card.id === 'upload' && (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    className="h-9"
                                                    asChild
                                                >
                                                    <Link
                                                        href={
                                                            uploadDokumen().url
                                                        }
                                                    >
                                                        <UploadCloud className="size-4" />
                                                        Buka Upload Dokumen
                                                    </Link>
                                                </Button>
                                            )}
                                            {card.id === 'komunikasi' && (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    className="h-9"
                                                    asChild
                                                >
                                                    <Link href={pesan().url}>
                                                        <MessageSquareText className="size-4" />
                                                        Kirim Pesan
                                                    </Link>
                                                </Button>
                                            )}
                                            {card.id === 'alur' && (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    className="h-9"
                                                    asChild
                                                >
                                                    <a href="#template">
                                                        <FileText className="size-4" />
                                                        Lihat Template
                                                    </a>
                                                </Button>
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                </section>

                <div className="grid gap-6 lg:grid-cols-[1fr_360px]">
                    <section id="faq" className="space-y-4">
                        <Card>
                            <CardHeader className="gap-1">
                                <CardTitle className="text-base">FAQ</CardTitle>
                                <CardDescription>
                                    Jawaban cepat untuk pertanyaan yang sering
                                    muncul.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {filteredFaq.length === 0 ? (
                                    <div className="rounded-xl border bg-muted/30 p-4">
                                        <div className="text-sm font-medium">
                                            Tidak ada FAQ yang cocok.
                                        </div>
                                        <div className="mt-1 text-sm text-muted-foreground">
                                            Coba kata kunci lain, atau buka
                                            kartu Bantuan untuk menghubungi
                                            admin.
                                        </div>
                                    </div>
                                ) : (
                                    <div className="grid gap-3">
                                        {filteredFaq.map((item, idx) => (
                                            <FaqAccordionItem
                                                key={item.id}
                                                item={item}
                                                defaultOpen={Boolean(
                                                    q && idx === 0,
                                                )}
                                            />
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </section>

                    <div className="grid gap-6">
                        <section id="template" className="space-y-4">
                            <Card>
                                <CardHeader className="gap-1">
                                    <CardTitle className="text-base">
                                        Dokumen Template
                                    </CardTitle>
                                    <CardDescription>
                                        Gunakan template agar format konsisten.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="grid gap-3">
                                        {templateDocs.map((doc, idx) => (
                                            <div key={doc.id}>
                                                <div className="flex items-start justify-between gap-3">
                                                    <div className="min-w-0">
                                                        <div className="flex items-center gap-2">
                                                            <FileText className="mt-0.5 size-4 text-muted-foreground" />
                                                            <div className="min-w-0">
                                                                <div className="truncate text-sm font-medium">
                                                                    {doc.title}
                                                                </div>
                                                                <div className="text-xs text-muted-foreground">
                                                                    {
                                                                        doc.description
                                                                    }
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div className="mt-2 flex items-center gap-2">
                                                            <Badge
                                                                variant="outline"
                                                                className="rounded-full"
                                                            >
                                                                {doc.format}
                                                            </Badge>
                                                            {doc.badge && (
                                                                <Badge className="rounded-full bg-primary text-primary-foreground hover:bg-primary/90">
                                                                    {doc.badge}
                                                                </Badge>
                                                            )}
                                                        </div>
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        className="h-8"
                                                        disabled
                                                    >
                                                        <FileDown className="size-4" />
                                                        Unduh
                                                    </Button>
                                                </div>
                                                {idx !==
                                                    templateDocs.length - 1 && (
                                                    <Separator className="my-3" />
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                    <div className="mt-4 rounded-xl border bg-muted/30 p-4 text-sm">
                                        <div className="font-medium">
                                            Tips: versi itu penting
                                        </div>
                                        <div className="mt-1 text-muted-foreground">
                                            Saat revisi, simpan versi lama dan
                                            naikkan nomor versi agar mudah
                                            dibandingkan.
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </section>

                        <section id="bantuan" className="space-y-4">
                            <Card className="overflow-hidden">
                                <CardHeader className="gap-1">
                                    <CardTitle className="text-base">
                                        Bantuan
                                    </CardTitle>
                                    <CardDescription>
                                        Jika masih bingung, mulai dari sini.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="rounded-xl border bg-background p-4">
                                        <div className="text-sm font-medium">
                                            Cek dulu yang paling dekat
                                        </div>
                                        <div className="mt-1 text-sm text-muted-foreground">
                                            Biasanya masalah selesai setelah:
                                            cari kata kunci, baca FAQ, lalu
                                            kirim pesan dengan konteks.
                                        </div>
                                    </div>

                                    <div className="grid gap-2">
                                        <Button
                                            type="button"
                                            className="h-9 bg-primary text-primary-foreground hover:bg-primary/90"
                                            asChild
                                        >
                                            <Link href={pesan().url}>
                                                <MessageSquareText className="size-4" />
                                                Hubungi Pembimbing/Admin
                                            </Link>
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="h-9"
                                            asChild
                                        >
                                            <Link href={uploadDokumen().url}>
                                                <UploadCloud className="size-4" />
                                                Lihat Status Dokumen
                                            </Link>
                                        </Button>
                                    </div>

                                    <div className="rounded-xl border bg-muted/30 p-4">
                                        <div className="text-sm font-medium">
                                            Format pesan yang efektif
                                        </div>
                                        <div className="mt-2 grid gap-2 text-sm text-muted-foreground">
                                            <div>
                                                1) Tahap: Bab 2 / Proposal /
                                                Revisi
                                            </div>
                                            <div>
                                                2) Tujuan: minta review atau
                                                keputusan
                                            </div>
                                            <div>
                                                3) Tautan: dokumen yang dirujuk
                                            </div>
                                            <div>
                                                4) Pertanyaan: pilihan A/B atau
                                                ya/tidak
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </section>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

