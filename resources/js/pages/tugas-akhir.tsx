import { Head, useForm, usePage } from '@inertiajs/react';
import {
    BookOpen,
    CalendarClock,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    Clock,
    Download,
    Eye,
    FileText,
    GraduationCap,
    Languages,
    MapPin,
    Pencil,
    Search,
    Star,
    Users,
    X,
} from 'lucide-react';
import { FormEventHandler, useMemo, useState } from 'react';

import { PersonCardLink } from '@/components/profile/person-card-link';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTableContainer } from '@/components/ui/data-table';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import {
    academicGradeClassName,
    calculateAverageAcademicScore,
    resolveAcademicGrade,
} from '@/lib/academic-grade';
import { cn } from '@/lib/utils';
import { dashboard, tugasAkhir } from '@/routes';
import {
    type BreadcrumbItem,
    type SharedData,
    type UserProfileSummary,
} from '@/types';

type Submission = {
    id: number;
    program_studi: string;
    title_id: string;
    title_en: string;
    proposal_summary: string;
    workflow: {
        key: string;
        label: string;
        description: string;
        can_edit: boolean;
    };
    proposal_file_name: string | null;
    proposal_file_view_url: string | null;
    proposal_file_download_url: string | null;
};

type AssignedLecturers = {
    pembimbing1: string | null;
    pembimbing2: string | null;
    penguji1: string | null;
    penguji2: string | null;
    ketuaSidang: string | null;
    sekretarisSidang: string | null;
    pengujiSidang: string | null;
};

type WorkspaceDocument = {
    id: number;
    title: string;
    category: string;
    fileName: string;
    version: string;
    uploadedAt: string | null;
    downloadUrl: string;
};

type DefenseSelection = {
    defenseId: number;
    isLocked: boolean;
    status: string;
    mainFileName: string | null;
    mainFileViewUrl: string | null;
    mainFileDownloadUrl: string | null;
    supportingDocuments: Array<{
        id: number;
        name: string;
        viewUrl: string;
        downloadUrl: string;
    }>;
};

type TugasAkhirPageProps = {
    submission: Submission | null;
    workspaceDocuments: WorkspaceDocument[];
    semproSelection: DefenseSelection | null;
    sidangSelection: DefenseSelection | null;
    assignedLecturers: AssignedLecturers;
    advisorProfiles: UserProfileSummary[];
    semproExaminerProfiles: UserProfileSummary[];
    sidangExaminerProfiles: UserProfileSummary[];
    semproDate: string | null;
    sidangDate: string | null;
    semproResult: {
        label: string;
        resultLabel: string;
        scheduledFor: string | null;
        location: string | null;
        examiners: Array<{
            id: number;
            name: string;
            roleLabel: string;
            decisionLabel: string;
            score: number | string | null;
            decisionNotes: string | null;
        }>;
    } | null;
    sidangResult: {
        label: string;
        resultLabel: string;
        scheduledFor: string | null;
        location: string | null;
        examiners: Array<{
            id: number;
            name: string;
            roleLabel: string;
            decisionLabel: string;
            score: number | string | null;
            decisionNotes: string | null;
        }>;
    } | null;
    defenseHistory: {
        sempro: Array<{
            id: number;
            attemptNo: number;
            statusLabel: string;
            resultLabel: string;
            scheduledFor: string | null;
            location: string | null;
            mode: string | null;
            officialNotes: string | null;
            titleId: string;
            titleEn: string | null;
            proposalSummary: string | null;
            primaryDocumentLabel: string;
            primaryDocumentName: string | null;
            primaryDocumentViewUrl: string | null;
            primaryDocumentDownloadUrl: string | null;
            supportingDocuments: Array<{
                id: number;
                name: string;
                viewUrl: string;
                downloadUrl: string;
            }>;
            examiners: Array<{
                id: number;
                name: string;
                roleLabel: string;
                decisionLabel: string;
                score: number | string | null;
                decisionNotes: string | null;
            }>;
            revisions: Array<{
                id: number;
                statusLabel: string;
                notes: string;
                requestedBy: string;
                dueAt: string | null;
                resolvedAt: string | null;
                resolutionNotes: string | null;
            }>;
        }>;
        sidang: Array<{
            id: number;
            attemptNo: number;
            statusLabel: string;
            resultLabel: string;
            scheduledFor: string | null;
            location: string | null;
            mode: string | null;
            officialNotes: string | null;
            titleId: string;
            titleEn: string | null;
            proposalSummary: string | null;
            primaryDocumentLabel: string;
            primaryDocumentName: string | null;
            primaryDocumentViewUrl: string | null;
            primaryDocumentDownloadUrl: string | null;
            supportingDocuments: Array<{
                id: number;
                name: string;
                viewUrl: string;
                downloadUrl: string;
            }>;
            examiners: Array<{
                id: number;
                name: string;
                roleLabel: string;
                decisionLabel: string;
                score: number | string | null;
                decisionNotes: string | null;
            }>;
            revisions: Array<{
                id: number;
                statusLabel: string;
                notes: string;
                requestedBy: string;
                dueAt: string | null;
                resolvedAt: string | null;
                resolutionNotes: string | null;
            }>;
        }>;
    };
    profileProgramStudi: string;
    flashMessage?: string | null;
    errorMessage?: string | null;
};

type FormData = {
    title_id: string;
    title_en: string;
    proposal_summary: string;
    proposal_file: File | null;
};

type DefenseDocumentFormData = {
    workspace_document_id: string;
    supporting_document_ids: string[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Tugas Akhir', href: tugasAkhir().url },
];

function normalizeTitleEn(value: string | null | undefined): string {
    if (value === null || value === '-' || value === undefined) return '';
    return value;
}

function submissionDefaults(submission: Submission | null): FormData {
    return {
        title_id: submission?.title_id ?? '',
        title_en: normalizeTitleEn(submission?.title_en),
        proposal_summary: submission?.proposal_summary ?? '',
        proposal_file: null,
    };
}

/* ─── Workflow status badge ─────────────────────────────────────── */
function WorkflowBadge({
    workflowKey,
    label,
}: {
    workflowKey: string;
    label: string;
}) {
    const isReview = workflowKey === 'title_review_pending';
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium',
                isReview
                    ? 'bg-amber-600/10 text-amber-700 dark:text-amber-400'
                    : 'bg-emerald-600/10 text-emerald-700 dark:text-emerald-400',
            )}
        >
            {isReview ? (
                <Clock className="size-3" />
            ) : (
                <CheckCircle2 className="size-3" />
            )}
            {label}
        </span>
    );
}

