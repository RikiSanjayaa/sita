import { Head, useForm, usePage } from '@inertiajs/react';
import {
    BookOpen,
    CalendarClock,
    CheckCircle2,
    ChevronRight,
    Clock,
    Download,
    Eye,
    FileText,
    GraduationCap,
    Languages,
    type LucideIcon,
    MapPin,
    Pencil,
    Search,
    Star,
    Users,
    X,
    XCircle,
} from 'lucide-react';
import { FormEventHandler, useMemo, useState } from 'react';

import { DeadlineBadge } from '@/components/deadline-badge';
import { LecturerSearchSelect } from '@/components/lecturer-search-select';
import { type MentorshipProgressRow } from '@/components/mentorship-progress';
import {
    DataSection,
    dataTh,
    stackedTable,
} from '@/components/patterns/data-section';
import { PageHeader } from '@/components/patterns/page-header';
import { PersonCardLink } from '@/components/profile/person-card-link';
import {
    type RevisionAnnotation,
    RevisionAnnotationLinks,
    type RevisionItem,
    RevisionItemList,
    showRevisionSummary,
} from '@/components/revision-items';
import {
    type ThesisNote,
    ThesisNotesPanel,
} from '@/components/thesis-notes-panel';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTableContainer } from '@/components/ui/data-table';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import {
    type AcademicGrade,
    calculateAverageAcademicScore,
    resolveAcademicGrade,
} from '@/lib/academic-grade';
import { type StatusTone, toneClassName } from '@/lib/status-styles';
import { cn } from '@/lib/utils';
import { dashboard, tugasAkhir } from '@/routes';
import { type SharedData, type UserProfileSummary } from '@/types';

type Submission = {
    id: number;
    program_studi: string;
    title_id: string;
    title_en: string;
    proposal_summary: string;
    discussion_lecturer_user_id: number | null;
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
    thesisNotes: ThesisNote[];
    mentorshipProgress: MentorshipProgressRow[];
    canCreateSubmission: boolean;
    workspaceDocuments: WorkspaceDocument[];
    semproSelection: DefenseSelection | null;
    sidangSelection: DefenseSelection | null;
    assignedLecturers: AssignedLecturers;
    advisorProfiles: UserProfileSummary[];
    semproExaminerProfiles: UserProfileSummary[];
    sidangExaminerProfiles: UserProfileSummary[];
    semproDate: string | null;
    sidangDate: string | null;
    sidangDateUntil: string | null;
    semproResult: {
        label: string;
        resultLabel: string;
        scheduledFor: string | null;
        scheduledUntil: string | null;
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
        scheduledUntil: string | null;
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
            scheduledUntil: string | null;
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
                items: RevisionItem[];
                annotations: RevisionAnnotation[];
            }>;
        }>;
        sidang: Array<{
            id: number;
            attemptNo: number;
            statusLabel: string;
            resultLabel: string;
            scheduledFor: string | null;
            scheduledUntil: string | null;
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
                items: RevisionItem[];
                annotations: RevisionAnnotation[];
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
    discussion_lecturer_user_id: string;
    proposal_file: File | null;
};

type DefenseDocumentFormData = {
    workspace_document_id: string;
    supporting_document_ids: string[];
};

function normalizeTitleEn(value: string | null | undefined): string {
    if (value === null || value === '-' || value === undefined) return '';
    return value;
}

function submissionDefaults(submission: Submission | null): FormData {
    return {
        title_id: submission?.title_id ?? '',
        title_en: normalizeTitleEn(submission?.title_en),
        proposal_summary: submission?.proposal_summary ?? '',
        discussion_lecturer_user_id:
            submission?.discussion_lecturer_user_id?.toString() ?? '',
        proposal_file: null,
    };
}

/* ─── Shared building blocks ────────────────────────────────────── */

/** The one bordered surface of a section; rows inside are separated by dividers. */
function Surface({
    className,
    children,
}: {
    className?: string;
    children: React.ReactNode;
}) {
    return (
        <div
            className={cn(
                'divide-y overflow-hidden rounded-xl border bg-card',
                className,
            )}
        >
            {children}
        </div>
    );
}

/** Short intro under a section title. */
function SectionIntro({ children }: { children: React.ReactNode }) {
    return (
        <p className="-mt-2 max-w-3xl text-sm text-pretty text-muted-foreground">
            {children}
        </p>
    );
}

function workflowTone(workflowKey: string): StatusTone {
    if (workflowKey === 'title_review_pending') return 'warning';
    if (['title_rejected', 'project_cancelled'].includes(workflowKey)) {
        return 'danger';
    }

    return 'success';
}

const workflowIcon: Record<StatusTone, LucideIcon> = {
    neutral: CheckCircle2,
    primary: CheckCircle2,
    success: CheckCircle2,
    warning: Clock,
    danger: XCircle,
    info: CheckCircle2,
};

function WorkflowBadge({
    workflowKey,
    label,
}: {
    workflowKey: string;
    label: string;
}) {
    const tone = workflowTone(workflowKey);
    const Icon = workflowIcon[tone];

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium',
                toneClassName(tone),
            )}
        >
            <Icon className="size-3" aria-hidden="true" />
            {label}
        </span>
    );
}

function gradeClassName(grade: AcademicGrade | null): string {
    if (grade === 'A' || grade === 'B+' || grade === 'B') {
        return toneClassName('success');
    }
    if (grade === 'C+' || grade === 'C') return toneClassName('warning');
    if (grade === 'D' || grade === 'E') return toneClassName('danger');

    return toneClassName('neutral');
}

