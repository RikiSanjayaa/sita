<?php

namespace App\Enums;

enum ThesisSubmissionStatus: string
{
    case IntakeCreated = 'intake_created';
    case ProposalSubmitted = 'proposal_submitted';
    case SemproScheduled = 'sempro_scheduled';
    case SemproRevision = 'sempro_revision';
    case SemproApproved = 'sempro_approved';
    case MentorshipAssigned = 'mentorship_assigned';

    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases(),
        );
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::MentorshipAssigned,
        ], true);
    }
}
