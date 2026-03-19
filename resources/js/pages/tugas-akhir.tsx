import { Head, useForm, usePage } from '@inertiajs/react';
import {
    BookOpen,
    CheckCircle2,
    Clock,
    Download,
    Eye,
    FileText,
    Pencil,
} from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import { PersonCardLink } from '@/components/profile/person-card-link';
import { Alert, AlertDescription } from '@/components/ui/alert';
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
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import {
    academicGradeClassName,
    calculateAverageAcademicScore,
    resolveAcademicGrade,
} from '@/lib/academic-grade';
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

type TugasAkhirPageProps = {
    submission: Submission | null;
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
            proposalFileName: string | null;
            proposalFileViewUrl: string | null;
            proposalFileDownloadUrl: string | null;
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
            proposalFileName: string | null;
            proposalFileViewUrl: string | null;
            proposalFileDownloadUrl: string | null;
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

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Tugas Akhir', href: tugasAkhir().url },
];

const sectionCardClass = 'overflow-hidden py-0 shadow-sm';
const sectionCardHeaderClass = 'border-b bg-muted/20 px-6 py-4';

function normalizeTitleEn(value: string | null | undefined): string {
    if (value === null || value === '-' || value === undefined) {
        return '';
    }

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

function ProposalFileCard({ submission }: { submission: Submission }) {
    if (
        submission.proposal_file_download_url === null ||
        submission.proposal_file_view_url === null
    ) {
        return (
            <Card className={sectionCardClass}>
                <CardHeader className={sectionCardHeaderClass}>
                    <CardTitle>File Proposal Terkirim</CardTitle>
                    <CardDescription>
                        File proposal belum tersedia. Admin akan membantu jika
                        terjadi kendala.
                    </CardDescription>
                </CardHeader>
            </Card>
        );
    }

    return (
        <Card className={sectionCardClass}>
            <CardHeader className={sectionCardHeaderClass}>
                <CardTitle>File Proposal Terkirim</CardTitle>
                <CardDescription>
                    Anda dapat melihat dan mengunduh ulang file proposal.
                </CardDescription>
            </CardHeader>
            <CardContent className="pb-6">
                <div className="flex flex-col gap-4 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-3">
                        <span className="mt-0.5 inline-flex size-9 items-center justify-center rounded-md bg-muted text-muted-foreground">
                            <FileText className="size-4" />
                        </span>
                        <div>
                            <p className="text-sm font-medium">
                                {submission.proposal_file_name ??
                                    'Proposal.pdf'}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Dokumen proposal yang tersimpan dari pengajuan
                                Anda.
                            </p>
                        </div>
                    </div>

                    <div className="flex gap-2">
                        <Button asChild type="button" variant="outline">
                            <a
                                href={submission.proposal_file_view_url}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <Eye className="mr-2 size-4" />
                                Lihat
                            </a>
                        </Button>
                        <Button asChild type="button">
                            <a href={submission.proposal_file_download_url}>
                                <Download className="mr-2 size-4" />
                                Unduh
                            </a>
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

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
        <div className="space-y-6">
            <div className="space-y-2">
                <Label htmlFor={`${idPrefix}title_id`}>
                    Judul Skripsi (Bahasa Indonesia)
                </Label>
                <Textarea
                    id={`${idPrefix}title_id`}
                    value={form.data.title_id}
                    onChange={(event) =>
                        form.setData('title_id', event.target.value)
                    }
                    className="h-20"
                    required
                />
                {form.errors.title_id && (
                    <p className="text-sm text-destructive">
                        {form.errors.title_id}
                    </p>
                )}
            </div>

            <div className="space-y-2">
                <Label htmlFor={`${idPrefix}title_en`}>
                    Judul Skripsi (Bahasa Inggris)
                </Label>
                <Textarea
                    id={`${idPrefix}title_en`}
                    value={form.data.title_en}
                    onChange={(event) =>
                        form.setData('title_en', event.target.value)
                    }
                    className="h-20"
                />
                {form.errors.title_en && (
                    <p className="text-sm text-destructive">
                        {form.errors.title_en}
                    </p>
                )}
            </div>

            <div className="space-y-2">
                <Label htmlFor={`${idPrefix}proposal_summary`}>
                    Ringkasan Proposal
                </Label>
                <Textarea
                    id={`${idPrefix}proposal_summary`}
                    value={form.data.proposal_summary}
                    onChange={(event) =>
                        form.setData('proposal_summary', event.target.value)
                    }
                    className="h-40"
                    required
                />
                {form.errors.proposal_summary && (
                    <p className="text-sm text-destructive">
                        {form.errors.proposal_summary}
                    </p>
                )}
            </div>

            <div className="space-y-2">
                <Label htmlFor={`${idPrefix}proposal_file`}>
                    {fileRequired
                        ? 'File Proposal (PDF)'
                        : 'Ganti File Proposal (Opsional)'}
                </Label>
                <Input
                    id={`${idPrefix}proposal_file`}
                    type="file"
                    accept=".pdf"
                    onChange={(event) =>
                        form.setData(
                            'proposal_file',
                            event.target.files?.[0] ?? null,
                        )
                    }
                    required={fileRequired}
                />
                {form.errors.proposal_file && (
                    <p className="text-sm text-destructive">
                        {form.errors.proposal_file}
                    </p>
                )}
            </div>
        </div>
    );
}

function DefenseResultCard({
    title,
    result,
}: {
    title: string;
    result: NonNullable<TugasAkhirPageProps['semproResult']>;
}) {
    const averageScore = calculateAverageAcademicScore(
        result.examiners.map((examiner) => examiner.score),
    );
    const finalGrade = resolveAcademicGrade(averageScore);

    return (
        <Card className={sectionCardClass}>
            <CardHeader className={`${sectionCardHeaderClass} gap-3`}>
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <CardTitle>{title}</CardTitle>
                        <CardDescription>
                            Nilai dan catatan dari dosen penguji.
                        </CardDescription>
                    </div>

                    <div className="flex flex-wrap gap-2 sm:justify-end">
                        <Badge className="w-fit bg-primary text-primary-foreground hover:bg-primary/90">
                            {result.resultLabel}
                        </Badge>
                        {averageScore !== null ? (
                            <Badge variant="secondary">
                                Nilai {averageScore.toFixed(2)}
                            </Badge>
                        ) : null}
                        {finalGrade ? (
                            <Badge
                                variant="soft"
                                className={academicGradeClassName(finalGrade)}
                            >
                                Grade {finalGrade}
                            </Badge>
                        ) : null}
                    </div>
                </div>
            </CardHeader>
            <CardContent className="space-y-6 pb-6">
                <div className="rounded-xl border bg-muted/15 p-4">
                    <div className="space-y-1">
                        <p className="text-sm font-medium text-foreground">
                            {result.label}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {result.scheduledFor ?? '-'}
                            {result.location ? ` · ${result.location}` : ''}
                        </p>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    {result.examiners.map((examiner) => {
                        return (
                            <div
                                key={examiner.id}
                                className="rounded-xl border bg-background p-4"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p className="text-sm font-semibold text-foreground">
                                            {examiner.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {examiner.roleLabel}
                                        </p>
                                    </div>
                                    <Badge variant="outline">
                                        {examiner.decisionLabel}
                                    </Badge>
                                </div>

                                <div className="mt-4 flex flex-wrap gap-2">
                                    <Badge variant="secondary">
                                        Nilai:{' '}
                                        {examiner.score !== null
                                            ? examiner.score
                                            : '-'}
                                    </Badge>
                                </div>

                                <div className="mt-4 rounded-lg border bg-muted/20 p-3 text-sm text-muted-foreground">
                                    <span className="font-medium text-foreground">
                                        Catatan keputusan:
                                    </span>{' '}
                                    {examiner.decisionNotes ??
                                        'Belum ada catatan dari penguji.'}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </CardContent>
        </Card>
    );
}

function DefenseHistorySection({
    title,
    items,
}: {
    title: string;
    items: TugasAkhirPageProps['defenseHistory']['sempro'];
}) {
    if (items.length === 0) {
        return null;
    }

    return (
        <Card className={sectionCardClass}>
            <CardHeader className={sectionCardHeaderClass}>
                <CardTitle>{title}</CardTitle>
                <CardDescription>
                    Jadwal, nilai, catatan dosen, dan revisi tiap ujian.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4 pb-6">
                {items.map((item) => {
                    const averageScore = calculateAverageAcademicScore(
                        item.examiners.map((examiner) => examiner.score),
                    );
                    const finalGrade = resolveAcademicGrade(averageScore);

                    return (
                        <div key={item.id} className="rounded-xl border p-4">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p className="text-sm font-semibold text-foreground">
                                        Attempt #{item.attemptNo}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {item.scheduledFor ?? '-'}
                                        {item.location
                                            ? ` · ${item.location}`
                                            : ''}
                                        {item.mode ? ` · ${item.mode}` : ''}
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Badge variant="outline">
                                        {item.statusLabel}
                                    </Badge>
                                    <Badge className="bg-primary text-primary-foreground hover:bg-primary/90">
                                        {item.resultLabel}
                                    </Badge>
                                    {averageScore !== null ? (
                                        <Badge variant="secondary">
                                            Nilai {averageScore.toFixed(2)}
                                        </Badge>
                                    ) : null}
                                    {finalGrade ? (
                                        <Badge
                                            variant="soft"
                                            className={academicGradeClassName(
                                                finalGrade,
                                            )}
                                        >
                                            Grade {finalGrade}
                                        </Badge>
                                    ) : null}
                                </div>
                            </div>

                            <div className="mt-4 rounded-xl border bg-muted/15 p-4">
                                <p className="text-sm font-semibold text-foreground">
                                    {item.titleId}
                                </p>
                                {item.titleEn ? (
                                    <p className="mt-1 text-xs text-muted-foreground italic">
                                        {item.titleEn}
                                    </p>
                                ) : null}
                                {item.proposalSummary ? (
                                    <p className="mt-3 text-sm text-muted-foreground">
                                        {item.proposalSummary}
                                    </p>
                                ) : null}
                                {item.proposalFileName ? (
                                    <div className="mt-4 flex flex-wrap gap-2">
                                        <Badge variant="outline">
                                            {item.proposalFileName}
                                        </Badge>
                                        {item.proposalFileViewUrl ? (
                                            <Button
                                                asChild
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                            >
                                                <a
                                                    href={
                                                        item.proposalFileViewUrl
                                                    }
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    <Eye className="mr-2 size-4" />
                                                    Lihat Proposal
                                                </a>
                                            </Button>
                                        ) : null}
                                        {item.proposalFileDownloadUrl ? (
                                            <Button
                                                asChild
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                            >
                                                <a
                                                    href={
                                                        item.proposalFileDownloadUrl
                                                    }
                                                >
                                                    <Download className="mr-2 size-4" />
                                                    Unduh Proposal
                                                </a>
                                            </Button>
                                        ) : null}
                                    </div>
                                ) : null}
                            </div>

                            <div className="mt-4 grid gap-3 lg:grid-cols-2">
                                {item.examiners.map((examiner) => (
                                    <div
                                        key={examiner.id}
                                        className="rounded-lg border bg-background p-3"
                                    >
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <p className="text-sm font-medium text-foreground">
                                                    {examiner.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {examiner.roleLabel}
                                                </p>
                                            </div>
                                            <Badge variant="outline">
                                                {examiner.decisionLabel}
                                            </Badge>
                                        </div>
                                        <div className="mt-3 flex flex-wrap gap-2">
                                            <Badge variant="secondary">
                                                Nilai: {examiner.score ?? '-'}
                                            </Badge>
                                        </div>
                                        <div className="mt-3 rounded-lg border bg-muted/20 p-3 text-sm text-muted-foreground">
                                            <span className="font-medium text-foreground">
                                                Catatan dosen:
                                            </span>{' '}
                                            {examiner.decisionNotes ??
                                                'Belum ada catatan dari dosen.'}
                                        </div>
                                    </div>
                                ))}
                            </div>

                            {item.revisions.length > 0 ? (
                                <div className="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/30">
                                    <p className="text-sm font-semibold text-amber-900 dark:text-amber-100">
                                        Catatan Revisi
                                    </p>
                                    <div className="mt-3 space-y-3">
                                        {item.revisions.map((revision) => (
                                            <div
                                                key={revision.id}
                                                className="rounded-lg border border-amber-200/70 bg-white/70 p-3 dark:border-amber-800 dark:bg-transparent"
                                            >
                                                <div className="flex flex-wrap gap-2">
                                                    <Badge variant="outline">
                                                        {revision.statusLabel}
                                                    </Badge>
                                                    <Badge variant="secondary">
                                                        {revision.requestedBy}
                                                    </Badge>
                                                </div>
                                                <p className="mt-3 text-sm text-amber-950 dark:text-amber-100">
                                                    {revision.notes}
                                                </p>
                                                <p className="mt-2 text-xs text-amber-800 dark:text-amber-300">
                                                    {revision.dueAt
                                                        ? `Batas: ${revision.dueAt}`
                                                        : 'Tanpa batas revisi'}
                                                    {revision.resolvedAt
                                                        ? ` · Selesai: ${revision.resolvedAt}`
                                                        : ''}
                                                </p>
                                                {revision.resolutionNotes ? (
                                                    <p className="mt-2 text-xs text-amber-800 dark:text-amber-300">
                                                        Catatan penyelesaian:{' '}
                                                        {
                                                            revision.resolutionNotes
                                                        }
                                                    </p>
                                                ) : null}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ) : null}

                            {item.officialNotes ? (
                                <div className="mt-4 rounded-lg border bg-background p-3 text-sm text-muted-foreground">
                                    <span className="font-medium text-foreground">
                                        Catatan resmi admin:
                                    </span>{' '}
                                    {item.officialNotes}
                                </div>
                            ) : null}
                        </div>
                    );
                })}
            </CardContent>
        </Card>
    );
}

export default function TugasAkhirSaya() {
    const {
        submission,
        advisorProfiles,
        semproExaminerProfiles,
        sidangExaminerProfiles,
        sidangResult,
        defenseHistory,
        flashMessage,
        errorMessage,
    } = usePage<SharedData & TugasAkhirPageProps>().props;
    const [isEditing, setIsEditing] = useState(false);
    const form = useForm<FormData>(submissionDefaults(submission));

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

        form.transform((data) => ({
            ...data,
            _method: 'patch',
        }));
        form.post(`/mahasiswa/tugas-akhir/${submission.id}`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setIsEditing(false);
                form.setData('proposal_file', null);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tugas Akhir" />
            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-6">
                <div>
                    <h1 className="text-xl font-semibold">Tugas Akhir</h1>
                    <p className="text-sm text-muted-foreground">
                        mulai dari judul, proposal hingga seminar dan sidang
                        beserta riwayat skripsi Anda.
                    </p>
                </div>

                {(flashMessage || errorMessage) && (
                    <Alert variant={errorMessage ? 'destructive' : 'default'}>
                        <AlertDescription>
                            {errorMessage || flashMessage}
                        </AlertDescription>
                    </Alert>
                )}

                {submission === null && (
                    <Card className={sectionCardClass}>
                        <CardHeader className={sectionCardHeaderClass}>
                            <CardTitle>Ajukan Judul & Proposal</CardTitle>
                            <CardDescription>
                                Isi formulir di bawah ini untuk mengajukan judul
                                dan proposal skripsi.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="pb-6">
                            <form
                                onSubmit={createSubmission}
                                className="space-y-6"
                            >
                                <SubmissionFields
                                    form={form}
                                    fileRequired
                                    idPrefix="create_"
                                />
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                    className="w-full bg-primary text-primary-foreground hover:bg-primary/90 sm:w-auto"
                                >
                                    <BookOpen className="mr-2 size-4" />
                                    Ajukan Sekarang
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {submission !== null && (
                    <div className="space-y-6">
                        <Card className={sectionCardClass}>
                            <CardHeader
                                className={`${sectionCardHeaderClass} gap-3`}
                            >
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <CardTitle>Status Pengajuan</CardTitle>
                                        <CardDescription>
                                            Perkembangan pengajuan Anda saat
                                            ini.
                                        </CardDescription>
                                    </div>
                                    <Badge className="w-fit bg-emerald-600 text-white hover:bg-emerald-600/90 dark:bg-emerald-500 dark:hover:bg-emerald-500/90">
                                        {label}
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent className="pb-6">
                                <Alert>
                                    {submission.workflow.key ===
                                    'title_review_pending' ? (
                                        <Clock className="size-4" />
                                    ) : (
                                        <CheckCircle2 className="size-4" />
                                    )}
                                    <AlertDescription>
                                        {description}
                                    </AlertDescription>
                                </Alert>
                            </CardContent>
                        </Card>

                        <Card className={sectionCardClass}>
                            <CardHeader
                                className={`${sectionCardHeaderClass} gap-3`}
                            >
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <CardTitle>Informasi Judul</CardTitle>
                                        <CardDescription>
                                            Judul dan ringkasan proposal
                                            terbaru.
                                        </CardDescription>
                                    </div>
                                    {!isEditing && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled={!canEditSubmission}
                                            onClick={() => setIsEditing(true)}
                                        >
                                            <Pencil className="mr-2 size-4" />
                                            Edit
                                        </Button>
                                    )}
                                </div>
                                {!canEditSubmission && (
                                    <p className="text-xs text-muted-foreground">
                                        Pengajuan yang sudah diproses tidak bisa
                                        diedit. Silakan hubungi admin jika perlu
                                        perubahan.
                                    </p>
                                )}
                            </CardHeader>
                            <CardContent className="pb-6">
                                {isEditing ? (
                                    <form
                                        onSubmit={updateSubmission}
                                        className="space-y-6"
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
                                                className="bg-primary text-primary-foreground hover:bg-primary/90"
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
                                                Batal
                                            </Button>
                                        </div>
                                    </form>
                                ) : (
                                    <div className="space-y-4">
                                        <div className="space-y-2">
                                            <p className="text-sm font-medium">
                                                Judul (Bahasa Indonesia)
                                            </p>
                                            <Input
                                                readOnly
                                                value={submission.title_id}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-sm font-medium">
                                                Judul (Bahasa Inggris)
                                            </p>
                                            <Input
                                                readOnly
                                                value={submission.title_en}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-sm font-medium">
                                                Ringkasan Proposal
                                            </p>
                                            <div className="rounded-md border bg-background px-3 py-2 text-sm whitespace-pre-wrap text-muted-foreground">
                                                {submission.proposal_summary}
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <ProposalFileCard submission={submission} />

                        <Card className={sectionCardClass}>
                            <CardHeader className={sectionCardHeaderClass}>
                                <CardTitle>
                                    Dosen Pembimbing dan Penguji
                                </CardTitle>
                                <CardDescription>
                                    Dosen yang terlibat pada sempro dan sidang.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6 pb-6">
                                <div className="grid gap-6 xl:grid-cols-3">
                                    <div className="space-y-3">
                                        <div>
                                            <p className="text-sm font-semibold">
                                                Dosen Pembimbing
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Pembimbing aktif
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

                                    <div className="space-y-3">
                                        <div>
                                            <p className="text-sm font-semibold">
                                                Dosen Penguji Sempro
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Penguji pada seminar proposal
                                                terbaru.
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

                                    <div className="space-y-3">
                                        <div>
                                            <p className="text-sm font-semibold">
                                                Dosen Penguji Sidang
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Penguji pada sidang terbaru.
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
                            </CardContent>
                        </Card>

                        {sidangResult ? (
                            <DefenseResultCard
                                title="Hasil Sidang Skripsi"
                                result={sidangResult}
                            />
                        ) : null}

                        <DefenseHistorySection
                            title="Riwayat Seminar Proposal"
                            items={defenseHistory.sempro}
                        />

                        <DefenseHistorySection
                            title="Riwayat Sidang Skripsi"
                            items={defenseHistory.sidang}
                        />
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
