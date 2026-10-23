import { Head, Link, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    ChevronDown,
    FileDown,
    FileText,
    ListChecks,
    MessageSquareText,
    UploadCloud,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { DataSection, DataToolbar } from '@/components/patterns/data-section';
import { FilterPills } from '@/components/patterns/filter-pills';
import { PageHeader } from '@/components/patterns/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
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
        <details className="group open:bg-muted/30" open={defaultOpen}>
            <summary className="flex min-h-11 cursor-pointer list-none items-start justify-between gap-4 px-6 py-4 outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset">
                <div className="min-w-0">
                    <h3 className="text-sm font-semibold text-balance">
                        {item.question}
                    </h3>
                    <div className="mt-1.5 flex flex-wrap gap-1">
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
                <ChevronDown
                    aria-hidden="true"
                    className="mt-0.5 size-4 shrink-0 text-muted-foreground transition-transform group-open:rotate-180"
                />
            </summary>
            <p className="max-w-prose px-6 pb-5 text-sm leading-6 text-pretty text-muted-foreground">
                {item.answer}
            </p>
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
        searchHint,
        templateDocs,
    } = usePage<SharedData & PanduanPageProps>().props;

    const [query, setQuery] = useState('');
    const [activeSection, setActiveSection] = useState('alur');

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

    const quickLinks = useMemo(
        () => [
            { value: 'alur', label: 'Alur' },
            { value: 'faq', label: 'FAQ' },
            { value: 'template', label: 'Dokumen Template' },
            { value: 'bantuan', label: 'Bantuan' },
        ],
        [],
    );

    useEffect(() => {
        const syncActiveSection = () => {
            const nextHash = window.location.hash.replace('#', '');
            if (nextHash) {
                setActiveSection(nextHash);
            }
        };

        syncActiveSection();
        window.addEventListener('hashchange', syncActiveSection);

        return () =>
            window.removeEventListener('hashchange', syncActiveSection);
    }, []);

    useEffect(() => {
        const sections = quickLinks
            .map((link) => document.getElementById(link.value))
            .filter((section): section is HTMLElement => section !== null);

        if (sections.length === 0) {
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                const visibleEntries = entries
                    .filter((entry) => entry.isIntersecting)
                    .sort(
                        (a, b) =>
                            b.intersectionRatio - a.intersectionRatio ||
                            a.boundingClientRect.top - b.boundingClientRect.top,
                    );

                if (visibleEntries.length > 0) {
                    setActiveSection(visibleEntries[0].target.id);
                }
            },
            {
                rootMargin: '-96px 0px -55% 0px',
                threshold: [0.1, 0.25, 0.5, 0.75],
            },
        );

        sections.forEach((section) => observer.observe(section));

        return () => observer.disconnect();
    }, [quickLinks]);

    const jumpToSection = (section: string) => {
        setActiveSection(section);

        const target = document.getElementById(section);
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            window.history.replaceState(null, '', `#${section}`);
        }
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={pageTitle}
            subtitle={pageSubtitle}
        >
            <Head title={pageTitle} />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-6 lg:gap-8">
                <div className="space-y-4">
                    <PageHeader
                        title={pageTitle}
                        description={
                            <span className="block max-w-prose text-pretty">
                                {pageSubtitle}
                            </span>
                        }
                    />

                    <DataToolbar
                        search={query}
                        onSearchChange={setQuery}
                        placeholder={searchHint}
                    >
                        <FilterPills
                            label="Lompat ke bagian"
                            value={activeSection}
                            onValueChange={jumpToSection}
                            options={quickLinks}
                            itemClassName="max-md:h-11 max-md:px-4"
                        />
                        {q ? (
                            <div className="flex items-center gap-2">
                                <Badge
                                    variant="outline"
                                    className="rounded-full"
                                >
                                    {totalSearchResults} hasil
                                </Badge>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-8 px-2 text-xs max-md:h-11 max-md:px-3"
                                    onClick={() => setQuery('')}
                                >
                                    Reset
                                </Button>
                            </div>
                        ) : null}
                    </DataToolbar>
                </div>

                <div id="alur" className="scroll-mt-24">
                    <DataSection
                        title="Panduan utama"
                        summary={`Empat hal yang paling sering bikin macet. ${filteredGuidance.length}/${guidanceCards.length}`}
                    >
                        <div className="grid gap-4 xl:grid-cols-2">
                            {filteredGuidance.map((card) => {
                                const Icon = iconMap[card.icon] ?? FileText;

                                return (
                                    <Card
                                        key={card.id}
                                        className="h-full gap-0 overflow-hidden border-border/70 py-0 shadow-sm"
                                    >
                                        <div className="flex flex-1 flex-col gap-4 p-6">
                                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                                <div className="flex min-w-0 items-center gap-3">
                                                    <span className="inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary-muted text-primary-muted-foreground">
                                                        <Icon
                                                            aria-hidden="true"
                                                            className="size-4"
                                                        />
                                                    </span>
                                                    <h3 className="text-base leading-snug font-semibold text-balance">
                                                        {card.title}
                                                    </h3>
                                                </div>
                                                <Badge
                                                    variant="secondary"
                                                    className="w-fit shrink-0 rounded-full"
                                                >
                                                    {card.badge}
                                                </Badge>
                                            </div>
                                            <p className="max-w-prose text-sm leading-6 text-pretty text-muted-foreground">
                                                {card.description}
                                            </p>
                                            <ul className="grid gap-2.5 text-sm leading-6 text-muted-foreground">
                                                {card.bullets.map((bullet) => (
                                                    <li
                                                        key={bullet}
                                                        className="flex max-w-prose gap-2"
                                                    >
                                                        <span
                                                            aria-hidden="true"
                                                            className="mt-2.5 size-1.5 shrink-0 rounded-full bg-muted-foreground"
                                                        />
                                                        <span className="min-w-0 text-pretty">
                                                            {bullet}
                                                        </span>
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>

                                        <div className="space-y-3 border-t px-6 py-4">
                                            <div className="flex flex-wrap gap-2">
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
                                                        className="h-9 bg-primary text-primary-foreground hover:bg-primary/90 max-md:h-11"
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
                                                        className="h-9 max-md:h-11"
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
                                                        className="h-9 max-md:h-11"
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
                                                        className="h-9 max-md:h-11"
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
                                    </Card>
                                );
                            })}
                        </div>
                    </DataSection>
                </div>

                <div id="faq" className="scroll-mt-24">
                    <DataSection
                        title="FAQ"
                        summary={`Jawaban cepat untuk pertanyaan yang sering muncul. ${filteredFaq.length}/${faqItems.length}`}
                    >
                        <Card className="gap-0 overflow-hidden border-border/70 py-0 shadow-sm">
                            {filteredFaq.length === 0 ? (
                                <div className="px-6 py-5">
                                    <p className="text-sm font-medium">
                                        Tidak ada FAQ yang cocok.
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Coba kata kunci lain, atau buka kartu
                                        Bantuan untuk menghubungi admin.
                                    </p>
                                </div>
                            ) : (
                                <div className="divide-y">
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
                        </Card>
                    </DataSection>
                </div>

                <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]">
                    <div id="template" className="scroll-mt-24">
                        <DataSection
                            title="Dokumen Template"
                            summary={`Gunakan template agar format konsisten. ${filteredTemplateDocs.length}/${templateDocs.length}`}
                        >
                            <Card className="gap-0 overflow-hidden border-border/70 py-0 shadow-sm">
                                {filteredTemplateDocs.length === 0 ? (
                                    <p className="px-6 py-5 text-sm text-muted-foreground">
                                        Tidak ada dokumen template yang cocok
                                        dengan pencarianmu.
                                    </p>
                                ) : (
                                    <ul className="divide-y">
                                        {filteredTemplateDocs.map((doc) => (
                                            <li
                                                key={doc.id}
                                                className="flex flex-col gap-3 px-6 py-5 sm:flex-row sm:items-start sm:justify-between"
                                            >
                                                <div className="flex min-w-0 flex-1 items-start gap-3">
                                                    <span className="mt-0.5 inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary-muted text-primary-muted-foreground">
                                                        <FileText
                                                            aria-hidden="true"
                                                            className="size-4"
                                                        />
                                                    </span>
                                                    <div className="min-w-0">
                                                        <h3 className="text-sm font-medium text-balance break-words">
                                                            {doc.title}
                                                        </h3>
                                                        <p className="mt-1 max-w-prose text-sm leading-6 text-pretty text-muted-foreground">
                                                            {doc.description}
                                                        </p>
                                                        <div className="mt-3 flex flex-wrap items-center gap-2">
                                                            <Badge
                                                                variant="outline"
                                                                className="rounded-full"
                                                            >
                                                                {doc.format}
                                                            </Badge>
                                                            {doc.badge ? (
                                                                <Badge className="rounded-full bg-primary text-primary-foreground hover:bg-primary/90">
                                                                    {doc.badge}
                                                                </Badge>
                                                            ) : null}
                                                            {doc.fileName ? (
                                                                <Badge
                                                                    variant="secondary"
                                                                    className="max-w-full rounded-full text-left break-all whitespace-normal"
                                                                >
                                                                    {
                                                                        doc.fileName
                                                                    }
                                                                </Badge>
                                                            ) : null}
                                                        </div>
                                                    </div>
                                                </div>

                                                {doc.downloadUrl ? (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        className="h-9 w-full max-md:h-11 sm:w-auto"
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
                                                        className="h-9 w-full max-md:h-11 sm:w-auto"
                                                        disabled
                                                    >
                                                        <FileDown className="size-4" />
                                                        Belum tersedia
                                                    </Button>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                )}

                                <div className="border-t bg-muted/30 px-6 py-4 text-sm">
                                    <p className="font-medium">
                                        Tips: versi itu penting
                                    </p>
                                    <p className="mt-1 max-w-prose text-pretty text-muted-foreground">
                                        Saat revisi, simpan versi lama dan
                                        naikkan nomor versi agar mudah
                                        dibandingkan.
                                    </p>
                                </div>
                            </Card>
                        </DataSection>
                    </div>

                    <div id="bantuan" className="scroll-mt-24">
                        <DataSection title={helpContent.title}>
                            <Card className="gap-0 overflow-hidden border-border/70 py-0 shadow-sm">
                                <div className="divide-y">
                                    <div className="space-y-1 px-6 py-5">
                                        <p className="text-sm leading-6 text-pretty text-muted-foreground">
                                            {helpContent.description}
                                        </p>
                                    </div>

                                    <div className="space-y-4 px-6 py-5">
                                        <div>
                                            <p className="text-sm font-medium">
                                                {helpContent.boxTitle}
                                            </p>
                                            <p className="mt-1 text-sm leading-6 text-pretty text-muted-foreground">
                                                {helpContent.boxDescription}
                                            </p>
                                        </div>

                                        <div className="grid gap-2">
                                            <Button
                                                type="button"
                                                className="h-10 bg-primary text-primary-foreground hover:bg-primary/90 max-md:h-11"
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
                                                className="h-10 max-md:h-11"
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
                                    </div>

                                    <div className="bg-muted/30 px-6 py-5">
                                        <p className="text-sm font-medium">
                                            {helpContent.messageTemplateTitle}
                                        </p>
                                        <div className="mt-3 grid gap-2 text-sm leading-6 text-muted-foreground">
                                            {helpContent.messageTemplateSteps.map(
                                                (step) => (
                                                    <p key={step}>{step}</p>
                                                ),
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </Card>
                        </DataSection>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
