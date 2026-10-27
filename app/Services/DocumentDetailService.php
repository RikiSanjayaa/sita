<?php

namespace App\Services;

use App\Models\MentorshipChatThread;
use App\Models\MentorshipDocument;
use App\Models\ThesisDefense;
use App\Models\ThesisDocument;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Support\AcademicTerminology;
use App\Support\WitaDateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DocumentDetailService
{
    public function __construct(
        private readonly DocumentAccessService $access,
        private readonly MentorshipAccessService $chatAccess,
        private readonly SecretSidangExaminers $secretExaminers,
    ) {}

    public static function workspaceUrl(MentorshipDocument $document, string $role): string
    {
        return route(match ($role) {
            'dosen' => 'dosen.dokumen-revisi.show',
            'mahasiswa' => 'mahasiswa.upload-dokumen.show',
            default => 'kaprodi.dokumen.workspace.show',
        }, ['document' => $document->id]);
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'approved' => 'Disetujui',
            'needs_revision' => 'Perlu Revisi',
            'revision_completed' => 'Revisi selesai',
            'partially_reviewed' => 'Sebagian direview',
            'stored', 'active' => 'Tersimpan',
            default => 'Menunggu Review',
        };
    }

    /** @param Collection<int, MentorshipDocument> $copies */
    public static function aggregateStatus(Collection $copies): string
    {
        $reviews = $copies->whereNotNull('lecturer_user_id');

        return match (true) {
            $reviews->isEmpty() => 'stored',
            $reviews->contains(fn (MentorshipDocument $copy): bool => DocumentRevisionService::displayStatus($copy) === 'needs_revision') => 'needs_revision',
            $reviews->contains('status', 'needs_revision') => 'revision_completed',
            $reviews->every(fn (MentorshipDocument $copy): bool => $copy->status === 'approved') => 'approved',
            $reviews->contains('status', 'approved') => 'partially_reviewed',
            default => 'submitted',
        };
    }

    /** @return array<int, string> */
    public function reviewerRoles(User $student): array
    {
        $terms = AcademicTerminology::forStudent($student);
        $roles = [];
        $defenses = ThesisDefense::query()->with('examiners')
            ->whereHas('project', fn ($query) => $query->where('student_user_id', $student->id))
            ->where(fn ($query) => $query->where('status', 'scheduled')->orWhere(fn ($query) => $query->where('status', 'completed')->where('result', 'pass_with_revision')))->get();
        foreach ($defenses as $defense) {
            foreach ($defense->examiners as $examiner) {
                $roles[(int) $examiner->lecturer_user_id] ??= 'Penguji '.($defense->type === 'sidang' ? $terms['finalExamShort'] : $terms['proposalExamShort']);
            }
        }
        $assignments = ThesisSupervisorAssignment::query()->whereHas('project', fn ($query) => $query->where('student_user_id', $student->id)->where('state', 'active'))
            ->where('status', 'active')->get();
        foreach ($assignments as $assignment) {
            $roles[(int) $assignment->lecturer_user_id] = match ($assignment->role) {
                'primary' => 'Pembimbing 1', 'secondary' => 'Pembimbing 2', default => 'Dosen',
            };
        }

        return $roles;
    }

    public function copies(MentorshipDocument $document): Builder
    {
        return MentorshipDocument::query()->where('student_user_id', $document->student_user_id)
            ->when($document->document_group === null, fn ($query) => $query->whereKey($document->id),
                fn ($query) => $query->where('document_group', $document->document_group));
    }

    /** @return array<string, mixed> */
    public function workspace(User $viewer, MentorshipDocument $document, string $role): array
    {
        abort_unless($this->access->workspace($viewer, $document, $role), 403);
        $document->loadMissing(['student.mahasiswaProfile', 'lecturer']);
        $copies = $this->copies($document)->with('lecturer')->where('version_number', $document->version_number)->get();
        $status = $role === 'dosen' ? DocumentRevisionService::displayStatus($document) : self::aggregateStatus($copies);
        $reason = $role === 'dosen' ? $this->access->reviewReadOnlyReason($viewer, $document) : 'Dokumen ini hanya dapat dibaca.';
        $download = $this->access->workspace($viewer, $document, $role, true);
        $fileParameters = ['document' => $document->id] + ($role === 'kaprodi' ? ['portal' => 'kaprodi'] : []);
        $fileUrl = $document->storage_path !== null
            ? route('files.documents.download', $fileParameters) : $document->file_url;
        $versions = $this->copies($document)->when($role === 'dosen', fn ($query) => $query->where('lecturer_user_id', $viewer->id))
            ->latest('version_number')->orderBy('id')->get()->groupBy('version_number')
            ->map(function (Collection $copies) use ($role): array {
                $copy = $copies->firstWhere('lecturer_user_id', null) ?? $copies->first();
                $status = $role === 'dosen' ? DocumentRevisionService::displayStatus($copy) : self::aggregateStatus($copies);

                return [
                    'id' => $copy->id, 'version' => $copy->category === 'lampiran-chat' ? null : $copy->version_number,
                    'fileName' => $copy->file_name, 'uploadedAt' => WitaDateTime::formatOrNull($copy->created_at),
                    'status' => $status, 'statusLabel' => self::statusLabel($status), 'url' => self::workspaceUrl($copy, $role),
                ];
            })->values()->all();
        $roles = $document->student === null ? [] : $this->reviewerRoles($document->student);
        $canResolve = $role === 'mahasiswa' && app(DocumentRevisionService::class)->canResolve($viewer, $document);
        $reviews = $copies->whereNotNull('lecturer_user_id')->values()->map(function (MentorshipDocument $copy, int $index) use ($role, $roles, $canResolve): array {
            $secretLabel = $role === 'mahasiswa' ? $this->secretExaminers->labelFor((int) $copy->student_user_id, (int) $copy->lecturer_user_id) : null;

            return [
                'id' => $role === 'mahasiswa' ? $index + 1 : $copy->id,
                'reviewer' => $secretLabel ?? $copy->lecturer?->name ?? '-',
                'role' => $secretLabel ?? $roles[$copy->lecturer_user_id] ?? 'Dosen',
                'status' => DocumentRevisionService::displayStatus($copy), 'notes' => $copy->revision_notes,
                'revisionItems' => array_map(fn (array $item): array => [
                    'id' => $item['id'], 'description' => $item['description'],
                    'resolved' => $item['resolved_at'] !== null,
                    'resolvedAt' => WitaDateTime::formatOrNull($item['resolved_at'] === null ? null : \Carbon\CarbonImmutable::parse($item['resolved_at'])),
                    'resolveUrl' => $canResolve && $copy->status === 'needs_revision'
                        ? route('mahasiswa.upload-dokumen.revisions.update', ['document' => $copy->id, 'revision' => $item['id']]) : null,
                ], $copy->revision_items ?? []),
                'reviewedAt' => WitaDateTime::formatOrNull($copy->reviewed_at),
            ];
        })->all();

        return [
            'role' => $role,
            'document' => [
                'id' => $document->id, 'source' => 'workspace', 'title' => $document->title,
                'fileName' => $document->file_name, 'category' => $document->category ?? '-',
                'version' => $document->category === 'lampiran-chat' ? null : $document->version_number,
                'uploadedAt' => WitaDateTime::formatOrNull($document->created_at),
                'studentName' => $document->student?->name ?? '-', 'studentNim' => $document->student?->mahasiswaProfile?->nim,
                'status' => $status, 'statusLabel' => self::statusLabel($status),
                'downloadUrl' => $download ? $fileUrl : null,
                'viewUrl' => $download ? ($document->storage_path === null ? $fileUrl : route('files.documents.download', $fileParameters + ['inline' => 1])) : null,
                'reviews' => $reviews, 'versions' => $versions, 'canReview' => $reason === null,
                'reviewUrl' => $reason === null ? route('dosen.dokumen-revisi.review', ['document' => $document->id]) : null,
                'reviewNotes' => $role === 'dosen' ? ($document->revision_notes ?? '') : '',
                'reviewItems' => $role === 'dosen' ? ($document->revision_items ?? []) : [],
                'reviewDetailUrl' => null,
            ],
            'navigation' => $this->navigation($viewer, $role, (int) $document->student_user_id, $document),
        ];
    }

    /** @return array<string, mixed> */
    public function thesis(User $viewer, ThesisDocument $document, string $role): array
    {
        abort_unless($this->access->thesis($viewer, $document, $role, false), 403);
        $document->loadMissing(['project.student.mahasiswaProfile', 'sourceWorkspaceDocument']);
        $source = $document->sourceWorkspaceDocument;
        if ($source !== null && $source->student_user_id !== $document->project?->student_user_id) {
            $source = null;
        }
        // The snapshot may point at a different reviewer's copy. Resolve only the viewer's exact version.
        $reviewCopy = $source === null ? null : $this->copies($source)->where('version_number', $source->version_number)
            ->when($role === 'dosen', fn ($query) => $query->where('lecturer_user_id', $viewer->id))->orderBy('id')->first();
        $reviewUrl = $reviewCopy !== null && $this->access->workspace($viewer, $reviewCopy, $role)
            ? self::workspaceUrl($reviewCopy, $role) : null;
        $download = $this->access->thesis($viewer, $document, $role, true);
        $fileParameters = ['document' => $document->id] + ($role === 'kaprodi' ? ['portal' => 'kaprodi'] : []);
        $detailUrl = route($role === 'dosen' ? 'dosen.berkas-ujian.show' : 'kaprodi.dokumen.thesis.show', ['document' => $document->id]);
        $student = $document->project?->student;
        $reviews = $reviewUrl === null ? [] : $this->workspace($viewer, $reviewCopy, $role)['document']['reviews'];

        return [
            'role' => $role,
            'document' => [
                'id' => $document->id, 'source' => 'thesis', 'title' => $document->title,
                'fileName' => $document->file_name, 'category' => $document->kind,
                'version' => $source?->version_number ?? $document->version_no,
                'uploadedAt' => WitaDateTime::formatOrNull($source?->created_at ?? $document->uploaded_at),
                'studentName' => $student?->name ?? '-', 'studentNim' => $student?->mahasiswaProfile?->nim,
                'status' => $document->status, 'statusLabel' => self::statusLabel($document->status),
                'downloadUrl' => $download ? route('files.thesis-documents.download', $fileParameters) : null,
                'viewUrl' => $download ? route('files.thesis-documents.download', $fileParameters + ['inline' => 1]) : null,
                'reviews' => $reviews,
                'versions' => [[
                    'id' => $document->id, 'version' => $source?->version_number ?? $document->version_no,
                    'fileName' => $document->file_name, 'uploadedAt' => WitaDateTime::formatOrNull($source?->created_at ?? $document->uploaded_at),
                    'status' => $document->status, 'statusLabel' => self::statusLabel($document->status), 'url' => $detailUrl,
                ]],
                'canReview' => false, 'reviewUrl' => null, 'reviewNotes' => '',
                'reviewItems' => [],
                'reviewDetailUrl' => $reviewUrl,
            ],
            'navigation' => $this->navigation($viewer, $role, (int) $student?->id, null, $document->defense_id),
        ];
    }

    /** @return array<string, string|null> */
    private function navigation(User $viewer, string $role, int $studentId, ?MentorshipDocument $document = null, ?int $defenseId = null): array
    {
        $threads = $role === 'kaprodi' ? collect() : MentorshipChatThread::query()->where('student_user_id', $studentId)
            ->when($defenseId !== null, fn ($query) => $query->whereIn('type', ['sempro', 'sidang'])->where('context_id', $defenseId))
            ->when($document !== null, fn ($query) => $query->whereHas('messages.relatedDocument', fn ($query) => $query
                ->whereIn('id', $this->copies($document)->where('version_number', $document->version_number)->select('id'))))
            ->orderBy('id')->get();
        $thread = $threads->first(fn (MentorshipChatThread $thread): bool => $this->chatAccess->canAccessThread($viewer, $thread));

        return [
            'listUrl' => match ($role) {
                'dosen' => $defenseId === null ? '/dosen/dokumen-revisi' : '/dosen/seminar-proposal?defense='.$defenseId, 'mahasiswa' => '/mahasiswa/upload-dokumen', default => '/kaprodi/dokumen'
            },
            'listLabel' => $defenseId !== null && $role === 'dosen' ? 'Kembali ke ujian' : 'Kembali ke dokumen',
            'chatUrl' => $thread === null ? null : '/'.$role.'/pesan?thread='.$thread->id,
            'chatLabel' => $thread === null ? null : 'Buka percakapan',
        ];
    }
}
