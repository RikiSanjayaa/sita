<?php

namespace App\Services;

use App\Models\ThesisProject;
use App\Support\WitaDateTime;

class MentorshipTicketService
{
    /**
     * The student's current thesis project (same notion as ThesisProject::current()),
     * whose masa aktif bimbingan (mentorship validity) governs mentorship activity. Null when the current one is not active.
     */
    public function activeProjectForStudent(int $studentUserId): ?ThesisProject
    {
        return ThesisProject::query()
            ->current()
            ->where('student_user_id', $studentUserId)
            ->where('state', 'active')
            ->first();
    }

    /**
     * The reason a student's mentorship activity is blocked, or null while it is allowed.
     */
    public function suspensionMessageForStudent(int $studentUserId, bool $addressStudent = true): ?string
    {
        $project = $this->activeProjectForStudent($studentUserId);

        return $project?->isMentorshipSuspended() ? self::suspensionMessage($project, $addressStudent) : null;
    }

    /**
     * Wording for the student (banner, blocked requests) or, with $addressStudent false,
     * for a dosen acting on that student's behalf.
     */
    public static function suspensionMessage(ThesisProject $project, bool $addressStudent = true): string
    {
        $until = WitaDateTime::translated($project->mentorship_valid_until, 'j F Y', withLabel: false);

        if (! $addressStudent) {
            return "Masa aktif bimbingan mahasiswa berakhir pada {$until}. Penjadwalan bimbingan ditangguhkan sampai admin program studi memperpanjangnya.";
        }

        return "Masa aktif bimbingan Anda berakhir pada {$until}. Pengajuan jadwal bimbingan, pesan, dan unggah dokumen ditangguhkan sampai diperpanjang. Hubungi admin program studi untuk perpanjangan.";
    }
}