function Score({ value }: { value: number | string }) {
    return (
        <span className="inline-flex items-center gap-1 text-sm font-semibold tabular-nums">
            <Star
                className="size-3.5 text-warning-muted-foreground"
                aria-hidden="true"
            />
            {value}
        </span>
    );
}

function FileActions({
    viewUrl,
    downloadUrl,
}: {
    viewUrl: string | null;
    downloadUrl: string | null;
}) {
    if (!viewUrl && !downloadUrl) return null;

    return (
        <div className="flex shrink-0 flex-wrap gap-2">
            {viewUrl && (
                <Button
                    asChild
                    type="button"
                    variant="outline"
                    size="sm"
                    className="max-md:h-11 max-md:px-4"
                >
                    <a href={viewUrl} target="_blank" rel="noreferrer">
                        <Eye className="size-3.5" />
                        Lihat
                    </a>
                </Button>
            )}
            {downloadUrl && (
                <Button
                    asChild
                    type="button"
                    size="sm"
                    className="max-md:h-11 max-md:px-4"
                >
                    <a href={downloadUrl}>
                        <Download className="size-3.5" />
                        Unduh
                    </a>
                </Button>
            )}
        </div>
    );
}

function FileRow({
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
            <p className="text-sm text-muted-foreground">
                Belum ada {label.toLowerCase()} yang dipilih.
            </p>
        );
    }

    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex min-w-0 items-center gap-3">
                <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary-muted text-primary-muted-foreground">
                    <FileText className="size-5" aria-hidden="true" />
                </span>
                <div className="min-w-0">
                    <p className="text-xs font-medium text-muted-foreground">
                        {label}
                    </p>
                    <p className="text-sm font-medium break-words">
                        {fileName}
                    </p>
                </div>
            </div>
            <FileActions viewUrl={viewUrl} downloadUrl={downloadUrl} />
        </div>
    );
}

function PersonGroup({
    title,
    description,
    emptyText,
    isEmpty,
    children,
}: {
    title: string;
    description: string;
    emptyText: string;
    isEmpty: boolean;
    children: React.ReactNode;
}) {
    return (
        <div className="space-y-3">
            <div>
                <h3 className="flex items-center gap-1.5 text-base font-semibold tracking-tight">
                    <Users className="size-4 text-primary" aria-hidden="true" />
                    {title}
                </h3>
                <p className="text-sm text-muted-foreground">{description}</p>
            </div>
            {isEmpty ? (
                <p className="text-sm text-pretty text-muted-foreground">
                    {emptyText}
                </p>
            ) : (
                <div className="grid divide-y">{children}</div>
            )}
        </div>
    );
}