/* ─── Section header ────────────────────────────────────────────── */
function SectionHeader({
    title,
    description,
    action,
}: {
    title: string;
    description?: string;
    action?: React.ReactNode;
}) {
    return (
        <div className="flex items-start justify-between border-b bg-muted/20 px-5 py-3.5">
            <div>
                <p className="text-sm font-semibold">{title}</p>
                {description && (
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {action && <div className="shrink-0">{action}</div>}
        </div>
    );
}

/* ─── Submission form fields ────────────────────────────────────── */
function SubmissionFields({
    form,
    fileRequired,
    idPrefix,
}: {
    form: ReturnType<typeof useForm<FormData>>;
    fileRequired: boolean;
    idPrefix: string;
}) {
    return (
        <div className="space-y-5">
            <div className="grid gap-1.5">
                <Label htmlFor={`${idPrefix}title_id`}>
                    Judul Skripsi (Bahasa Indonesia)
                </Label>
                <Textarea
                    id={`${idPrefix}title_id`}
                    value={form.data.title_id}
                    onChange={(e) => form.setData('title_id', e.target.value)}
                    className="h-20 resize-none"
                    required
                />
                {form.errors.title_id && (
                    <p className="text-xs text-destructive">
                        {form.errors.title_id}
                    </p>
                )}
            </div>

            <div className="grid gap-1.5">
                <Label htmlFor={`${idPrefix}title_en`}>
                    Judul Skripsi (Bahasa Inggris)
                </Label>
                <Textarea
                    id={`${idPrefix}title_en`}
                    value={form.data.title_en}
                    onChange={(e) => form.setData('title_en', e.target.value)}
                    className="h-20 resize-none"
                />
                {form.errors.title_en && (
                    <p className="text-xs text-destructive">
                        {form.errors.title_en}
                    </p>
                )}
            </div>

            <div className="grid gap-1.5">
                <Label htmlFor={`${idPrefix}proposal_summary`}>
                    Ringkasan Proposal
                </Label>
                <Textarea
                    id={`${idPrefix}proposal_summary`}
                    value={form.data.proposal_summary}
                    onChange={(e) =>
                        form.setData('proposal_summary', e.target.value)
                    }
                    className="h-36 resize-none"
                    required
                />
                {form.errors.proposal_summary && (
                    <p className="text-xs text-destructive">
                        {form.errors.proposal_summary}
                    </p>
                )}
            </div>

            <div className="grid gap-1.5">
                <Label htmlFor={`${idPrefix}proposal_file`}>
                    {fileRequired
                        ? 'File Proposal (PDF)'
                        : 'Ganti File Proposal (Opsional)'}
                </Label>
                <Input
                    id={`${idPrefix}proposal_file`}
                    type="file"
                    accept=".pdf"
                    onChange={(e) =>
                        form.setData(
                            'proposal_file',
                            e.target.files?.[0] ?? null,
                        )
                    }
                    required={fileRequired}
                />
                {form.errors.proposal_file && (
                    <p className="text-xs text-destructive">
                        {form.errors.proposal_file}
                    </p>
                )}
            </div>
        </div>
    );
}

function DocumentSnapshotCard({
    label,
    fileName,
    viewUrl,
    downloadUrl,
}: {
    label: string;
    fileName: string | null;
    viewUrl: string | null;
    downloadUrl: string | null;
}) {
    if (!fileName) {
        return (
            <div className="rounded-lg border border-dashed px-3 py-2 text-xs text-muted-foreground">
                Belum ada {label.toLowerCase()} yang dipilih.
            </div>
        );
    }

    return (
        <div className="flex flex-wrap items-center gap-2.5 border-l-2 border-primary/30 pl-3">
            <div className="flex size-7 items-center justify-center rounded bg-primary/10 text-primary">
                <FileText className="size-3.5" />
            </div>
            <div className="min-w-0">
                <p className="text-[11px] font-semibold text-muted-foreground uppercase">
                    {label}
                </p>
                <p className="max-w-[240px] truncate text-xs font-medium">
                    {fileName}
                </p>
            </div>
            {viewUrl && (
                <a
                    href={viewUrl}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                >
                    <Eye className="size-3" />
                    Lihat
                </a>
            )}
            {downloadUrl && (
                <a
                    href={downloadUrl}
                    className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                >
                    <Download className="size-3" />
                    Unduh
                </a>
            )}
        </div>
    );
}

function DefenseDocumentSelectionSection({
    title,
    description,
    workspaceDocuments,
    selection,
    form,
    onSubmit,
    allowSupportingFiles,
}: {
    title: string;
    description: string;
    workspaceDocuments: WorkspaceDocument[];
    selection: DefenseSelection | null;
    form: ReturnType<typeof useForm<DefenseDocumentFormData>>;
    onSubmit: FormEventHandler;
    allowSupportingFiles: boolean;
}) {
    const [mainSearch, setMainSearch] = useState('');
    const [supportingSearch, setSupportingSearch] = useState('');
    const filteredMainOptions = useMemo(() => {
        const needle = mainSearch.trim().toLowerCase();

        if (needle === '') {
            return workspaceDocuments;
        }

        return workspaceDocuments.filter((document) =>
            [
                document.title,
                document.fileName,
                document.category,
                document.version,
            ].some((value) => value.toLowerCase().includes(needle)),
        );
    }, [mainSearch, workspaceDocuments]);
    const selectedMainDocument = workspaceDocuments.find(
        (document) =>
            document.id.toString() === form.data.workspace_document_id,
    );
    const supportingOptions = workspaceDocuments.filter(
        (document) =>
            document.id.toString() !== form.data.workspace_document_id,
    );
    const filteredSupportingOptions = useMemo(() => {
        const needle = supportingSearch.trim().toLowerCase();

        if (needle === '') {
            return supportingOptions;
        }

        return supportingOptions.filter((document) =>
            [
                document.title,
                document.fileName,
                document.category,
                document.version,
            ].some((value) => value.toLowerCase().includes(needle)),
        );
    }, [supportingOptions, supportingSearch]);
    const selectedSupportingDocuments = supportingOptions.filter((document) =>
        form.data.supporting_document_ids.includes(document.id.toString()),
    );

    if (selection === null) {
        return null;
    }

    return (
        <section>
            <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                <SectionHeader title={title} description={description} />
                <div className="space-y-4 px-5 py-4">
                    <DocumentSnapshotCard
                        label={
                            allowSupportingFiles
                                ? 'Naskah akhir aktif'
                                : 'Proposal aktif'
                        }
                        fileName={selection.mainFileName}
                        viewUrl={selection.mainFileViewUrl}
                        downloadUrl={selection.mainFileDownloadUrl}
                    />

                    {allowSupportingFiles &&
                        selection.supportingDocuments.length > 0 && (
                            <div className="space-y-2 border-t pt-3">
                                <p className="mb-2 text-xs font-semibold text-muted-foreground uppercase">
                                    Lampiran aktif
                                </p>
                                <div className="space-y-2">
                                    {selection.supportingDocuments.map(
                                        (document) => (
                                            <DocumentSnapshotCard
                                                key={document.id}
                                                label="Lampiran"
                                                fileName={document.name}
                                                viewUrl={document.viewUrl}
                                                downloadUrl={
                                                    document.downloadUrl
                                                }
                                            />
                                        ),
                                    )}
                                </div>
                            </div>
                        )}

                    {selection.isLocked ? (
                        <div className="rounded-lg border border-amber-200 bg-amber-50/70 px-3 py-2 text-xs text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-400">
                            Dokumen untuk tahap ini sudah dikunci karena hasil
                            resmi telah ditetapkan.
                        </div>
                    ) : workspaceDocuments.length === 0 ? (
                        <div className="rounded-lg border border-dashed px-3 py-3 text-sm text-muted-foreground">
                            Belum ada file di workspace. Unggah file lebih dulu
                            dari halaman{' '}
                            <a
                                href="/mahasiswa/upload-dokumen"
                                className="font-medium text-primary hover:underline"
                            >
                                Upload Dokumen
                            </a>
                            .
                        </div>
                    ) : (
                        <form
                            onSubmit={onSubmit}
                            className="space-y-4 border-t pt-4"
                        >
                            <div className="grid gap-2">
                                <Label>
                                    {allowSupportingFiles
                                        ? 'Pilih naskah akhir'
                                        : 'Pilih file proposal'}
                                </Label>
                                <div className="space-y-3">
                                    <div className="flex min-h-11 items-center rounded-md border px-3 py-2">
                                        {selectedMainDocument ? (
                                            <Badge
                                                variant="secondary"
                                                className="gap-1 pr-1"
                                            >
                                                <span className="max-w-[240px] truncate">
                                                    {
                                                        selectedMainDocument.fileName
                                                    }
                                                </span>
                                                <button
                                                    type="button"
                                                    className="rounded-sm p-0.5 hover:bg-black/10"
                                                    onClick={() => {
                                                        form.setData(
                                                            'workspace_document_id',
                                                            '',
                                                        );
                                                    }}
                                                >
                                                    <X className="size-3" />
                                                </button>
                                            </Badge>
                                        ) : (
                                            <span className="text-sm text-muted-foreground">
                                                Belum ada file utama dipilih.
                                            </span>
                                        )}
                                    </div>

                                    <div className="relative">
                                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            value={mainSearch}
                                            onChange={(event) =>
                                                setMainSearch(
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Cari file utama..."
                                            className="pl-9"
                                        />
                                    </div>

                                    <div className="max-h-56 overflow-y-auto rounded-md border">
                                        {filteredMainOptions.length > 0 ? (
                                            filteredMainOptions.map(
                                                (document) => {
                                                    const value =
                                                        document.id.toString();
                                                    const selected =
                                                        form.data
                                                            .workspace_document_id ===
                                                        value;

                                                    return (
                                                        <button
                                                            key={document.id}
                                                            type="button"
                                                            className="flex w-full items-start justify-between gap-3 border-b px-3 py-2 text-left text-sm last:border-b-0 hover:bg-muted/20"
                                                            onClick={() => {
                                                                form.setData(
                                                                    'workspace_document_id',
                                                                    value,
                                                                );
                                                            }}
                                                        >
                                                            <div className="min-w-0">
                                                                <p className="truncate font-medium">
                                                                    {
                                                                        document.title
                                                                    }
                                                                </p>
                                                                <p className="truncate text-xs text-muted-foreground">
                                                                    {
                                                                        document.fileName
                                                                    }{' '}
                                                                    ·{' '}
                                                                    {
                                                                        document.category
                                                                    }{' '}
                                                                    ·{' '}
                                                                    {
                                                                        document.version
                                                                    }
                                                                </p>
                                                            </div>
                                                            {selected && (
                                                                <Badge className="shrink-0">
                                                                    Dipilih
                                                                </Badge>
                                                            )}
                                                        </button>
                                                    );
                                                },
                                            )
                                        ) : (
                                            <div className="px-3 py-3 text-sm text-muted-foreground">
                                                Tidak ada file yang cocok dengan
                                                pencarian.
                                            </div>
                                        )}
                                    </div>
                                </div>
                                {form.errors.workspace_document_id && (
                                    <p className="text-xs text-destructive">
                                        {form.errors.workspace_document_id}
                                    </p>
                                )}
                            </div>

                            {allowSupportingFiles &&
                                supportingOptions.length > 0 && (
                                    <div className="grid gap-2">
                                        <Label>Lampiran pendukung sidang</Label>
                                        <div className="space-y-3">
                                            <div className="flex min-h-11 flex-wrap gap-2 rounded-md border px-3 py-2">
                                                {selectedSupportingDocuments.length >
                                                0 ? (
                                                    selectedSupportingDocuments.map(
                                                        (document) => (
                                                            <Badge
                                                                key={
                                                                    document.id
                                                                }
                                                                variant="secondary"
                                                                className="gap-1 pr-1"
                                                            >
                                                                <span className="max-w-[180px] truncate">
                                                                    {
                                                                        document.fileName
                                                                    }
                                                                </span>
                                                                <button
                                                                    type="button"
                                                                    className="rounded-sm p-0.5 hover:bg-black/10"
                                                                    onClick={() => {
                                                                        form.setData(
                                                                            'supporting_document_ids',
                                                                            form.data.supporting_document_ids.filter(
                                                                                (
                                                                                    item,
                                                                                ) =>
                                                                                    item !==
                                                                                    document.id.toString(),
                                                                            ),
                                                                        );
                                                                    }}
                                                                >
                                                                    <X className="size-3" />
                                                                </button>
                                                            </Badge>
                                                        ),
                                                    )
                                                ) : (
                                                    <span className="text-sm text-muted-foreground">
                                                        Belum ada lampiran
                                                        dipilih.
                                                    </span>
                                                )}
                                            </div>

                                            <div className="relative">
                                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                                <Input
                                                    value={supportingSearch}
                                                    onChange={(event) =>
                                                        setSupportingSearch(
                                                            event.target.value,
                                                        )
                                                    }
                                                    placeholder="Cari file lampiran..."
                                                    className="pl-9"
                                                />
                                            </div>

                                            <div className="max-h-56 overflow-y-auto rounded-md border">
                                                {filteredSupportingOptions.length >
                                                0 ? (
                                                    filteredSupportingOptions.map(
                                                        (document) => {
                                                            const value =
                                                                document.id.toString();
                                                            const selected =
                                                                form.data.supporting_document_ids.includes(
                                                                    value,
                                                                );

                                                            return (
                                                                <button
                                                                    key={
                                                                        document.id
                                                                    }
                                                                    type="button"
                                                                    className="flex w-full items-start justify-between gap-3 border-b px-3 py-2 text-left text-sm last:border-b-0 hover:bg-muted/20"
                                                                    onClick={() => {
                                                                        form.setData(
                                                                            'supporting_document_ids',
                                                                            selected
                                                                                ? form.data.supporting_document_ids.filter(
                                                                                      (
                                                                                          item,
                                                                                      ) =>
                                                                                          item !==
                                                                                          value,
                                                                                  )
                                                                                : [
                                                                                      ...form
                                                                                          .data
                                                                                          .supporting_document_ids,
                                                                                      value,
                                                                                  ],
                                                                        );
                                                                    }}
                                                                >
                                                                    <div className="min-w-0">
                                                                        <p className="truncate font-medium">
                                                                            {
                                                                                document.title
                                                                            }
                                                                        </p>
                                                                        <p className="truncate text-xs text-muted-foreground">
                                                                            {
                                                                                document.fileName
                                                                            }{' '}
                                                                            ·{' '}
                                                                            {
                                                                                document.category
                                                                            }{' '}
                                                                            ·{' '}
                                                                            {
                                                                                document.version
                                                                            }
                                                                        </p>
                                                                    </div>
                                                                    {selected && (
                                                                        <Badge className="shrink-0">
                                                                            Dipilih
                                                                        </Badge>
                                                                    )}
                                                                </button>
                                                            );
                                                        },
                                                    )
                                                ) : (
                                                    <div className="px-3 py-3 text-sm text-muted-foreground">
                                                        Tidak ada file yang
                                                        cocok dengan pencarian.
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                )}

                            <div className="flex flex-wrap items-center gap-2">
                                <Button
                                    type="submit"
                                    disabled={
                                        form.processing ||
                                        form.data.workspace_document_id === ''
                                    }
                                >
                                    Simpan Dokumen
                                </Button>
                                <a
                                    href="/mahasiswa/upload-dokumen"
                                    className="text-sm font-medium text-primary hover:underline"
                                >
                                    Kelola file workspace
                                </a>
                            </div>
                        </form>
                    )}
                </div>
            </div>
        </section>
    );
}

/* ─── Defense result table ──────────────────────────────────────── */
function DefenseResultSection({
    title,
    result,
}: {
    title: string;
    result: NonNullable<TugasAkhirPageProps['semproResult']>;
}) {
    const averageScore = calculateAverageAcademicScore(
        result.examiners.map((e) => e.score),
    );
    const finalGrade = resolveAcademicGrade(averageScore);

    return (
        <section>
            <div className="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 className="text-base font-semibold">{title}</h2>
                    <p className="text-sm text-muted-foreground">
                        {result.label}
                        {result.scheduledFor ? ` · ${result.scheduledFor}` : ''}
                        {result.location ? ` · ${result.location}` : ''}
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge className="bg-emerald-600 text-white hover:bg-emerald-600/90 dark:bg-emerald-500">
                        {result.resultLabel}
                    </Badge>
                    {averageScore !== null && (
                        <Badge variant="secondary">
                            <Star className="mr-1 size-3 text-amber-500" />
                            {averageScore.toFixed(2)}
                        </Badge>
                    )}
                    {finalGrade && (
                        <Badge
                            variant="soft"
                            className={academicGradeClassName(finalGrade)}
                        >
                            Grade {finalGrade}
                        </Badge>
                    )}
                </div>
            </div>

            <DataTableContainer>
                <table className="w-full min-w-[600px] text-sm">
                    <thead>
                        <tr className="border-b bg-muted/30">
                            <th className="px-5 py-2.5 text-left text-xs font-medium text-muted-foreground">
                                Penguji
                            </th>
                            <th className="px-5 py-2.5 text-left text-xs font-medium text-muted-foreground">
                                Peran
                            </th>
                            <th className="px-5 py-2.5 text-left text-xs font-medium text-muted-foreground">
                                Keputusan
                            </th>
                            <th className="px-5 py-2.5 text-right text-xs font-medium text-muted-foreground">
                                Nilai
                            </th>
                            <th className="px-5 py-2.5 text-left text-xs font-medium text-muted-foreground">
                                Catatan
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {result.examiners.map((examiner) => (
                            <tr
                                key={examiner.id}
                                className="transition-colors hover:bg-muted/20"
                            >
                                <td className="px-5 py-3 font-medium">
                                    {examiner.name}
                                </td>
                                <td className="px-5 py-3 text-xs text-muted-foreground">
                                    {examiner.roleLabel}
                                </td>
                                <td className="px-5 py-3">
                                    <Badge
                                        variant="outline"
                                        className="text-xs"
                                    >
                                        {examiner.decisionLabel}
                                    </Badge>
                                </td>
                                <td className="px-5 py-3 text-right">
                                    {examiner.score !== null ? (
                                        <span className="inline-flex items-center gap-1 text-sm font-semibold">
                                            <Star className="size-3 text-amber-500" />
                                            {examiner.score}
                                        </span>
                                    ) : (
                                        <span className="text-xs text-muted-foreground/50 italic">
                                            —
                                        </span>
                                    )}
                                </td>
                                <td className="px-5 py-3 text-xs text-muted-foreground">
                                    {examiner.decisionNotes ?? (
                                        <span className="italic opacity-50">
                                            Belum ada catatan
                                        </span>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </DataTableContainer>
        </section>
    );
}

/* ─── Defense history expandable row ───────────────────────────── */
function DefenseHistoryRow({
    item,
    type,
}: {
    item: TugasAkhirPageProps['defenseHistory']['sempro'][number];
    type: 'sempro' | 'sidang';
}) {
    const [expanded, setExpanded] = useState(false);
    const averageScore = calculateAverageAcademicScore(
        item.examiners.map((e) => e.score),
    );
    const finalGrade = resolveAcademicGrade(averageScore);
    const hasRevisions = item.revisions.length > 0;
    const pendingRevisions = item.revisions.filter((r) => !r.resolvedAt).length;

    return (
        <>
            {/* ── Summary row ── */}
            <tr
                className={cn(
                    'cursor-pointer transition-colors select-none hover:bg-muted/30',
                    expanded && 'bg-muted/20',
                )}
                onClick={() => setExpanded((v) => !v)}
            >
                {/* Tipe & attempt */}
                <td className="px-5 py-3">
                    <span
                        className={cn(
                            'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold',
                            type === 'sempro'
                                ? 'bg-cyan-500/10 text-cyan-700 dark:text-cyan-400'
                                : 'bg-purple-500/10 text-purple-700 dark:text-purple-400',
                        )}
                    >
                        {type === 'sempro' ? 'Sempro' : 'Sidang'} #
                        {item.attemptNo}
                    </span>
                </td>

                {/* Judul */}
                <td className="px-5 py-3">
                    <p className="line-clamp-1 max-w-xs text-sm font-medium">
                        {item.titleId}
                    </p>
                    {item.titleEn && (
                        <p className="line-clamp-1 max-w-xs text-xs text-muted-foreground italic">
                            {item.titleEn}
                        </p>
                    )}
                </td>

                {/* Jadwal */}
                <td className="px-5 py-3">
                    {item.scheduledFor ? (
                        <span className="flex items-center gap-1.5 text-xs whitespace-nowrap text-muted-foreground">
                            <CalendarClock className="size-3 shrink-0" />
                            {item.scheduledFor}
                        </span>
                    ) : (
                        <span className="text-xs text-muted-foreground/50 italic">
                            —
                        </span>
                    )}
                    {item.location && (
                        <span className="mt-0.5 flex items-center gap-1 text-xs text-muted-foreground">
                            <MapPin className="size-3 shrink-0" />
                            {item.location}
                            {item.mode ? ` · ${item.mode}` : ''}
                        </span>
                    )}
                </td>

                {/* Status */}
                <td className="px-5 py-3">
                    <Badge variant="outline" className="text-xs">
                        {item.statusLabel}
                    </Badge>
                </td>

                {/* Hasil & nilai */}
                <td className="px-5 py-3">
                    <div className="flex flex-wrap items-center gap-1.5">
                        <Badge className="bg-primary text-xs text-primary-foreground hover:bg-primary/90">
                            {item.resultLabel}
                        </Badge>
                        {averageScore !== null && (
                            <span className="inline-flex items-center gap-1 text-xs font-semibold">
                                <Star className="size-3 text-amber-500" />
                                {averageScore.toFixed(2)}
                            </span>
                        )}
                        {finalGrade && (
                            <Badge
                                variant="soft"
                                className={cn(
                                    'text-xs',
                                    academicGradeClassName(finalGrade),
                                )}
                            >
                                {finalGrade}
                            </Badge>
                        )}
                        {hasRevisions && (
                            <span
                                className={cn(
                                    'inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[10px] font-bold',
                                    pendingRevisions > 0
                                        ? 'bg-amber-600/10 text-amber-700 dark:text-amber-400'
                                        : 'bg-muted text-muted-foreground',
                                )}
                            >
                                {item.revisions.length} revisi
                            </span>
                        )}
                    </div>
                </td>

                {/* Expand toggle */}
                <td className="w-8 px-4 py-3 text-center">
                    {expanded ? (
                        <ChevronUp className="size-4 text-muted-foreground" />
                    ) : (
                        <ChevronDown className="size-4 text-muted-foreground" />
                    )}
                </td>
            </tr>

            {/* ── Expanded detail panel ── */}
            {expanded && (
                <tr>
                    <td colSpan={6} className="p-0">
                        <div className="space-y-5 border-t border-dashed bg-muted/5 px-5 py-5">
                            {/* Penilaian Penguji */}
                            {item.examiners.length > 0 && (
                                <div>
                                    <p className="mb-2.5 flex items-center gap-1.5 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        <Star className="size-3" />
                                        Penilaian Penguji
                                    </p>
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        {item.examiners.map((ex) => (
                                            <div
                                                key={ex.id}
                                                className="flex flex-col gap-1.5 rounded-lg border bg-card px-4 py-3"
                                            >
                                                <div className="flex items-start justify-between gap-2">
                                                    <div>
                                                        <p className="text-sm leading-snug font-semibold">
                                                            {ex.name}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {ex.roleLabel}
                                                        </p>
                                                    </div>
                                                    <div className="flex shrink-0 items-center gap-1.5">
                                                        {ex.score !== null && (
                                                            <span className="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-bold text-amber-700 dark:text-amber-400">
                                                                <Star className="size-3" />
                                                                {ex.score}
                                                            </span>
                                                        )}
                                                        <Badge
                                                            variant="outline"
                                                            className="text-xs"
                                                        >
                                                            {ex.decisionLabel}
                                                        </Badge>
                                                    </div>
                                                </div>
                                                {ex.decisionNotes && (
                                                    <p className="rounded-md bg-muted/40 px-3 py-2 text-xs leading-relaxed text-muted-foreground">
                                                        {ex.decisionNotes}
                                                    </p>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Catatan Revisi */}
                            {hasRevisions && (
                                <div>
                                    <p className="mb-2.5 flex items-center gap-1.5 text-xs font-semibold tracking-wider text-amber-700 uppercase dark:text-amber-400">
                                        <svg
                                            aria-hidden="true"
                                            className="size-3"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth="2"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        >
                                            <path d="M12 9v4M12 17h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                                        </svg>
                                        Catatan Revisi ({item.revisions.length})
                                    </p>
                                    <div className="space-y-2">
                                        {item.revisions.map((rev) => {
                                            const isDone = !!rev.resolvedAt;
                                            return (
                                                <div
                                                    key={rev.id}
                                                    className={cn(
                                                        'rounded-lg border px-4 py-3',
                                                        isDone
                                                            ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-900/40 dark:bg-emerald-950/10'
                                                            : 'border-amber-200 bg-amber-50 dark:border-amber-900/40 dark:bg-amber-950/20',
                                                    )}
                                                >
                                                    <div className="mb-2 flex flex-wrap items-center gap-2">
                                                        <Badge
                                                            variant="outline"
                                                            className={cn(
                                                                'text-xs',
                                                                isDone
                                                                    ? 'border-emerald-300 text-emerald-700 dark:text-emerald-400'
                                                                    : '',
                                                            )}
                                                        >
                                                            {rev.statusLabel}
                                                        </Badge>
                                                        <span
                                                            className={cn(
                                                                'text-xs font-medium',
                                                                isDone
                                                                    ? 'text-emerald-700 dark:text-emerald-300'
                                                                    : 'text-amber-700 dark:text-amber-300',
                                                            )}
                                                        >
                                                            {rev.requestedBy}
                                                        </span>
                                                        {rev.dueAt &&
                                                            !isDone && (
                                                                <span className="text-xs text-amber-600 dark:text-amber-400">
                                                                    · Batas:{' '}
                                                                    {rev.dueAt}
                                                                </span>
                                                            )}
                                                        {rev.resolvedAt && (
                                                            <span className="flex items-center gap-1 text-xs text-emerald-600 dark:text-emerald-400">
                                                                <CheckCircle2 className="size-3" />
                                                                Selesai:{' '}
                                                                {rev.resolvedAt}
                                                            </span>
                                                        )}
                                                    </div>

                                                    <p
                                                        className={cn(
                                                            'text-xs leading-relaxed',
                                                            isDone
                                                                ? 'text-emerald-900 dark:text-emerald-100'
                                                                : 'text-amber-900 dark:text-amber-100',
                                                        )}
                                                    >
                                                        {rev.notes}
                                                    </p>

                                                    {rev.resolutionNotes && (
                                                        <div className="mt-2 rounded border border-emerald-200 bg-white/60 px-3 py-1.5 text-xs text-emerald-700 dark:border-emerald-800 dark:bg-transparent dark:text-emerald-300">
                                                            <span className="font-medium">
                                                                Penyelesaian:
                                                            </span>{' '}
                                                            {
                                                                rev.resolutionNotes
                                                            }
                                                        </div>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}

                            {/* File & catatan admin */}
                            <div className="flex flex-wrap items-center gap-3">
                                {item.primaryDocumentName && (
                                    <div className="flex items-center gap-2.5 rounded-lg border bg-card px-3 py-2">
                                        <div className="flex size-7 items-center justify-center rounded bg-primary/10 text-primary">
                                            <FileText className="size-3.5" />
                                        </div>
                                        <div className="min-w-0">
                                            <p className="text-[11px] font-semibold text-muted-foreground uppercase">
                                                {item.primaryDocumentLabel}
                                            </p>
                                            <p className="max-w-[200px] truncate text-xs font-medium">
                                                {item.primaryDocumentName}
                                            </p>
                                        </div>
                                        {item.primaryDocumentViewUrl && (
                                            <a
                                                href={
                                                    item.primaryDocumentViewUrl
                                                }
                                                target="_blank"
                                                rel="noreferrer"
                                                className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                                                onClick={(e) =>
                                                    e.stopPropagation()
                                                }
                                            >
                                                <Eye className="size-3" />
                                                Lihat
                                            </a>
                                        )}
                                        {item.primaryDocumentDownloadUrl && (
                                            <a
                                                href={
                                                    item.primaryDocumentDownloadUrl
                                                }
                                                className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                                                onClick={(e) =>
                                                    e.stopPropagation()
                                                }
                                            >
                                                <Download className="size-3" />
                                                Unduh
                                            </a>
                                        )}
                                    </div>
                                )}
                                {item.supportingDocuments.map((document) => (
                                    <div
                                        key={document.id}
                                        className="flex items-center gap-2.5 rounded-lg border bg-card px-3 py-2"
                                    >
                                        <div className="flex size-7 items-center justify-center rounded bg-primary/10 text-primary">
                                            <FileText className="size-3.5" />
                                        </div>
                                        <div className="min-w-0">
                                            <p className="text-[11px] font-semibold text-muted-foreground uppercase">
                                                Lampiran
                                            </p>
                                            <p className="max-w-[200px] truncate text-xs font-medium">
                                                {document.name}
                                            </p>
                                        </div>
                                        <a
                                            href={document.viewUrl}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                                            onClick={(e) => e.stopPropagation()}
                                        >
                                            <Eye className="size-3" />
                                            Lihat
                                        </a>
                                        <a
                                            href={document.downloadUrl}
                                            className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                                            onClick={(e) => e.stopPropagation()}
                                        >
                                            <Download className="size-3" />
                                            Unduh
                                        </a>
                                    </div>
                                ))}
                                {item.officialNotes && (
                                    <p className="text-xs text-muted-foreground">
                                        <span className="font-medium text-foreground">
                                            Catatan admin:
                                        </span>{' '}
                                        {item.officialNotes}
                                    </p>
                                )}
                            </div>
                        </div>
                    </td>
                </tr>
            )}
        </>
    );
}

/* ─── Defense history table section ────────────────────────────── */
function DefenseHistorySection({
    title,
    items,
    type,
}: {
    title: string;
    items: TugasAkhirPageProps['defenseHistory']['sempro'];
    type: 'sempro' | 'sidang';
}) {
    if (items.length === 0) return null;

    return (
        <section>
            <div className="mb-3">
                <h2 className="text-base font-semibold">{title}</h2>
                <p className="text-sm text-muted-foreground">
                    Klik baris untuk melihat detail, nilai, dan catatan revisi.
                </p>
            </div>

            <DataTableContainer>
                <table className="w-full min-w-[700px] text-sm">
                    <thead>
                        <tr className="border-b bg-muted/30">
                            <th className="px-5 py-2.5 text-left text-xs font-medium text-muted-foreground">
                                Tipe
                            </th>
                            <th className="px-5 py-2.5 text-left text-xs font-medium text-muted-foreground">
                                Judul
                            </th>
                            <th className="px-5 py-2.5 text-left text-xs font-medium text-muted-foreground">
                                Jadwal & Lokasi
                            </th>
                            <th className="px-5 py-2.5 text-left text-xs font-medium text-muted-foreground">
                                Status
                            </th>
                            <th className="px-5 py-2.5 text-left text-xs font-medium text-muted-foreground">
                                Hasil & Nilai
                            </th>
                            <th className="w-8 px-4 py-2.5" />
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {items.map((item) => (
                            <DefenseHistoryRow
                                key={item.id}
                                item={item}
                                type={type}
                            />
                        ))}
                    </tbody>
                </table>
            </DataTableContainer>
        </section>
    );
}

/* ─── Main page ─────────────────────────────────────────────────── */
export default function TugasAkhirSaya() {
    const {
        submission,
        workspaceDocuments,
        semproSelection,
        sidangSelection,
        advisorProfiles,
        semproExaminerProfiles,
        sidangExaminerProfiles,
        semproResult,
        sidangResult,
        defenseHistory,
        flashMessage,
        errorMessage,
    } = usePage<SharedData & TugasAkhirPageProps>().props;

    const [isEditing, setIsEditing] = useState(false);
    const form = useForm<FormData>(submissionDefaults(submission));
    const semproForm = useForm<DefenseDocumentFormData>({
        workspace_document_id: '',
        supporting_document_ids: [],
    });
    const sidangForm = useForm<DefenseDocumentFormData>({
        workspace_document_id: '',
        supporting_document_ids: [],
    });

    const canEditSubmission = submission?.workflow.can_edit ?? false;
    const label = submission?.workflow.label ?? '';
    const description = submission?.workflow.description ?? '';

    const createSubmission: FormEventHandler = (event) => {
        event.preventDefault();
        form.post('/mahasiswa/tugas-akhir', {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    const updateSubmission: FormEventHandler = (event) => {
        event.preventDefault();
        if (submission === null) return;
        form.transform((data) => ({ ...data, _method: 'patch' }));
        form.post(`/mahasiswa/tugas-akhir/${submission.id}`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setIsEditing(false);
                form.setData('proposal_file', null);
            },
        });
    };

    const updateSemproDocuments: FormEventHandler = (event) => {
        event.preventDefault();
        if (submission === null) return;

        semproForm.patch(
            `/mahasiswa/tugas-akhir/${submission.id}/sempro-documents`,
            {
                preserveScroll: true,
            },
        );
    };

    const updateSidangDocuments: FormEventHandler = (event) => {
        event.preventDefault();
        if (submission === null) return;

        sidangForm.patch(
            `/mahasiswa/tugas-akhir/${submission.id}/sidang-documents`,
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Tugas Akhir"
            subtitle="Judul, proposal, seminar, dan sidang skripsi Anda"
        >
            <Head title="Tugas Akhir" />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-8 px-4 py-6 md:px-6">
                {/* Flash / Error */}
                {(flashMessage || errorMessage) && (
                    <Alert variant={errorMessage ? 'destructive' : 'default'}>
                        <AlertDescription>
                            {errorMessage || flashMessage}
                        </AlertDescription>
                    </Alert>
                )}

                {/* ── No submission yet ── */}
                {submission === null && (
                    <section>
                        <div className="mb-3">
                            <h2 className="text-base font-semibold">
                                Ajukan Judul & Proposal
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Isi formulir di bawah ini untuk memulai
                                pengajuan skripsi Anda.
                            </p>
                        </div>
                        <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                            <div className="px-5 py-5">
                                <form
                                    onSubmit={createSubmission}
                                    className="space-y-5"
                                >
                                    <SubmissionFields
                                        form={form}
                                        fileRequired
                                        idPrefix="create_"
                                    />
                                    <Button
                                        type="submit"
                                        disabled={form.processing}
                                    >
                                        <BookOpen className="size-4" />
                                        Ajukan Sekarang
                                    </Button>
                                </form>
                            </div>
                        </div>
                    </section>
                )}

                {/* ── Has submission ── */}
                {submission !== null && (
                    <>
                        {/* ── Status pengajuan ── */}
                        <section>
                            <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                                <SectionHeader
                                    title="Status Pengajuan"
                                    description="Perkembangan pengajuan Anda saat ini"
                                    action={
                                        <WorkflowBadge
                                            workflowKey={
                                                submission.workflow.key
                                            }
                                            label={label}
                                        />
                                    }
                                />
                                <div className="flex items-start gap-3 px-5 py-3.5 text-sm">
                                    {submission.workflow.key ===
                                    'title_review_pending' ? (
                                        <Clock className="mt-0.5 size-4 shrink-0 text-amber-600" />
                                    ) : (
                                        <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                                    )}
                                    <p className="text-muted-foreground">
                                        {description}
                                    </p>
                                </div>
                            </div>
                        </section>

                        {/* ── Informasi Judul ── */}
                        <section>
                            <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                                <SectionHeader
                                    title="Informasi Judul"
                                    description="Judul dan ringkasan proposal skripsi terbaru"
                                    action={
                                        !isEditing ? (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                disabled={!canEditSubmission}
                                                onClick={() =>
                                                    setIsEditing(true)
                                                }
                                            >
                                                <Pencil className="size-3.5" />
                                                Edit
                                            </Button>
                                        ) : undefined
                                    }
                                />

                                {!canEditSubmission && !isEditing && (
                                    <div className="border-b bg-amber-50/60 px-5 py-2 text-xs text-amber-700 dark:bg-amber-950/20 dark:text-amber-400">
                                        Pengajuan yang sudah diproses tidak bisa
                                        diedit. Hubungi admin jika perlu
                                        perubahan.
                                    </div>
                                )}

                                {isEditing ? (
                                    <div className="px-5 py-5">
                                        <form
                                            onSubmit={updateSubmission}
                                            className="space-y-5"
                                        >
                                            <SubmissionFields
                                                form={form}
                                                fileRequired={false}
                                                idPrefix="edit_"
                                            />
                                            <div className="flex flex-wrap gap-2">
                                                <Button
                                                    type="submit"
                                                    disabled={form.processing}
                                                >
                                                    Simpan Perubahan
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={() => {
                                                        form.setData(
                                                            submissionDefaults(
                                                                submission,
                                                            ),
                                                        );
                                                        setIsEditing(false);
                                                    }}
                                                >
                                                    <X className="size-3.5" />
                                                    Batal
                                                </Button>
                                            </div>
                                        </form>
                                    </div>
                                ) : (
                                    <div className="space-y-4 px-5 py-4">
                                        {/* Judul Indonesia */}
                                        <div className="rounded-lg border bg-muted/10 p-4">
                                            <div className="mb-1.5 flex items-center gap-1.5 text-[10px] font-semibold tracking-widest text-muted-foreground uppercase">
                                                <GraduationCap className="size-3" />
                                                Judul (Bahasa Indonesia)
                                            </div>
                                            <p className="text-sm leading-relaxed font-semibold text-foreground">
                                                {submission.title_id}
                                            </p>
                                        </div>

                                        {/* Judul Inggris */}
                                        {submission.title_en && (
                                            <div className="rounded-lg border bg-muted/10 p-4">
                                                <div className="mb-1.5 flex items-center gap-1.5 text-[10px] font-semibold tracking-widest text-muted-foreground uppercase">
                                                    <Languages className="size-3" />
                                                    Judul (Bahasa Inggris)
                                                </div>
                                                <p className="text-sm leading-relaxed text-muted-foreground italic">
                                                    {submission.title_en}
                                                </p>
                                            </div>
                                        )}

                                        {/* Ringkasan */}
                                        <div className="rounded-lg border bg-muted/10 p-4">
                                            <div className="mb-2 flex items-center gap-1.5 text-[10px] font-semibold tracking-widest text-muted-foreground uppercase">
                                                <FileText className="size-3" />
                                                Ringkasan Proposal
                                            </div>
                                            <p className="text-sm leading-relaxed whitespace-pre-wrap text-muted-foreground">
                                                {submission.proposal_summary}
                                            </p>
                                        </div>

                                        {/* Program studi chip */}
                                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                            <span>Program Studi:</span>
                                            <Badge variant="secondary">
                                                {submission.program_studi}
                                            </Badge>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </section>

                        {/* ── File proposal ── */}
                        {(submission.proposal_file_download_url ||
                            submission.proposal_file_view_url) && (
                            <section>
                                <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                                    <SectionHeader
                                        title="File Proposal Terkirim"
                                        description="Anda dapat melihat dan mengunduh ulang file proposal"
                                    />
                                    <div className="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                        <div className="flex items-center gap-3">
                                            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                <FileText className="size-4" />
                                            </div>
                                            <div>
                                                <p className="text-sm font-medium">
                                                    {submission.proposal_file_name ??
                                                        'Proposal.pdf'}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    Dokumen proposal yang
                                                    tersimpan
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex gap-2">
                                            {submission.proposal_file_view_url && (
                                                <Button
                                                    asChild
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <a
                                                        href={
                                                            submission.proposal_file_view_url
                                                        }
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        <Eye className="size-3.5" />
                                                        Lihat
                                                    </a>
                                                </Button>
                                            )}
                                            {submission.proposal_file_download_url && (
                                                <Button
                                                    asChild
                                                    type="button"
                                                    size="sm"
                                                >
                                                    <a
                                                        href={
                                                            submission.proposal_file_download_url
                                                        }
                                                    >
                                                        <Download className="size-3.5" />
                                                        Unduh
                                                    </a>
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </section>
                        )}

                        <DefenseDocumentSelectionSection
                            title="Dokumen Seminar Proposal"
                            description="Pilih file dari workspace untuk dijadikan snapshot resmi pada sempro aktif. File bisa diganti sampai hasil sempro ditetapkan."
                            workspaceDocuments={workspaceDocuments}
                            selection={semproSelection}
                            form={semproForm}
                            onSubmit={updateSemproDocuments}
                            allowSupportingFiles={false}
                        />

                        <DefenseDocumentSelectionSection
                            title="Dokumen Sidang"
                            description="Pilih naskah akhir utama dan lampiran pendukung dari workspace. Snapshot ini dipakai untuk sidang aktif dan bisa diperbarui sampai hasil sidang ditetapkan."
                            workspaceDocuments={workspaceDocuments}
                            selection={sidangSelection}
                            form={sidangForm}
                            onSubmit={updateSidangDocuments}
                            allowSupportingFiles
                        />

                        {/* ── Dosen Pembimbing & Penguji ── */}
                        <section>
                            <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                                <SectionHeader
                                    title="Dosen Pembimbing & Penguji"
                                    description="Dosen yang terlibat pada sempro dan sidang skripsi Anda"
                                />
                                <div className="grid gap-6 p-5 xl:grid-cols-3">
                                    {/* Pembimbing */}
                                    <div className="space-y-3">
                                        <div>
                                            <p className="flex items-center gap-1.5 text-sm font-semibold">
                                                <GraduationCap className="size-4 text-primary" />
                                                Dosen Pembimbing
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Pembimbing aktif skripsi
                                            </p>
                                        </div>
                                        {advisorProfiles.length > 0 ? (
                                            <div className="grid gap-3">
                                                {advisorProfiles.map(
                                                    (person, index) => (
                                                        <PersonCardLink
                                                            key={person.id}
                                                            person={person}
                                                            label={`Pembimbing ${index + 1}`}
                                                        />
                                                    ),
                                                )}
                                            </div>
                                        ) : (
                                            <div className="rounded-xl border border-dashed p-4 text-sm text-muted-foreground">
                                                Belum ada dosen pembimbing
                                                aktif.
                                            </div>
                                        )}
                                    </div>

                                    {/* Penguji Sempro */}
                                    <div className="space-y-3">
                                        <div>
                                            <p className="flex items-center gap-1.5 text-sm font-semibold">
                                                <Users className="size-4 text-cyan-600" />
                                                Penguji Sempro
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Penguji pada seminar proposal
                                                terbaru
                                            </p>
                                        </div>
                                        {semproExaminerProfiles.length > 0 ? (
                                            <div className="grid gap-3">
                                                {semproExaminerProfiles.map(
                                                    (person, index) => (
                                                        <PersonCardLink
                                                            key={`${person.id}-${index}`}
                                                            person={person}
                                                            label={`Penguji ${index + 1}`}
                                                        />
                                                    ),
                                                )}
                                            </div>
                                        ) : (
                                            <div className="rounded-xl border border-dashed p-4 text-sm text-muted-foreground">
                                                Belum ada dosen penguji sempro.
                                            </div>
                                        )}
                                    </div>

                                    {/* Penguji Sidang */}
                                    <div className="space-y-3">
                                        <div>
                                            <p className="flex items-center gap-1.5 text-sm font-semibold">
                                                <Users className="size-4 text-purple-600" />
                                                Penguji Sidang
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Penguji pada sidang skripsi
                                                terbaru
                                            </p>
                                        </div>
                                        {sidangExaminerProfiles.length > 0 ? (
                                            <div className="grid gap-3">
                                                {sidangExaminerProfiles.map(
                                                    (person, index) => (
                                                        <PersonCardLink
                                                            key={`${person.id}-sidang-${index}`}
                                                            person={person}
                                                            label={`Penguji Sidang ${index + 1}`}
                                                        />
                                                    ),
                                                )}
                                            </div>
                                        ) : (
                                            <div className="rounded-xl border border-dashed p-4 text-sm text-muted-foreground">
                                                Belum ada dosen penguji sidang.
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </section>

                        {/* ── Hasil sidang ── */}
                        {semproResult && (
                            <DefenseResultSection
                                title="Hasil Seminar Proposal"
                                result={semproResult}
                            />
                        )}

                        {sidangResult && (
                            <DefenseResultSection
                                title="Hasil Sidang Skripsi"
                                result={sidangResult}
                            />
                        )}

                        {/* ── Riwayat sempro ── */}
                        <DefenseHistorySection
                            title="Riwayat Seminar Proposal"
                            items={defenseHistory.sempro}
                            type="sempro"
                        />

                        {/* ── Riwayat sidang ── */}
                        <DefenseHistorySection
                            title="Riwayat Sidang Skripsi"
                            items={defenseHistory.sidang}
                            type="sidang"
                        />
                    </>
                )}
            </div>
        </AppLayout>
    );
}
