<?php

namespace App\Services;

use App\Models\ThesisProject;
use Carbon\CarbonImmutable;

/**
 * Everything the archive command would delete for one completed project, computed
 * without touching any data. --dry-run prints this plan and archive() executes the
 * very same plan, so the two cannot disagree.
 */
final class ThesisArchivePlan
{
    /**
     * @param  list<int>  $threadIds  chat threads deleted together with their messages, read markers and participants
     * @param  list<int>  $messageIds  every chat message deleted (includes the messages of $threadIds)
     * @param  list<int>  $scheduleIds
     * @param  list<int>  $mentorshipDocumentIds
     * @param  list<int>  $thesisDocumentIds  everything except the kept final manuscript
     * @param  list<array{disk: string, path: string}>  $filesToDelete  not referenced by any remaining row
     * @param  list<array{disk: string, path: string}>  $filesKept  still referenced by a remaining row
     * @param  array<string, mixed>  $summary  written to thesis_projects.archive_summary before deleting
     * @param  array{threads: int, read_markers: int, participants: int}  $threadDetail
     */
    public function __construct(
        public readonly ThesisProject $project,
        public readonly ?CarbonImmutable $windowFrom,
        public readonly ?CarbonImmutable $windowTo,
        public readonly array $threadIds,
        public readonly array $messageIds,
        public readonly array $scheduleIds,
        public readonly array $mentorshipDocumentIds,
        public readonly array $thesisDocumentIds,
        public readonly ?int $keptFinalDocumentId,
        public readonly array $filesToDelete,
        public readonly array $filesKept,
        public readonly array $summary,
        public readonly array $threadDetail,
    ) {}

    /**
     * Counts recorded on the project_archived event and printed by --dry-run.
     *
     * @return array{chat_threads: int, chat_messages: int, chat_read_markers: int, chat_participants: int, mentorship_schedules: int, mentorship_documents: int, thesis_documents: int, files_deleted: int, files_kept_shared: int}
     */
    public function counts(): array
    {
        return [
            'chat_threads' => count($this->threadIds),
            'chat_messages' => count($this->messageIds),
            'chat_read_markers' => $this->threadDetail['read_markers'],
            'chat_participants' => $this->threadDetail['participants'],
            'mentorship_schedules' => count($this->scheduleIds),
            'mentorship_documents' => count($this->mentorshipDocumentIds),
            'thesis_documents' => count($this->thesisDocumentIds),
            'files_deleted' => count($this->filesToDelete),
            'files_kept_shared' => count($this->filesKept),
        ];
    }
}
