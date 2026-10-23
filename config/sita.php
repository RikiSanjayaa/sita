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
    | Inactive Student Threshold
    |--------------------------------------------------------------------------
    |
    | Days without recorded progress (completed bimbingan, a student's chat
    | message, or a student's document upload) before a student is flagged as
    | inactive on progress cards and the Progres Mahasiswa page.
    |
    */

    'inactive_after_days' => (int) env('SITA_INACTIVE_AFTER_DAYS', 30),

];