/* ─── Submission form fields ────────────────────────────────────── */
function SubmissionFields({
    form,
    fileRequired,
    idPrefix,
    finalWork,
}: {
    form: ReturnType<typeof useForm<FormData>>;
    fileRequired: boolean;
    idPrefix: string;
    finalWork: string;
}) {
    const { academicTerminology: terms } = usePage<SharedData>().props;
    return (
        <div className="space-y-5">
            <div className="grid gap-1.5">
                <Label htmlFor={`${idPrefix}title_id`}>
                    Judul {finalWork} (Bahasa Indonesia)
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
                    Judul {finalWork} (Bahasa Inggris)
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
                    Ringkasan {terms.proposalDocument}
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

            <LecturerSearchSelect
                label="Dosen Diskusi Proposal"
                value={form.data.discussion_lecturer_user_id}
                onChange={(value) =>
                    form.setData('discussion_lecturer_user_id', value)
                }
                projectId={null}
                searchUrl="/mahasiswa/tugas-akhir/lecturers/search"
                purpose="supervisor"
                error={form.errors.discussion_lecturer_user_id}
            />
            <p className="-mt-2 text-xs text-muted-foreground">
                Wajib dipilih. Dosen diskusi proposal harus dosen aktif di
                program studi Anda.
            </p>

            <div className="grid gap-1.5">
                <Label htmlFor={`${idPrefix}proposal_file`}>
                    {fileRequired
                        ? `File ${terms.proposalDocument} (PDF)`
                        : `Ganti File ${terms.proposalDocument} (Opsional)`}
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

function DocumentOption({
    document,
    selected,
    onSelect,
}: {
    document: WorkspaceDocument;
    selected: boolean;
    onSelect: () => void;
}) {
    return (
        <button
            type="button"
            aria-pressed={selected}
            className="flex min-h-11 w-full items-start justify-between gap-3 px-3 py-2 text-left text-sm outline-none hover:bg-muted focus-visible:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset"
            onClick={onSelect}
        >
            <div className="min-w-0">
                <p className="truncate font-medium">{document.title}</p>
                <p className="truncate text-xs text-muted-foreground">
                    {document.fileName} · {document.category} ·{' '}
                    {document.version}
                </p>
            </div>
            {selected && <Badge className="shrink-0">Dipilih</Badge>}
        </button>
    );
}

function SelectedFile({
    fileName,
    onRemove,
}: {
    fileName: string;
    onRemove: () => void;
}) {
    return (
        <li className="flex items-center justify-between gap-2 rounded-md bg-muted/50 py-0.5 pl-3">
            <span className="min-w-0 text-sm font-medium break-words">
                {fileName}
            </span>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="size-9 shrink-0 max-md:size-11"
                aria-label={`Hapus pilihan ${fileName}`}
                onClick={onRemove}
            >
                <X className="size-4" />
            </Button>
        </li>
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
    finalExamLabel,
}: {
    title: string;
    description: string;
    workspaceDocuments: WorkspaceDocument[];
    selection: DefenseSelection | null;
    form: ReturnType<typeof useForm<DefenseDocumentFormData>>;
    onSubmit: FormEventHandler;
    allowSupportingFiles: boolean;
    finalExamLabel: string;
}) {
    const { academicTerminology: terms } = usePage<SharedData>().props;
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

    const toggleSupporting = (value: string) => {
        form.setData(
            'supporting_document_ids',
            form.data.supporting_document_ids.includes(value)
                ? form.data.supporting_document_ids.filter(
                      (item) => item !== value,
                  )
                : [...form.data.supporting_document_ids, value],
        );
    };

    return (
        <DataSection title={title}>
            <SectionIntro>{description}</SectionIntro>
            <Surface>
                <div className="p-4">
                    <FileRow
                        label={
                            allowSupportingFiles
                                ? 'Naskah akhir aktif'
                                : `${terms.proposalDocument} aktif`
                        }
                        fileName={selection.mainFileName}
                        viewUrl={selection.mainFileViewUrl}
                        downloadUrl={selection.mainFileDownloadUrl}
                    />
                </div>

                {allowSupportingFiles &&
                    selection.supportingDocuments.length > 0 && (
                        <div className="p-4">
                            <h3 className="mb-1 text-sm font-semibold">
                                Lampiran aktif
                            </h3>
                            <div className="divide-y">
                                {selection.supportingDocuments.map(
                                    (document) => (
                                        <div key={document.id} className="py-3">
                                            <FileRow
                                                label="Lampiran"
                                                fileName={document.name}
                                                viewUrl={document.viewUrl}
                                                downloadUrl={
                                                    document.downloadUrl
                                                }
                                            />
                                        </div>
                                    ),
                                )}
                            </div>
                        </div>
                    )}

                {selection.isLocked ? (
                    <p
                        className={cn(
                            'px-4 py-3 text-sm text-pretty',
                            toneClassName('warning'),
                        )}
                    >
                        Dokumen untuk tahap ini sudah dikunci karena hasil resmi
                        telah ditetapkan.
                    </p>
                ) : workspaceDocuments.length === 0 ? (
                    <p className="p-4 text-sm text-pretty text-muted-foreground">
                        Belum ada file di workspace. Unggah file lebih dulu dari
                        halaman{' '}
                        <a
                            href="/mahasiswa/upload-dokumen"
                            className="font-medium text-primary hover:underline"
                        >
                            Upload Dokumen
                        </a>
                        .
                    </p>
                ) : (
                    <form onSubmit={onSubmit} className="space-y-5 p-4">
                        <div className="grid gap-2">
                            <Label>
                                {allowSupportingFiles
                                    ? 'Pilih naskah akhir'
                                    : `Pilih file ${terms.proposalDocument.toLowerCase()}`}
                            </Label>
                            <div className="space-y-3">
                                <div className="flex min-h-11 items-center">
                                    {selectedMainDocument ? (
                                        <ul className="w-full">
                                            <SelectedFile
                                                fileName={
                                                    selectedMainDocument.fileName
                                                }
                                                onRemove={() => {
                                                    form.setData(
                                                        'workspace_document_id',
                                                        '',
                                                    );
                                                }}
                                            />
                                        </ul>
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
                                            setMainSearch(event.target.value)
                                        }
                                        placeholder="Cari file utama..."
                                        className="pl-9 max-md:h-11"
                                    />
                                </div>

                                <div className="max-h-56 divide-y overflow-y-auto border-y">
                                    {filteredMainOptions.length > 0 ? (
                                        filteredMainOptions.map((document) => {
                                            const value =
                                                document.id.toString();

                                            return (
                                                <DocumentOption
                                                    key={document.id}
                                                    document={document}
                                                    selected={
                                                        form.data
                                                            .workspace_document_id ===
                                                        value
                                                    }
                                                    onSelect={() => {
                                                        form.setData(
                                                            'workspace_document_id',
                                                            value,
                                                        );
                                                    }}
                                                />
                                            );
                                        })
                                    ) : (
                                        <p className="px-3 py-3 text-sm text-muted-foreground">
                                            Tidak ada file yang cocok dengan
                                            pencarian.
                                        </p>
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
                                    <Label>
                                        Lampiran pendukung {finalExamLabel}
                                    </Label>
                                    <div className="space-y-3">
                                        <div className="flex min-h-11 items-center">
                                            {selectedSupportingDocuments.length >
                                            0 ? (
                                                <ul className="w-full space-y-1">
                                                    {selectedSupportingDocuments.map(
                                                        (document) => (
                                                            <SelectedFile
                                                                key={
                                                                    document.id
                                                                }
                                                                fileName={
                                                                    document.fileName
                                                                }
                                                                onRemove={() =>
                                                                    toggleSupporting(
                                                                        document.id.toString(),
                                                                    )
                                                                }
                                                            />
                                                        ),
                                                    )}
                                                </ul>
                                            ) : (
                                                <span className="text-sm text-muted-foreground">
                                                    Belum ada lampiran dipilih.
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
                                                className="pl-9 max-md:h-11"
                                            />
                                        </div>

                                        <div className="max-h-56 divide-y overflow-y-auto border-y">
                                            {filteredSupportingOptions.length >
                                            0 ? (
                                                filteredSupportingOptions.map(
                                                    (document) => {
                                                        const value =
                                                            document.id.toString();

                                                        return (
                                                            <DocumentOption
                                                                key={
                                                                    document.id
                                                                }
                                                                document={
                                                                    document
                                                                }
                                                                selected={form.data.supporting_document_ids.includes(
                                                                    value,
                                                                )}
                                                                onSelect={() =>
                                                                    toggleSupporting(
                                                                        value,
                                                                    )
                                                                }
                                                            />
                                                        );
                                                    },
                                                )
                                            ) : (
                                                <p className="px-3 py-3 text-sm text-muted-foreground">
                                                    Tidak ada file yang cocok
                                                    dengan pencarian.
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            )}

                        <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
                            <Button
                                type="submit"
                                className="max-md:h-11"
                                disabled={
                                    form.processing ||
                                    form.data.workspace_document_id === ''
                                }
                            >
                                Simpan Dokumen
                            </Button>
                            <a
                                href="/mahasiswa/upload-dokumen"
                                className="inline-flex text-sm font-medium text-primary hover:underline max-md:min-h-11 max-md:items-center"
                            >
                                Kelola file workspace
                            </a>
                        </div>
                    </form>
                )}
            </Surface>
        </DataSection>
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
    const hasScores = result.examiners.some((e) => e.score !== null);

    return (
        <DataSection
            title={title}
            summary={[result.label, result.scheduledFor, result.location]
                .filter(Boolean)
                .join(' · ')}
        >
            <div className="flex flex-wrap items-center gap-2">
                <Badge
                    variant="outline"
                    className={cn(
                        'rounded-full border-transparent',
                        toneClassName('success'),
                    )}
                >
                    {result.resultLabel}
                </Badge>
                {averageScore !== null && (
                    <Score value={averageScore.toFixed(2)} />
                )}
                {finalGrade && (
                    <Badge
                        variant="soft"
                        className={gradeClassName(finalGrade)}
                    >
                        Grade {finalGrade}
                    </Badge>
                )}
            </div>

            <DataTableContainer>
                <table className={stackedTable.table}>
                    <thead className={stackedTable.thead}>
                        <tr className="border-b bg-muted/30">
                            <th className={dataTh}>Penguji</th>
                            <th className={dataTh}>Peran</th>
                            <th className={dataTh}>Keputusan</th>
                            {hasScores && (
                                <th className={cn(dataTh, 'md:text-right')}>
                                    Nilai
                                </th>
                            )}
                            <th className={dataTh}>Catatan</th>
                        </tr>
                    </thead>
                    <tbody className={cn('divide-y', stackedTable.tbody)}>
                        {result.examiners.map((examiner) => (
                            <tr
                                key={examiner.id}
                                className={cn(
                                    'transition-colors hover:bg-muted/20',
                                    stackedTable.tr,
                                )}
                            >
                                <td
                                    className={cn(
                                        stackedTable.tdPlain,
                                        'font-medium',
                                    )}
                                >
                                    {examiner.name}
                                </td>
                                <td
                                    data-label="Peran"
                                    className={cn(
                                        stackedTable.td,
                                        'text-muted-foreground',
                                    )}
                                >
                                    {examiner.roleLabel}
                                </td>
                                <td
                                    data-label="Keputusan"
                                    className={stackedTable.td}
                                >
                                    <Badge variant="outline">
                                        {examiner.decisionLabel}
                                    </Badge>
                                </td>
                                {hasScores && (
                                    <td
                                        data-label="Nilai"
                                        className={cn(
                                            stackedTable.td,
                                            'md:text-right',
                                        )}
                                    >
                                        {examiner.score !== null ? (
                                            <Score value={examiner.score} />
                                        ) : (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </td>
                                )}
                                <td
                                    data-label="Catatan"
                                    className={cn(
                                        stackedTable.td,
                                        'text-muted-foreground',
                                    )}
                                >
                                    {examiner.decisionNotes ?? (
                                        <span className="italic">
                                            Belum ada catatan
                                        </span>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </DataTableContainer>
        </DataSection>
    );
}

/* ─── Defense history expandable row ───────────────────────────── */
function DetailHeading({ children }: { children: React.ReactNode }) {
    return <h3 className="mb-1 text-sm font-semibold">{children}</h3>;
}

function DefenseHistoryRow({
    item,
    type,
    typeLabel,
}: {
    item: TugasAkhirPageProps['defenseHistory']['sempro'][number];
    type: 'sempro' | 'sidang';
    typeLabel: string;
}) {
    const [expanded, setExpanded] = useState(false);
    const averageScore = calculateAverageAcademicScore(
        item.examiners.map((e) => e.score),
    );
    const finalGrade = resolveAcademicGrade(averageScore);
    const hasRevisions = item.revisions.length > 0;
    const pendingRevisions = item.revisions.filter((r) => !r.resolvedAt).length;
    const hasFiles =
        item.primaryDocumentName !== null ||
        item.supportingDocuments.length > 0;

    return (
        <>
            {/* ── Summary row ── */}
            <tr
                className={cn(
                    'cursor-pointer transition-colors select-none hover:bg-muted/30',
                    stackedTable.tr,
                    expanded && 'bg-muted/20',
                )}
                onClick={() => setExpanded((v) => !v)}
            >
                <td className={stackedTable.tdPlain}>
                    <span
                        className={cn(
                            'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
                            toneClassName(
                                type === 'sempro' ? 'info' : 'primary',
                            ),
                        )}
                    >
                        {typeLabel} #{item.attemptNo}
                    </span>
                </td>

                <td data-label="Judul" className={stackedTable.td}>
                    <p className="text-sm font-medium text-pretty md:line-clamp-2 md:max-w-xs">
                        {item.titleId}
                    </p>
                    {item.titleEn && (
                        <p className="text-xs text-pretty text-muted-foreground italic md:line-clamp-1 md:max-w-xs">
                            {item.titleEn}
                        </p>
                    )}
                </td>

                <td data-label="Jadwal & Lokasi" className={stackedTable.td}>
                    {item.scheduledFor ? (
                        <span className="flex items-center gap-1.5 text-xs text-muted-foreground md:whitespace-nowrap">
                            <CalendarClock
                                className="size-3.5 shrink-0"
                                aria-hidden="true"
                            />
                            {item.scheduledFor}
                        </span>
                    ) : (
                        <span className="text-xs text-muted-foreground">—</span>
                    )}
                    {item.location && (
                        <span className="mt-0.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                            <MapPin
                                className="size-3.5 shrink-0"
                                aria-hidden="true"
                            />
                            {item.location}
                            {item.mode ? ` · ${item.mode}` : ''}
                        </span>
                    )}
                </td>

                <td data-label="Status" className={stackedTable.td}>
                    <Badge variant="outline">{item.statusLabel}</Badge>
                </td>

                <td data-label="Hasil & Nilai" className={stackedTable.td}>
                    <div className="flex flex-wrap items-center gap-1.5">
                        <Badge>{item.resultLabel}</Badge>
                        {averageScore !== null && (
                            <Score value={averageScore.toFixed(2)} />
                        )}
                        {finalGrade && (
                            <Badge
                                variant="soft"
                                className={gradeClassName(finalGrade)}
                            >
                                {finalGrade}
                            </Badge>
                        )}
                        {hasRevisions && (
                            <span
                                className={cn(
                                    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                    toneClassName(
                                        pendingRevisions > 0
                                            ? 'warning'
                                            : 'neutral',
                                    ),
                                )}
                            >
                                {item.revisions.length} revisi
                            </span>
                        )}
                    </div>
                </td>

                <td className={stackedTable.tdActions}>
                    <button
                        type="button"
                        aria-expanded={expanded}
                        className="inline-flex items-center gap-1.5 rounded-md text-sm font-medium text-primary outline-none focus-visible:ring-2 focus-visible:ring-ring max-md:min-h-11"
                    >
                        <ChevronRight
                            className={cn(
                                'size-4 transition-transform',
                                expanded && 'rotate-90',
                            )}
                            aria-hidden="true"
                        />
                        <span className="md:sr-only">
                            {expanded ? 'Tutup detail' : 'Lihat detail'}
                        </span>
                    </button>
                </td>
            </tr>

            {/* ── Expanded detail panel ── */}
            {expanded && (
                <tr className="max-md:block">
                    <td colSpan={6} className="p-0 max-md:block">
                        <div className="space-y-5 border-t bg-muted/20 px-4 py-4 md:px-5">
                            {item.examiners.length > 0 && (
                                <div>
                                    <DetailHeading>
                                        Penilaian Penguji
                                    </DetailHeading>
                                    <ul className="divide-y">
                                        {item.examiners.map((ex) => (
                                            <li key={ex.id} className="py-3">
                                                <div className="flex flex-wrap items-start justify-between gap-2">
                                                    <div className="min-w-0">
                                                        <p className="text-sm font-medium">
                                                            {ex.name}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {ex.roleLabel}
                                                        </p>
                                                    </div>
                                                    <div className="flex shrink-0 items-center gap-2">
                                                        {ex.score !== null && (
                                                            <Score
                                                                value={ex.score}
                                                            />
                                                        )}
                                                        <Badge variant="outline">
                                                            {ex.decisionLabel}
                                                        </Badge>
                                                    </div>
                                                </div>
                                                {ex.decisionNotes && (
                                                    <p className="mt-1.5 text-sm text-pretty text-muted-foreground">
                                                        {ex.decisionNotes}
                                                    </p>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                            {hasRevisions && (
                                <div>
                                    <DetailHeading>
                                        Catatan Revisi ({item.revisions.length})
                                    </DetailHeading>
                                    <ul className="divide-y">
                                        {item.revisions.map((rev) => {
                                            const isDone = !!rev.resolvedAt;

                                            return (
                                                <li
                                                    key={rev.id}
                                                    className="py-3"
                                                >
                                                    <div className="mb-1.5 flex flex-wrap items-center gap-2">
                                                        <span
                                                            className={cn(
                                                                'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                                                toneClassName(
                                                                    isDone
                                                                        ? 'success'
                                                                        : 'warning',
                                                                ),
                                                            )}
                                                        >
                                                            {rev.statusLabel}
                                                        </span>
                                                        <span className="text-xs font-medium text-muted-foreground">
                                                            {rev.requestedBy}
                                                        </span>
                                                        {rev.dueAt &&
                                                            !isDone && (
                                                                <DeadlineBadge
                                                                    dueAt={
                                                                        rev.dueAt
                                                                    }
                                                                />
                                                            )}
                                                        {rev.resolvedAt && (
                                                            <span className="flex items-center gap-1 text-xs text-success-muted-foreground">
                                                                <CheckCircle2
                                                                    className="size-3.5"
                                                                    aria-hidden="true"
                                                                />
                                                                Selesai:{' '}
                                                                {rev.resolvedAt}
                                                            </span>
                                                        )}
                                                    </div>

                                                    {showRevisionSummary(
                                                        rev.notes,
                                                        rev.items,
                                                    ) && (
                                                        <p className="text-sm text-pretty">
                                                            {rev.notes}
                                                        </p>
                                                    )}
                                                    <RevisionItemList
                                                        items={rev.items}
                                                    />
                                                    <RevisionAnnotationLinks
                                                        annotations={
                                                            rev.annotations
                                                        }
                                                    />

                                                    {rev.resolutionNotes && (
                                                        <p className="mt-2 border-l-2 pl-3 text-sm text-pretty text-muted-foreground">
                                                            <span className="font-medium text-foreground">
                                                                Penyelesaian:
                                                            </span>{' '}
                                                            {
                                                                rev.resolutionNotes
                                                            }
                                                        </p>
                                                    )}
                                                </li>
                                            );
                                        })}
                                    </ul>
                                </div>
                            )}

                            {hasFiles && (
                                <div>
                                    <DetailHeading>Berkas</DetailHeading>
                                    <div className="divide-y">
                                        {item.primaryDocumentName && (
                                            <div className="py-3">
                                                <FileRow
                                                    label={
                                                        item.primaryDocumentLabel
                                                    }
                                                    fileName={
                                                        item.primaryDocumentName
                                                    }
                                                    viewUrl={
                                                        item.primaryDocumentViewUrl
                                                    }
                                                    downloadUrl={
                                                        item.primaryDocumentDownloadUrl
                                                    }
                                                />
                                            </div>
                                        )}
                                        {item.supportingDocuments.map(
                                            (document) => (
                                                <div
                                                    key={document.id}
                                                    className="py-3"
                                                >
                                                    <FileRow
                                                        label="Lampiran"
                                                        fileName={document.name}
                                                        viewUrl={
                                                            document.viewUrl
                                                        }
                                                        downloadUrl={
                                                            document.downloadUrl
                                                        }
                                                    />
                                                </div>
                                            ),
                                        )}
                                    </div>
                                </div>
                            )}

                            {item.officialNotes && (
                                <p className="text-sm text-pretty text-muted-foreground">
                                    <span className="font-medium text-foreground">
                                        Catatan admin:
                                    </span>{' '}
                                    {item.officialNotes}
                                </p>
                            )}
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
    typeLabel,
}: {
    title: string;
    items: TugasAkhirPageProps['defenseHistory']['sempro'];
    type: 'sempro' | 'sidang';
    typeLabel: string;
}) {
    if (items.length === 0) return null;

    return (
        <DataSection title={title}>
            <SectionIntro>
                Klik baris untuk melihat detail, nilai, dan catatan revisi.
            </SectionIntro>

            <DataTableContainer>
                <table className={stackedTable.table}>
                    <thead className={stackedTable.thead}>
                        <tr className="border-b bg-muted/30">
                            <th className={dataTh}>Tipe</th>
                            <th className={dataTh}>Judul</th>
                            <th className={dataTh}>Jadwal & Lokasi</th>
                            <th className={dataTh}>Status</th>
                            <th className={dataTh}>Hasil & Nilai</th>
                            <th className={dataTh}>
                                <span className="sr-only">Detail</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody className={cn('divide-y', stackedTable.tbody)}>
                        {items.map((item) => (
                            <DefenseHistoryRow
                                key={item.id}
                                item={item}
                                type={type}
                                typeLabel={typeLabel}
                            />
                        ))}
                    </tbody>
                </table>
            </DataTableContainer>
        </DataSection>
    );
}

/** Label with icon above a value, one row of a Surface. */
function InfoRow({
    icon: Icon,
    label,
    children,
}: {
    icon: LucideIcon;
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="space-y-1.5 p-4">
            <p className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                <Icon className="size-3.5" aria-hidden="true" />
                {label}
            </p>
            {children}
        </div>
    );
}

/* ─── Main page ─────────────────────────────────────────────────── */
export default function TugasAkhirSaya() {
    const {
        submission,
        thesisNotes,
        mentorshipProgress,
        canCreateSubmission,
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
        academicTerminology: terms,
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
            breadcrumbs={[
                { title: 'Dashboard', href: dashboard().url },
                { title: terms.finalWork, href: tugasAkhir().url },
            ]}
            title={terms.finalWork}
            subtitle={`Judul, ${terms.proposalDocument.toLowerCase()}, ${terms.proposalExamShort}, dan ${terms.finalExam} Anda`}
        >
            <Head title={terms.finalWork} />

            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-8 px-4 py-6 md:px-6">
                <PageHeader
                    title={terms.finalWork}
                    description={`Judul, ${terms.proposalDocument.toLowerCase()}, ${terms.proposalExamShort}, dan ${terms.finalExam} Anda`}
                />

                {/* Flash / Error */}
                {(flashMessage || errorMessage) && (
                    <Alert variant={errorMessage ? 'destructive' : 'default'}>
                        <AlertDescription>
                            {errorMessage || flashMessage}
                        </AlertDescription>
                    </Alert>
                )}

                {/* ── Create submission ── */}
                {canCreateSubmission && (
                    <DataSection
                        title={`Ajukan Judul & ${terms.proposalDocument}`}
                    >
                        <SectionIntro>
                            {submission === null
                                ? `Isi formulir di bawah ini untuk memulai pengajuan ${terms.finalWorkLower} Anda.`
                                : `Pengajuan sebelumnya sudah ditutup. Anda dapat mengirim judul dan ${terms.proposalDocument.toLowerCase()} baru dari formulir ini.`}
                        </SectionIntro>
                        <Surface>
                            <form
                                onSubmit={createSubmission}
                                className="space-y-5 p-4 md:p-5"
                            >
                                <SubmissionFields
                                    form={form}
                                    fileRequired
                                    idPrefix="create_"
                                    finalWork={terms.finalWork}
                                />
                                <Button
                                    type="submit"
                                    className="max-md:h-11"
                                    disabled={form.processing}
                                >
                                    <BookOpen className="size-4" />
                                    Ajukan Sekarang
                                </Button>
                            </form>
                        </Surface>
                    </DataSection>
                )}

                {/* ── Has submission ── */}
                {submission !== null && (
                    <>
                        {/* ── Status pengajuan ── */}
                        <DataSection title="Status Pengajuan">
                            <SectionIntro>
                                Perkembangan pengajuan Anda saat ini
                            </SectionIntro>
                            <Surface>
                                <div className="flex flex-col items-start gap-2 p-4">
                                    <WorkflowBadge
                                        workflowKey={submission.workflow.key}
                                        label={label}
                                    />
                                    <p className="text-sm text-pretty text-muted-foreground">
                                        {description}
                                    </p>
                                </div>
                            </Surface>
                        </DataSection>

                        {/* ── Informasi Judul ── */}
                        <DataSection title="Informasi Judul">
                            <SectionIntro>
                                {`Judul dan ringkasan ${terms.proposalDocument.toLowerCase()} terbaru`}
                            </SectionIntro>
                            <Surface>
                                {!canEditSubmission && !isEditing && (
                                    <p
                                        className={cn(
                                            'px-4 py-3 text-sm text-pretty',
                                            toneClassName('warning'),
                                        )}
                                    >
                                        Pengajuan yang sudah diproses tidak bisa
                                        diedit. Hubungi admin jika perlu
                                        perubahan.
                                    </p>
                                )}

                                {isEditing ? (
                                    <form
                                        onSubmit={updateSubmission}
                                        className="space-y-5 p-4 md:p-5"
                                    >
                                        <SubmissionFields
                                            form={form}
                                            fileRequired={false}
                                            idPrefix="edit_"
                                            finalWork={terms.finalWork}
                                        />
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                type="submit"
                                                className="max-md:h-11"
                                                disabled={form.processing}
                                            >
                                                Simpan Perubahan
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="max-md:h-11"
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
                                ) : (
                                    <>
                                        <InfoRow
                                            icon={GraduationCap}
                                            label="Judul (Bahasa Indonesia)"
                                        >
                                            <p className="text-sm font-semibold text-pretty">
                                                {submission.title_id}
                                            </p>
                                        </InfoRow>

                                        {submission.title_en && (
                                            <InfoRow
                                                icon={Languages}
                                                label="Judul (Bahasa Inggris)"
                                            >
                                                <p className="text-sm text-pretty text-muted-foreground italic">
                                                    {submission.title_en}
                                                </p>
                                            </InfoRow>
                                        )}

                                        <InfoRow
                                            icon={FileText}
                                            label={`Ringkasan ${terms.proposalDocument}`}
                                        >
                                            <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                                                {submission.proposal_summary}
                                            </p>
                                        </InfoRow>

                                        <div className="flex flex-wrap items-center justify-between gap-3 p-4">
                                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                                <span>Program Studi:</span>
                                                <Badge variant="secondary">
                                                    {submission.program_studi}
                                                </Badge>
                                            </div>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                className="max-md:h-11 max-md:px-4"
                                                disabled={!canEditSubmission}
                                                onClick={() =>
                                                    setIsEditing(true)
                                                }
                                            >
                                                <Pencil className="size-3.5" />
                                                Edit
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Surface>
                        </DataSection>

                        {/* ── File proposal ── */}
                        {(submission.proposal_file_download_url ||
                            submission.proposal_file_view_url) && (
                            <DataSection
                                title={`File ${terms.proposalDocument} Terkirim`}
                            >
                                <SectionIntro>
                                    {`Anda dapat melihat dan mengunduh ulang file ${terms.proposalDocument.toLowerCase()}`}
                                </SectionIntro>
                                <Surface>
                                    <div className="p-4">
                                        <FileRow
                                            label={`${terms.proposalDocument} yang tersimpan`}
                                            fileName={
                                                submission.proposal_file_name ??
                                                'Proposal.pdf'
                                            }
                                            viewUrl={
                                                submission.proposal_file_view_url
                                            }
                                            downloadUrl={
                                                submission.proposal_file_download_url
                                            }
                                        />
                                    </div>
                                </Surface>
                            </DataSection>
                        )}

                        <DefenseDocumentSelectionSection
                            title={`Dokumen ${terms.proposalExam}`}
                            description={`Pilih file dari workspace untuk dijadikan snapshot resmi pada ${terms.proposalExamShort} aktif. File bisa diganti sampai hasil ${terms.proposalExamShort} ditetapkan.`}
                            workspaceDocuments={workspaceDocuments}
                            selection={semproSelection}
                            form={semproForm}
                            onSubmit={updateSemproDocuments}
                            allowSupportingFiles={false}
                            finalExamLabel={terms.finalExam}
                        />

                        <DefenseDocumentSelectionSection
                            title={`Dokumen ${terms.finalExam}`}
                            description={`Pilih naskah akhir utama dan lampiran pendukung dari workspace. Snapshot ini dipakai untuk ${terms.finalExam.toLowerCase()} aktif dan bisa diperbarui sampai hasilnya ditetapkan.`}
                            workspaceDocuments={workspaceDocuments}
                            selection={sidangSelection}
                            form={sidangForm}
                            onSubmit={updateSidangDocuments}
                            allowSupportingFiles
                            finalExamLabel={terms.finalExam}
                        />

                        {/* ── Dosen Pembimbing & Penguji ── */}
                        <DataSection title="Dosen Pembimbing & Penguji">
                            <SectionIntro>
                                {`Dosen yang terlibat pada ${terms.proposalExamShort} dan ${terms.finalExam.toLowerCase()} Anda`}
                            </SectionIntro>
                            <Surface className="xl:grid xl:grid-cols-3 xl:divide-x xl:divide-y-0">
                                <div className="p-4 md:p-5">
                                    <PersonGroup
                                        title="Dosen Pembimbing"
                                        description={`Pembimbing aktif ${terms.finalWorkLower}`}
                                        emptyText="Belum ada dosen pembimbing aktif."
                                        isEmpty={advisorProfiles.length === 0}
                                    >
                                        {advisorProfiles.map(
                                            (person, index) => (
                                                <PersonCardLink
                                                    key={person.id}
                                                    variant="row"
                                                    person={person}
                                                    label={`Pembimbing ${index + 1}`}
                                                    mentorshipCount={
                                                        mentorshipProgress.find(
                                                            (row) =>
                                                                row.lecturer
                                                                    .id ===
                                                                person.id,
                                                        )?.completed
                                                    }
                                                />
                                            ),
                                        )}
                                    </PersonGroup>
                                </div>

                                <div className="p-4 md:p-5">
                                    <PersonGroup
                                        title={`Penguji ${terms.proposalExamShort}`}
                                        description={`Penguji pada ${terms.proposalExam.toLowerCase()} terbaru`}
                                        emptyText={`Belum ada dosen penguji ${terms.proposalExamShort}.`}
                                        isEmpty={
                                            semproExaminerProfiles.length === 0
                                        }
                                    >
                                        {semproExaminerProfiles.map(
                                            (person, index) => (
                                                <PersonCardLink
                                                    key={`${person.id}-${index}`}
                                                    variant="row"
                                                    person={person}
                                                    label={`Penguji ${index + 1}`}
                                                />
                                            ),
                                        )}
                                    </PersonGroup>
                                </div>

                                <div className="p-4 md:p-5">
                                    <PersonGroup
                                        title={`Penguji ${terms.finalExam}`}
                                        description={`Penguji pada ${terms.finalExam.toLowerCase()} terbaru`}
                                        emptyText={`Belum ada dosen penguji ${terms.finalExam.toLowerCase()}.`}
                                        isEmpty={
                                            sidangExaminerProfiles.length === 0
                                        }
                                    >
                                        {sidangExaminerProfiles.map(
                                            (person, index) => (
                                                <PersonCardLink
                                                    key={`${person.id}-sidang-${index}`}
                                                    variant="row"
                                                    person={person}
                                                    label={`Penguji ${terms.finalExam} ${index + 1}`}
                                                />
                                            ),
                                        )}
                                    </PersonGroup>
                                </div>
                            </Surface>
                        </DataSection>

                        {/* ── Catatan Dosen (hanya yang untuk mahasiswa) ── */}
                        {thesisNotes.length > 0 && (
                            <DataSection title="Catatan Dosen">
                                <SectionIntro>
                                    {`Catatan dari pembimbing dan penguji untuk ${terms.finalWorkLower} Anda`}
                                </SectionIntro>
                                <ThesisNotesPanel
                                    projectId={submission.id}
                                    data={{
                                        canWrite: false,
                                        notes: thesisNotes,
                                    }}
                                    showVisibility={false}
                                />
                            </DataSection>
                        )}

                        {/* ── Hasil sidang ── */}
                        {semproResult && (
                            <DefenseResultSection
                                title={`Hasil ${terms.proposalExam}`}
                                result={semproResult}
                            />
                        )}

                        {sidangResult && (
                            <DefenseResultSection
                                title={`Hasil ${terms.finalExam}`}
                                result={sidangResult}
                            />
                        )}

                        {/* ── Riwayat sempro ── */}
                        <DefenseHistorySection
                            title={`Riwayat ${terms.proposalExam}`}
                            items={defenseHistory.sempro}
                            type="sempro"
                            typeLabel={terms.proposalExamShort}
                        />

                        {/* ── Riwayat sidang ── */}
                        <DefenseHistorySection
                            title={`Riwayat ${terms.finalExam}`}
                            items={defenseHistory.sidang}
                            type="sidang"
                            typeLabel={terms.finalExam}
                        />
                    </>
                )}
            </div>
        </AppLayout>
    );
}
