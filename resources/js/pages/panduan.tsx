import { Head, Link, usePage } from '@inertiajs/react';
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
import AppLayout from '@/layouts/app-layout';
import { dashboard, panduan, pesan, uploadDokumen } from '@/routes';
import { create as jadwalBimbinganCreate } from '@/routes/jadwal-bimbingan';
import { type BreadcrumbItem, type SharedData } from '@/types';

type GuidanceCard = {
    id: string;
    title: string;
    description: string;
    badge: string;
    icon:
        | 'calendar-clock'
        | 'file-text'
        | 'list-checks'
        | 'message-square-text'
        | 'upload-cloud';
    action: 'none' | 'template' | 'schedule' | 'upload' | 'message';
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
    badge?: string | null;
    fileName?: string | null;
    downloadUrl?: string | null;
};

type HelpContent = {
    title: string;
    description: string;
    boxTitle: string;
    boxDescription: string;
    messageTemplateTitle: string;
    messageTemplateSteps: string[];
};

type PanduanPageProps = {
    pageTitle: string;
    pageSubtitle: string;
    searchHint: string;
    guidanceCards: GuidanceCard[];
    faqItems: FaqItem[];
    templateDocs: TemplateDoc[];
    helpContent: HelpContent;
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

const iconMap = {
    'calendar-clock': CalendarClock,
    'file-text': FileText,
    'list-checks': ListChecks,
    'message-square-text': MessageSquareText,
    'upload-cloud': UploadCloud,
} as const;

const sectionCardClass = 'overflow-hidden border-border/70 py-0 shadow-sm';
const sectionCardHeaderClass = 'border-b bg-muted/20 px-6 py-4';

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
                        {item.tags.map((tag) => (
                            <Badge
                                key={tag}
                                variant="secondary"
                                className="rounded-full"
                            >
                                {tag}
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
    const {
        faqItems,
        guidanceCards,
        helpContent,
        pageSubtitle,
        pageTitle,
        templateDocs,
    } = usePage<SharedData & PanduanPageProps>().props;

    const [query, setQuery] = useState('');

    const q = query.trim();

    const filteredGuidance = useMemo(() => {
        if (!q) {
            return guidanceCards;
        }

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
    }, [guidanceCards, q]);

    const filteredFaq = useMemo(() => {
        if (!q) {
            return faqItems;
        }

        const nq = normalize(q);

        return faqItems.filter((item) => {
            const hay = normalize(
                [item.question, item.answer, item.tags.join(' ')].join(' '),
            );

            return hay.includes(nq);
        });
    }, [faqItems, q]);

    const filteredTemplateDocs = useMemo(() => {
        if (!q) {
            return templateDocs;
        }

        const nq = normalize(q);

        return templateDocs.filter((doc) => {
            const hay = normalize(
                [
                    doc.title,
                    doc.description,
                    doc.format,
                    doc.badge,
                    doc.fileName,
                ]
                    .filter(Boolean)
                    .join(' '),
            );

            return hay.includes(nq);
        });
    }, [q, templateDocs]);

    const totalSearchResults =
        filteredGuidance.length +
        filteredFaq.length +
        filteredTemplateDocs.length;

    const quickLinks = [
        { href: '#alur', label: 'Alur' },
        { href: '#faq', label: 'FAQ' },
        { href: '#template', label: 'Dokumen Template' },
        { href: '#bantuan', label: 'Bantuan' },
    ];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={pageTitle}
            subtitle={pageSubtitle}
        >
            <Head title={pageTitle} />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-6 lg:gap-8">
                <Card className="relative overflow-hidden border-border/70 p-0 shadow-sm">
                    <div className="pointer-events-none absolute inset-y-0 right-0 w-72 bg-[radial-gradient(circle_at_top_right,_hsl(var(--primary)/0.18),_transparent_68%)]" />
                    <div className="pointer-events-none absolute -top-16 left-1/3 h-40 w-40 rounded-full bg-primary/8 blur-3xl" />
                    <CardContent className="relative grid gap-6 bg-gradient-to-r from-background via-background to-accent/10 p-6 lg:grid-cols-[minmax(0,1fr)_minmax(360px,460px)] lg:items-center lg:p-8">
                        <div className="space-y-5 lg:pr-4">
                            <div className="space-y-3">
                                <h1 className="text-2xl font-semibold tracking-tight text-foreground lg:text-3xl">
                                    {pageTitle}
                                </h1>
                                <p className="max-w-2xl text-sm leading-6 text-muted-foreground lg:text-base">
                                    {pageSubtitle}
                                </p>
                            </div>

                            <div className="flex flex-wrap gap-2">
                                {quickLinks.map((link) => (
                                    <Button
                                        key={link.href}
                                        type="button"
                                        variant="secondary"
                                        size="sm"
                                        className="rounded-full"
                                        asChild
                                    >
                                        <a href={link.href}>{link.label}</a>
                                    </Button>
                                ))}
                            </div>
                        </div>

                        <div className="space-y-3 lg:self-start lg:justify-self-end lg:pt-1">
                            <div className="relative">
                                <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                                <Input
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                    placeholder="Cari panduan atau FAQ..."
                                    aria-label="Cari panduan atau FAQ"
                                    className="h-11 rounded-xl border-border/70 bg-background/90 pl-10 shadow-sm"
                                />
                            </div>

                            <div className="flex min-h-5 items-center justify-between gap-3 text-xs text-muted-foreground">
                                <span>
                                    {q
                                        ? `${totalSearchResults} hasil ditemukan`
                                        : 'Cari alur, template, revisi, atau bimbingan'}
                                </span>
                                {q ? (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="h-7 px-2 text-xs"
                                        onClick={() => setQuery('')}
                                    >
                                        Reset
                                    </Button>
                                ) : null}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <section id="alur" className="scroll-mt-24 space-y-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <div className="text-base font-semibold text-foreground">
                                Panduan utama
                            </div>
                            <div className="text-sm leading-6 text-muted-foreground">
                                Empat hal yang paling sering bikin macet.
                            </div>
                        </div>
                        <Badge variant="outline" className="rounded-full">
                            {filteredGuidance.length}/{guidanceCards.length}
                        </Badge>
                    </div>

                    <div className="grid gap-4 xl:grid-cols-2">
                        {filteredGuidance.map((card) => {
                            const Icon = iconMap[card.icon] ?? FileText;

                            return (
                                <Card
                                    key={card.id}
                                    className={`${sectionCardClass} h-full`}
                                >
                                    <CardHeader
                                        className={`${sectionCardHeaderClass} gap-3`}
                                    >
                                        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center gap-2">
                                                    <span className="inline-flex size-9 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                                                        <Icon className="size-4" />
                                                    </span>
                                                    <CardTitle className="text-base">
                                                        {card.title}
                                                    </CardTitle>
                                                </div>
                                                <CardDescription className="mt-2 text-sm leading-6">
                                                    {card.description}
                                                </CardDescription>
                                            </div>
                                            <Badge
                                                variant="secondary"
                                                className="w-fit shrink-0 rounded-full"
                                            >
                                                {card.badge}
                                            </Badge>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="flex h-full flex-col gap-5 pb-6">
                                        <ul className="grid flex-1 gap-2.5 text-sm text-muted-foreground">
                                            {card.bullets.map((bullet) => (
                                                <li
                                                    key={bullet}
                                                    className="flex gap-2"
                                                >
                                                    <span className="mt-2 size-1.5 shrink-0 rounded-full bg-muted-foreground/40" />
                                                    <span className="min-w-0">
                                                        {bullet}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>

                                        <div className="border-t pt-4">
                                            <div className="mb-3 flex flex-wrap gap-2">
                                                {card.keywords.map(
                                                    (keyword) => (
                                                        <Badge
                                                            key={keyword}
                                                            variant="secondary"
                                                            className="rounded-full"
                                                        >
                                                            {keyword}
                                                        </Badge>
                                                    ),
                                                )}
                                            </div>

                                            <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                                                {card.action === 'schedule' ? (
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
                                                            className="w-full sm:w-auto"
                                                        >
                                                            <CalendarClock className="size-4" />
                                                            Ajukan Bimbingan
                                                        </Link>
                                                    </Button>
                                                ) : null}

                                                {card.action === 'upload' ? (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        className="h-9"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={
                                                                uploadDokumen()
                                                                    .url
                                                            }
                                                            className="w-full sm:w-auto"
                                                        >
                                                            <UploadCloud className="size-4" />
                                                            Buka Upload Dokumen
                                                        </Link>
                                                    </Button>
                                                ) : null}

                                                {card.action === 'message' ? (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        className="h-9"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={pesan().url}
                                                            className="w-full sm:w-auto"
                                                        >
                                                            <MessageSquareText className="size-4" />
                                                            Kirim Pesan
                                                        </Link>
                                                    </Button>
                                                ) : null}

                                                {card.action === 'template' ? (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        className="h-9"
                                                        asChild
                                                    >
                                                        <a
                                                            href="#template"
                                                            className="w-full sm:w-auto"
                                                        >
                                                            <FileText className="size-4" />
                                                            Lihat Template
                                                        </a>
                                                    </Button>
                                                ) : null}
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                </section>

                <section id="faq" className="scroll-mt-24 space-y-4">
                    <Card className={sectionCardClass}>
                        <CardHeader
                            className={`${sectionCardHeaderClass} gap-3 sm:flex-row sm:items-center sm:justify-between`}
                        >
                            <div>
                                <CardTitle className="text-base">FAQ</CardTitle>
                                <CardDescription className="mt-1">
                                    Jawaban cepat untuk pertanyaan yang sering
                                    muncul.
                                </CardDescription>
                            </div>
                            <Badge
                                variant="outline"
                                className="w-fit rounded-full"
                            >
                                {filteredFaq.length}/{faqItems.length}
                            </Badge>
                        </CardHeader>
                        <CardContent className="space-y-3 pb-6">
                            {filteredFaq.length === 0 ? (
                                <div className="rounded-xl border bg-muted/30 p-4">
                                    <div className="text-sm font-medium">
                                        Tidak ada FAQ yang cocok.
                                    </div>
                                    <div className="mt-1 text-sm text-muted-foreground">
                                        Coba kata kunci lain, atau buka kartu
                                        Bantuan untuk menghubungi admin.
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

                <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]">
                    <section id="template" className="scroll-mt-24 space-y-4">
                        <Card className={sectionCardClass}>
                            <CardHeader
                                className={`${sectionCardHeaderClass} gap-3 sm:flex-row sm:items-center sm:justify-between`}
                            >
                                <div>
                                    <CardTitle className="text-base">
                                        Dokumen Template
                                    </CardTitle>
                                    <CardDescription className="mt-1">
                                        Gunakan template agar format konsisten.
                                    </CardDescription>
                                </div>
                                <Badge
                                    variant="outline"
                                    className="w-fit rounded-full"
                                >
                                    {filteredTemplateDocs.length}/
                                    {templateDocs.length}
                                </Badge>
                            </CardHeader>
                            <CardContent className="space-y-4 pb-6">
                                {filteredTemplateDocs.length === 0 ? (
                                    <div className="rounded-xl border bg-muted/30 p-4 text-sm text-muted-foreground">
                                        Tidak ada dokumen template yang cocok
                                        dengan pencarianmu.
                                    </div>
                                ) : (
                                    <div className="grid gap-3">
                                        {filteredTemplateDocs.map((doc) => (
                                            <div
                                                key={doc.id}
                                                className="rounded-xl border bg-background p-4"
                                            >
                                                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                                    <div className="min-w-0 flex-1">
                                                        <div className="flex items-start gap-3">
                                                            <span className="mt-0.5 inline-flex size-9 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                                                                <FileText className="size-4" />
                                                            </span>
                                                            <div className="min-w-0">
                                                                <div className="text-sm font-medium break-words">
                                                                    {doc.title}
                                                                </div>
                                                                <div className="mt-1 text-sm leading-6 text-muted-foreground">
                                                                    {
                                                                        doc.description
                                                                    }
                                                                </div>
                                                                <div className="mt-3 flex flex-wrap items-center gap-2">
                                                                    <Badge
                                                                        variant="outline"
                                                                        className="rounded-full"
                                                                    >
                                                                        {
                                                                            doc.format
                                                                        }
                                                                    </Badge>
                                                                    {doc.badge ? (
                                                                        <Badge className="rounded-full bg-primary text-primary-foreground hover:bg-primary/90">
                                                                            {
                                                                                doc.badge
                                                                            }
                                                                        </Badge>
                                                                    ) : null}
                                                                    {doc.fileName ? (
                                                                        <Badge
                                                                            variant="secondary"
                                                                            className="rounded-full"
                                                                        >
                                                                            {
                                                                                doc.fileName
                                                                            }
                                                                        </Badge>
                                                                    ) : null}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    {doc.downloadUrl ? (
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            className="h-9 w-full sm:w-auto"
                                                            asChild
                                                        >
                                                            <a
                                                                href={
                                                                    doc.downloadUrl
                                                                }
                                                                target="_blank"
                                                                rel="noreferrer"
                                                            >
                                                                <FileDown className="size-4" />
                                                                Unduh
                                                            </a>
                                                        </Button>
                                                    ) : (
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            className="h-9 w-full sm:w-auto"
                                                            disabled
                                                        >
                                                            <FileDown className="size-4" />
                                                            Belum tersedia
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}

                                <div className="rounded-xl border bg-muted/30 p-4 text-sm">
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

                    <section id="bantuan" className="scroll-mt-24 space-y-4">
                        <Card className={sectionCardClass}>
                            <CardHeader className={sectionCardHeaderClass}>
                                <CardTitle className="text-base">
                                    {helpContent.title}
                                </CardTitle>
                                <CardDescription>
                                    {helpContent.description}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4 pb-6">
                                <div className="rounded-xl border bg-background p-4">
                                    <div className="text-sm font-medium">
                                        {helpContent.boxTitle}
                                    </div>
                                    <div className="mt-1 text-sm leading-6 text-muted-foreground">
                                        {helpContent.boxDescription}
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Button
                                        type="button"
                                        className="h-10 bg-primary text-primary-foreground hover:bg-primary/90"
                                        asChild
                                    >
                                        <Link
                                            href={pesan().url}
                                            className="w-full"
                                        >
                                            <MessageSquareText className="size-4" />
                                            Hubungi Pembimbing/Admin
                                        </Link>
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="h-10"
                                        asChild
                                    >
                                        <Link
                                            href={uploadDokumen().url}
                                            className="w-full"
                                        >
                                            <UploadCloud className="size-4" />
                                            Lihat Status Dokumen
                                        </Link>
                                    </Button>
                                </div>

                                <div className="rounded-xl border bg-muted/30 p-4">
                                    <div className="text-sm font-medium">
                                        {helpContent.messageTemplateTitle}
                                    </div>
                                    <div className="mt-3 grid gap-2 text-sm text-muted-foreground">
                                        {helpContent.messageTemplateSteps.map(
                                            (step) => (
                                                <div key={step}>{step}</div>
                                            ),
                                        )}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </section>
                </div>
            </div>
        </AppLayout>
    );
}
