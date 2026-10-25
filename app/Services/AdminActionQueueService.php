<?php

namespace App\Services;

use App\Filament\Resources\ThesisProjects\ThesisProjectResource;
use App\Models\ThesisProject;
use App\Models\User;
use App\Support\AcademicTerminology;
use App\Support\WitaDateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The single definition of what needs an admin's action. The dashboard widget counts
 * these queues and the thesis project list filters by the same keys, so a row on the
 * dashboard and the list it opens always agree. Archived projects never qualify.
 */
class AdminActionQueueService
{
    public const FILTER_NAME = 'action_queue';

    private const ARCHIVE_WARNING_DAYS = 7;

    /**
     * Queue keys in priority order.
     *
     * @var list<string>
     */
    private const KEYS = [
        'title_review',
        'awaiting_finalization',
        'no_supervisor',
        'revision_overdue',
        'mentorship_lapsed',
        'archive_soon',
        'mentorship_unset',
        'phone_missing',
    ];

    /**
     * @return array<string, string> key => label, for the list filter
     */
    public function options(): array
    {
        return collect(self::KEYS)
            ->mapWithKeys(fn (string $key): array => [$key => $this->definition($key)['label']])
            ->all();
    }

    /**
     * Link to the thesis project list filtered to one queue.
     */
    public function url(string $key): string
    {
        return ThesisProjectResource::getUrl('index', [
            'filters' => [self::FILTER_NAME => ['value' => $key]],
        ]);
    }

    /**
     * Queues with at least one project, in priority order, scoped to the admin's program studi.
     *
     * @return Collection<int, array{key: string, label: string, description: string, action: string, color: string, icon: string, count: int, samples: string, url: string}>
     */
    public function items(?User $user): Collection
    {
        $items = collect(self::KEYS)
            ->map(function (string $key) use ($user): ?array {
                $count = $this->queueQuery($key, $user)->count();

                if ($count === 0) {
                    return null;
                }

                $definition = $this->definition($key);

                return [
                    'key' => $key,
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'action' => $definition['action'],
                    'color' => $definition['color'],
                    'icon' => $definition['icon'],
                    'count' => $count,
                    'samples' => '',
                    'url' => $this->url($key),
                ];
            })
            ->filter()
            ->values();

        $first = $items->first();

        if ($first !== null) {
            $items->put(0, [...$first, 'samples' => $this->preview($first['key'], $first['count'], $user)]);
        }

        return $items;
    }

    /**
     * One eligible student name for the featured queue, plus how many more it holds.
     */
    private function preview(string $key, int $count, ?User $user): string
    {
        $project = $this->queueQuery($key, $user)
            ->with('student:id,name')
            ->orderBy('thesis_projects.id')
            ->first();

        $rest = $count - 1;

        return ($project?->student?->name ?? '-').($rest > 0 ? ' dan '.$rest.' lainnya' : '');
    }

    public function queueQuery(string $key, ?User $user): Builder
    {
        $query = ThesisProject::query();

        if ($user?->adminProgramStudiId() !== null) {
            $query->where('thesis_projects.program_studi_id', $user->adminProgramStudiId());
        }

        return $this->applyQueue($query, $key);
    }

