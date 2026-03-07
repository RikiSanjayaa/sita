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
import { FormEventHandler, useEffect, useState } from 'react';

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
import { dashboard, tugasAkhir } from '@/routes';
import { type BreadcrumbItem, type SharedData } from '@/types';

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
    semproDate: string | null;
    sidangDate: string | null;
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
    { title: 'Judul dan Proposal', href: tugasAkhir().url },
];

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

function AssignmentValue({
    value,
    placeholder,
}: {
    value: string | null;
    placeholder: string;
}) {
    if (value !== null && value.trim() !== '') {
        return <p className="text-sm font-medium">{value}</p>;
    }

    return <p className="text-sm text-muted-foreground">{placeholder}</p>;
}

function ProposalFileCard({ submission }: { submission: Submission }) {
    if (
        submission.proposal_file_download_url === null ||
        submission.proposal_file_view_url === null
    ) {
        return (
            <Card>
                <CardHeader>
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
        <Card>
            <CardHeader>
                <CardTitle>File Proposal Terkirim</CardTitle>
                <CardDescription>
                    Anda dapat melihat dan mengunduh ulang file proposal.
                </CardDescription>
            </CardHeader>
            <CardContent>
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

export default function TugasAkhirSaya() {
    const {
        submission,
        assignedLecturers,
        semproDate,
        sidangDate,
        flashMessage,
        errorMessage,
    } = usePage<SharedData & TugasAkhirPageProps>().props;
    const [isEditing, setIsEditing] = useState(false);
    const form = useForm<FormData>(submissionDefaults(submission));

    useEffect(() => {
        if (submission) {
            form.setData(submissionDefaults(submission));
        }
    }, [submission]);

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
            <Head title="Judul dan Proposal" />
            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-6">
                <div>
                    <h1 className="text-xl font-semibold">
                        Judul dan Proposal
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Kelola judul, proposal, dan informasi penugasan skripsi
                        Anda.
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
                    <Card>
                        <CardHeader>
                            <CardTitle>Ajukan Judul & Proposal</CardTitle>
                            <CardDescription>
                                Isi formulir di bawah ini untuk mengajukan judul
                                dan proposal skripsi.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
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
                        <Card>
                            <CardHeader className="gap-3">
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <CardTitle>Status Pengajuan</CardTitle>
                                        <CardDescription>
                                            Status proses judul dan proposal
                                            skripsi Anda.
                                        </CardDescription>
                                    </div>
                                    <Badge className="w-fit bg-emerald-600 text-white hover:bg-emerald-600/90 dark:bg-emerald-500 dark:hover:bg-emerald-500/90">
                                        {label}
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent>
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

                        <Card>
                            <CardHeader className="gap-3">
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <CardTitle>Informasi Judul</CardTitle>
                                        <CardDescription>
                                            Detail judul dan ringkasan proposal
                                            yang Anda kirim.
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
                            <CardContent>
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

                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    Dosen Pembimbing, Penguji, dan Jadwal Ujian
                                </CardTitle>
                                <CardDescription>
                                    Penetapan dosen pembimbing, dosen penguji,
                                    dan jadwal Sempro maupun sidang dikelola
                                    admin.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2 rounded-lg border p-4">
                                        <p className="text-sm font-semibold">
                                            Dosen Pembimbing 1
                                        </p>
                                        <AssignmentValue
                                            value={
                                                assignedLecturers.pembimbing1
                                            }
                                            placeholder="Belum ditetapkan. Admin akan menetapkan dosen pembimbing."
                                        />
                                    </div>
                                    <div className="space-y-2 rounded-lg border p-4">
                                        <p className="text-sm font-semibold">
                                            Dosen Pembimbing 2
                                        </p>
                                        <AssignmentValue
                                            value={
                                                assignedLecturers.pembimbing2
                                            }
                                            placeholder="Belum ditetapkan. Admin akan menetapkan dosen pembimbing."
                                        />
                                    </div>
                                    <div className="space-y-2 rounded-lg border p-4">
                                        <p className="text-sm font-semibold">
                                            Dosen Penguji 1
                                        </p>
                                        <AssignmentValue
                                            value={assignedLecturers.penguji1}
                                            placeholder="Belum ditetapkan. Admin akan menetapkan dosen penguji."
                                        />
                                    </div>
                                    <div className="space-y-2 rounded-lg border p-4">
                                        <p className="text-sm font-semibold">
                                            Dosen Penguji 2
                                        </p>
                                        <AssignmentValue
                                            value={assignedLecturers.penguji2}
                                            placeholder="Belum ditetapkan. Admin akan menetapkan dosen penguji."
                                        />
                                    </div>
                                    <div className="space-y-2 rounded-lg border p-4">
                                        <p className="text-sm font-semibold">
                                            Ketua Sidang
                                        </p>
                                        <AssignmentValue
                                            value={
                                                assignedLecturers.ketuaSidang
                                            }
                                            placeholder="Belum ditetapkan. Admin akan menetapkan ketua sidang."
                                        />
                                    </div>
                                    <div className="space-y-2 rounded-lg border p-4">
                                        <p className="text-sm font-semibold">
                                            Sekretaris Sidang
                                        </p>
                                        <AssignmentValue
                                            value={
                                                assignedLecturers.sekretarisSidang
                                            }
                                            placeholder="Belum ditetapkan. Admin akan menetapkan sekretaris sidang."
                                        />
                                    </div>
                                    <div className="space-y-2 rounded-lg border p-4">
                                        <p className="text-sm font-semibold">
                                            Penguji Sidang
                                        </p>
                                        <AssignmentValue
                                            value={
                                                assignedLecturers.pengujiSidang
                                            }
                                            placeholder="Belum ditetapkan. Admin akan menetapkan penguji sidang."
                                        />
                                    </div>
                                </div>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2 rounded-lg border p-4">
                                        <p className="text-sm font-semibold">
                                            Tanggal Sempro
                                        </p>
                                        <AssignmentValue
                                            value={semproDate}
                                            placeholder="Belum dijadwalkan. Admin akan menetapkan jadwal Sempro."
                                        />
                                    </div>
                                    <div className="space-y-2 rounded-lg border p-4">
                                        <p className="text-sm font-semibold">
                                            Tanggal Sidang
                                        </p>
                                        <AssignmentValue
                                            value={sidangDate}
                                            placeholder="Belum dijadwalkan. Admin akan menetapkan jadwal sidang."
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
