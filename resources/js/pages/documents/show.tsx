import type { FormComponentRef, FormComponentSlotProps } from '@inertiajs/core';
import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    Download,
    ExternalLink,
    MessageSquare,
    PencilLine,
} from 'lucide-react';
import { useEffect, useRef } from 'react';

import {
    RevisionEditor,
    RevisionList,
    type DocumentRevisionItem,
    type ReviewRevisionItem,
} from '@/components/document-revision-items';
import InputError from '@/components/input-error';
import { KeyValueList } from '@/components/patterns/key-value-list';
import { PageHeader } from '@/components/patterns/page-header';
import { SectionCard } from '@/components/patterns/section-card';
import { StatusBadge } from '@/components/patterns/status-badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import DosenLayout from '@/layouts/dosen-layout';
import KaprodiLayout from '@/layouts/kaprodi-layout';
import type { BreadcrumbItem, SharedData } from '@/types';

type DocumentDetail = {
    id: number;
    source: 'workspace' | 'thesis';
    title: string;
    fileName: string;
    category: string;
    version: number | null;
    uploadedAt: string | null;
    studentName: string;
    studentNim: string | null;
    status: string;
    statusLabel: string;
    downloadUrl: string | null;
    viewUrl: string | null;
    reviews: {
        id: number;
        reviewer: string;
        role: string;
        status: string;
        notes: string | null;
        reviewedAt: string | null;
        revisionItems: DocumentRevisionItem[];
    }[];
    versions: {
        id: number;
        version: number | null;
        fileName: string;
        uploadedAt: string | null;
        status: string;
        statusLabel: string;
        url: string;
    }[];
    canReview: boolean;
    reviewUrl: string | null;
    reviewNotes: string;
    reviewItems: ReviewRevisionItem[];
    reviewDetailUrl: string | null;
};

type DocumentPageProps = {
    role: 'dosen' | 'mahasiswa' | 'kaprodi';
    document: DocumentDetail;
    navigation: {
        listUrl: string;
        listLabel: string;
        chatUrl: string | null;
        chatLabel: string | null;
    };
};

const reviewStatusLabels: Record<string, string> = {
    submitted: 'Perlu review',
    needs_revision: 'Perlu revisi',
    approved: 'Disetujui',
    revision_completed: 'Revisi selesai',
};

const leaveMessage = 'Catatan dosen belum disimpan. Tinggalkan halaman ini?';

