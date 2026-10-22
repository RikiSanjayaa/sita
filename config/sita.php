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

];
