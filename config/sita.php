<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Minimum Mentorship Sessions
    |--------------------------------------------------------------------------
    |
    | Number of completed mentorship sessions each active supervisor should
    | have with the student before the final defense is scheduled. Falling
    | short only raises a warning; scheduling is never blocked.
    |
    */

    'minimum_mentorship_sessions' => (int) env('SITA_MINIMUM_MENTORSHIP_SESSIONS', 6),

    /*
    |--------------------------------------------------------------------------
    | Thesis Ticket Duration (months)
    |--------------------------------------------------------------------------
    |
    | How long a thesis project's mentorship ticket stays valid after the first
    | supervisor is assigned, and how far each renewal extends it. Once the
    | validity date has passed, mentorship activity is suspended until an admin
    | renews the ticket.
    |
    */

    'thesis_ticket_months' => (int) env('SITA_THESIS_TICKET_MONTHS', 12),

    /*
    |--------------------------------------------------------------------------
    | Completed Thesis Archive
    |--------------------------------------------------------------------------
    |
    | Once a thesis has been completed for archive_grace_days, the
    | sita:archive-completed-theses command keeps the summary data and the
    | final manuscript and permanently deletes mentorship chat, schedules and
    | draft documents. Deletion is irreversible, so the daily schedule entry is
    | registered only when archive_enabled is true. Keep it false in production
    | until a database backup has been confirmed. The command can still be run
    | by hand (use --dry-run first).
    |
    */

    'archive_grace_days' => (int) env('SITA_ARCHIVE_GRACE_DAYS', 30),

    'archive_enabled' => (bool) env('SITA_ARCHIVE_ENABLED', false),

];