function DocumentBody({
    document,
    navigation,
    review,
}: Pick<DocumentPageProps, 'document' | 'navigation'> & {
    review?: FormComponentSlotProps;
}) {
    return (
        <>
            <nav
                aria-label="Navigasi dokumen"
                className="flex flex-wrap items-center gap-2"
            >
                <Button
                    variant="ghost"
                    size="sm"
                    asChild
                    className="max-md:min-h-11"
                >
                    <Link href={navigation.listUrl}>
                        <ArrowLeft aria-hidden className="size-4" />
                        {navigation.listLabel}
                    </Link>
                </Button>
                {navigation.chatUrl && (
                    <Button
                        variant="outline"
                        size="sm"
                        asChild
                        className="max-md:min-h-11"
                    >
                        <Link href={navigation.chatUrl}>
                            <MessageSquare aria-hidden className="size-4" />
                            {navigation.chatLabel ?? 'Buka chat'}
                        </Link>
                    </Button>
                )}
            </nav>

            <PageHeader
                title="Detail dokumen"
                description={
                    document.source === 'thesis'
                        ? 'Berkas yang tercatat pada pengajuan ujian. Review dilakukan pada dokumen bimbingan terkait.'
                        : 'Dokumen dan catatan review untuk versi ini.'
                }
                actions={
                    <>
                        {document.downloadUrl && (
                            <Button
                                variant="outline"
                                size="sm"
                                asChild
                                className="max-md:min-h-11"
                            >
                                <a href={document.downloadUrl}>
                                    <Download aria-hidden className="size-4" />
                                    Unduh
                                </a>
                            </Button>
                        )}
                    </>
                }
            />

            <SectionCard
                title="Informasi dokumen"
                actions={
                    <StatusBadge domain="document" status={document.status}>
                        {document.statusLabel}
                    </StatusBadge>
                }
            >
                <KeyValueList
                    layout="inline"
                    items={[
                        { label: 'Mahasiswa', value: document.studentName },
                        {
                            label: 'NIM',
                            value: (
                                <span className="tabular-nums">
                                    {document.studentNim ?? '-'}
                                </span>
                            ),
                        },
                        { label: 'Judul', value: document.title || '-' },
                        {
                            label: 'Nama file',
                            value: (
                                <span className="break-all">
                                    {document.fileName}
                                </span>
                            ),
                        },
                        { label: 'Kategori', value: document.category },
                        {
                            label: 'Versi',
                            value: (
                                <span className="tabular-nums">
                                    {document.version ?? '-'}
                                </span>
                            ),
                        },
                        {
                            label: 'Diunggah',
                            value: document.uploadedAt ?? '-',
                        },
                    ]}
                />
                {(document.viewUrl || document.reviewDetailUrl) && (
                    <div className="mt-5 flex flex-wrap gap-2 border-t pt-4">
                        {document.viewUrl && (
                            <Button
                                variant="outline"
                                size="sm"
                                asChild
                                className="max-md:min-h-11"
                            >
                                <a
                                    href={document.viewUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <ExternalLink
                                        aria-hidden
                                        className="size-4"
                                    />
                                    Buka file
                                    <span className="sr-only">
                                        {' '}
                                        di tab baru
                                    </span>
                                </a>
                            </Button>
                        )}
                        {document.reviewDetailUrl && (
                            <Button
                                variant="outline"
                                size="sm"
                                asChild
                                className="max-md:min-h-11"
                            >
                                <Link href={document.reviewDetailUrl}>
                                    Tinjau dokumen bimbingan
                                </Link>
                            </Button>
                        )}
                    </div>
                )}
            </SectionCard>

            {review && (
                <SectionCard title="Review Anda">
                    <div className="grid gap-2">
                        <Label htmlFor="revision-notes">Catatan dosen</Label>
                        <Textarea
                            id="revision-notes"
                            name="revision_notes"
                            defaultValue={document.reviewNotes}
                            rows={6}
                            readOnly={review.processing}
                            aria-invalid={Boolean(review.errors.revision_notes)}
                            aria-describedby={
                                review.errors.revision_notes
                                    ? 'review-notes-help review-notes-error'
                                    : 'review-notes-help'
                            }
                            className="min-h-36 resize-y"
                        />
                        <p
                            id="review-notes-help"
                            className="text-sm text-muted-foreground"
                        >
                            Isi butir di bawah untuk meminta revisi per poin.
                            Tanpa butir, catatan ini menjadi satu permintaan
                            revisi. Saat menyetujui, catatan bersifat opsional.
                        </p>
                        <InputError
                            id="review-notes-error"
                            message={review.errors.revision_notes}
                        />
                        <InputError message={review.errors.status} />
                        <p
                            role="status"
                            aria-live="polite"
                            className="text-sm text-muted-foreground"
                        >
                            {review.processing
                                ? 'Menyimpan review...'
                                : review.isDirty
                                  ? 'Catatan belum disimpan.'
                                  : null}
                        </p>
                    </div>
                    <RevisionEditor
                        initialItems={document.reviewItems}
                        processing={review.processing}
                        errors={review.errors}
                    />
                    <div className="mt-5 flex flex-wrap justify-end gap-2 border-t pt-4">
                        <Button
                            type="submit"
                            name="status"
                            value="needs_revision"
                            variant="outline"
                            size="sm"
                            disabled={review.processing}
                            className="max-md:min-h-11"
                        >
                            <PencilLine aria-hidden className="size-4" />
                            Minta revisi
                        </Button>
                        <Button
                            type="submit"
                            name="status"
                            value="approved"
                            size="sm"
                            disabled={review.processing}
                            className="max-md:min-h-11"
                        >
                            <CheckCircle2 aria-hidden className="size-4" />
                            Setujui
                        </Button>
                    </div>
                </SectionCard>
            )}

            <SectionCard
                title="Review dokumen"
                description="Status dan catatan dosen untuk versi dokumen ini."
            >
                {document.reviews.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Belum ada review untuk versi ini.
                    </p>
                ) : (
                    <ul className="divide-y">
                        {document.reviews.map((item) => (
                            <li
                                key={item.id}
                                className="space-y-3 py-4 first:pt-0 last:pb-0"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0 space-y-1">
                                        <h3 className="text-sm font-medium break-words">
                                            {item.reviewer}
                                        </h3>
                                        <p className="text-xs text-muted-foreground">
                                            {item.role}
                                            {item.reviewedAt
                                                ? ` · ${item.reviewedAt}`
                                                : ''}
                                        </p>
                                    </div>
                                    <StatusBadge
                                        domain="document"
                                        status={item.status}
                                    >
                                        {reviewStatusLabels[item.status] ??
                                            item.status}
                                    </StatusBadge>
                                </div>
                                {item.revisionItems.length > 0 && (
                                    <RevisionList items={item.revisionItems} />
                                )}
                                {item.notes &&
                                (item.status === 'approved' ||
                                    item.revisionItems.length === 0) ? (
                                    <div className="space-y-1 text-sm">
                                        <p className="font-medium">
                                            {item.status === 'approved'
                                                ? 'Masukan persetujuan'
                                                : 'Catatan revisi'}
                                        </p>
                                        <p className="max-w-prose break-words whitespace-pre-wrap">
                                            {item.notes}
                                        </p>
                                    </div>
                                ) : item.revisionItems.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Belum ada catatan.
                                    </p>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                )}
            </SectionCard>

            <SectionCard
                title="Riwayat versi"
                description="Buka versi lain untuk melihat dokumen dan review pada versi tersebut."
            >
                {document.versions.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Tidak ada versi lain yang tersedia.
                    </p>
                ) : (
                    <ul className="divide-y">
                        {document.versions.map((item) => (
                            <li key={item.id}>
                                <Link
                                    href={item.url}
                                    aria-current={
                                        item.id === document.id
                                            ? 'page'
                                            : undefined
                                    }
                                    className="group grid min-h-11 grid-cols-[minmax(0,1fr)_auto] items-start gap-x-3 gap-y-1 py-2 text-sm transition-colors hover:bg-muted/50 focus-visible:rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    <span className="min-w-0 font-medium [overflow-wrap:anywhere]">
                                        {item.version === null
                                            ? item.fileName
                                            : `Versi ${item.version}`}
                                    </span>
                                    <StatusBadge
                                        domain="document"
                                        status={item.status}
                                    >
                                        {item.statusLabel}
                                    </StatusBadge>
                                    {item.version !== null && (
                                        <span className="col-span-2 min-w-0 [overflow-wrap:anywhere] text-primary underline-offset-4 group-hover:underline md:col-span-1">
                                            {item.fileName}
                                        </span>
                                    )}
                                    <span className="col-span-2 flex flex-wrap gap-x-2 text-xs text-muted-foreground md:col-span-1 md:col-start-2 md:row-start-2 md:justify-end">
                                        <span>{item.uploadedAt ?? '-'}</span>
                                        {item.id === document.id && (
                                            <span className="font-medium text-primary">
                                                Sedang dibuka
                                            </span>
                                        )}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </SectionCard>
        </>
    );
}

export default function DocumentShowPage() {
    const page = usePage<SharedData & DocumentPageProps>();
    const { role, document, navigation } = page.props;
    const formRef = useRef<FormComponentRef>(null);
    const submitting = useRef(false);
    const canReview = document.canReview && document.reviewUrl !== null;

    useEffect(() => {
        if (!canReview) return;

        let currentEntry = {
            state: window.history.state,
            url: window.location.href,
        };
        const stopBefore = router.on('before', (event) => {
            const visit = event.detail.visit;
            const isReviewSubmission =
                submitting.current &&
                visit.method === 'post' &&
                visit.url.href ===
                    new URL(document.reviewUrl!, window.location.origin).href;
            if (
                !isReviewSubmission &&
                formRef.current?.isDirty &&
                !window.confirm(leaveMessage)
            ) {
                event.preventDefault();
            }
        });
        const stopNavigate = router.on('navigate', () => {
            currentEntry = {
                state: window.history.state,
                url: window.location.href,
            };
        });
        const beforeUnload = (event: BeforeUnloadEvent) => {
            if (!formRef.current?.isDirty) return;
            event.preventDefault();
            event.returnValue = '';
        };
        const popState = (event: Event) => {
            if (!formRef.current?.isDirty || window.confirm(leaveMessage))
                return;

            event.preventDefault();
            window.history.pushState(currentEntry.state, '', currentEntry.url);
        };
        window.addEventListener('beforeunload', beforeUnload);
        window.addEventListener('sita:before-popstate', popState);
        return () => {
            stopBefore();
            stopNavigate();
            window.removeEventListener('beforeunload', beforeUnload);
            window.removeEventListener('sita:before-popstate', popState);
        };
    }, [canReview, document.id, document.reviewUrl]);

    const Layout =
        role === 'dosen'
            ? DosenLayout
            : role === 'kaprodi'
              ? KaprodiLayout
              : AppLayout;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: navigation.listLabel, href: navigation.listUrl },
        { title: 'Detail dokumen', href: page.url },
    ];
    const contentClassName =
        'mx-auto flex w-full min-w-0 max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-6 lg:py-8';

    return (
        <Layout breadcrumbs={breadcrumbs} title="Detail dokumen">
            <Head title={`Detail dokumen - ${document.fileName}`} />
            {canReview ? (
                <Form
                    key={`${document.source}-${document.id}-${JSON.stringify(document.reviewItems)}`}
                    ref={formRef}
                    method="post"
                    action={document.reviewUrl!}
                    className={contentClassName}
                    options={{ preserveScroll: true }}
                    setDefaultsOnSuccess
                    disableWhileProcessing
                    onSubmitCapture={(event) => {
                        if (submitting.current || formRef.current?.processing) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                    }}
                    onBefore={() => {
                        if (submitting.current) return false;
                        submitting.current = true;
                    }}
                    onFinish={() => {
                        submitting.current = false;
                    }}
                >
                    {(review) => (
                        <DocumentBody
                            document={document}
                            navigation={navigation}
                            review={review}
                        />
                    )}
                </Form>
            ) : (
                <div className={contentClassName}>
                    <DocumentBody document={document} navigation={navigation} />
                </div>
            )}
        </Layout>
    );
}