    /**
     * Narrows a thesis project query to one queue. Unknown keys leave it untouched.
     */
    public function applyQueue(Builder $query, ?string $key): Builder
    {
        $today = now(WitaDateTime::TIMEZONE)->toDateString();

        return match ($key) {
            'title_review' => $this->active($query)
                ->where('phase', 'title_review')
                ->whereHas('titles', fn (Builder $titles): Builder => $titles->where('status', 'submitted')),
            'awaiting_finalization' => $this->active($query)
                ->whereHas('defenses', fn (Builder $defenses): Builder => $defenses->where('status', 'awaiting_finalization')),
            'no_supervisor' => $this->active($query)
                ->whereIn('phase', ['research', 'sidang'])
                ->whereDoesntHave('activeSupervisorAssignments'),
            'revision_overdue' => $this->active($query)
                ->whereHas('revisions', fn (Builder $revisions): Builder => $revisions
                    ->whereIn('status', ['open', 'submitted'])
                    ->whereNotNull('due_at')
                    ->where('due_at', '<', now())),
            'mentorship_lapsed' => $this->active($query)
                ->whereNotNull('mentorship_valid_until')
                ->whereDate('mentorship_valid_until', '<', $today),
            'mentorship_unset' => $this->active($query)
                ->whereNull('mentorship_valid_until'),
            'archive_soon' => $query
                ->dueForArchive(now()->addDays(self::ARCHIVE_WARNING_DAYS)),
            'phone_missing' => $this->active($query)
                ->whereHas('student', fn (Builder $student): Builder => $student->where(function (Builder $phone): void {
                    $phone->whereNull('phone_number')
                        ->orWhere('phone_number', '')
                        ->orWhereNull('phone_number_confirmed_at');
                })),
            default => $query,
        };
    }

    private function active(Builder $query): Builder
    {
        return $query
            ->where('thesis_projects.state', 'active')
            ->whereNull('thesis_projects.archived_at');
    }

    /**
     * @return array{label: string, description: string, action: string, color: string, icon: string}
     */
    private function definition(string $key): array
    {
        $terms = AcademicTerminology::neutral();
        $finalWork = mb_strtolower($terms['finalWork']);

        return match ($key) {
            'title_review' => [
                'label' => 'Judul menunggu review',
                'description' => 'Mahasiswa sudah mengajukan judul dan menunggu keputusan admin.',
                'action' => 'Review',
                'color' => 'warning',
                'icon' => 'heroicon-m-document-magnifying-glass',
            ],
            'awaiting_finalization' => [
                'label' => 'Ujian menunggu finalisasi',
                'description' => 'Semua penguji sudah memberi keputusan. Hasil resmi menunggu admin.',
                'action' => 'Finalisasi',
                'color' => 'warning',
                'icon' => 'heroicon-m-clipboard-document-check',
            ],
            'no_supervisor' => [
                'label' => 'Pembimbing belum ditetapkan',
                'description' => 'Sudah lewat '.$terms['proposalExamShort'].', tetapi belum ada pembimbing aktif.',
                'action' => 'Tetapkan',
                'color' => 'danger',
                'icon' => 'heroicon-m-user-plus',
            ],
            'revision_overdue' => [
                'label' => 'Revisi melewati batas waktu',
                'description' => 'Batas waktu revisi sudah lewat dan revisi belum selesai.',
                'action' => 'Tinjau',
                'color' => 'danger',
                'icon' => 'heroicon-m-exclamation-triangle',
            ],
            'mentorship_lapsed' => [
                'label' => 'Masa aktif bimbingan habis',
                'description' => 'Bimbingan mahasiswa ditangguhkan sampai masa aktifnya diperpanjang.',
                'action' => 'Perpanjang',
                'color' => 'danger',
                'icon' => 'heroicon-m-arrow-path',
            ],
            'archive_soon' => [
                'label' => 'Akan diarsipkan dalam '.self::ARCHIVE_WARNING_DAYS.' hari',
                'description' => 'Data bimbingan '.$finalWork.' yang sudah selesai akan dihapus saat diarsipkan.',
                'action' => 'Tinjau',
                'color' => 'info',
                'icon' => 'heroicon-m-archive-box',
            ],
            'mentorship_unset' => [
                'label' => 'Masa aktif bimbingan belum diatur',
                'description' => 'Belum ada tanggal berlaku, jadi masa aktif tidak pernah habis.',
                'action' => 'Atur',
                'color' => 'gray',
                'icon' => 'heroicon-m-calendar-days',
            ],
            'phone_missing' => [
                'label' => 'Nomor HP belum ada atau belum dikonfirmasi',
                'description' => 'Mahasiswa aktif yang belum mengisi atau mengonfirmasi nomor HP-nya.',
                'action' => 'Lihat',
                'color' => 'gray',
                'icon' => 'heroicon-m-phone',
            ],
        };
    }
}
