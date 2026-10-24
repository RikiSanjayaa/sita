<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cache Store
    |--------------------------------------------------------------------------
    |
    | Filament passes notifications to the next request through the cache, so
    | the store has to be shared between requests. Leave it unset to use the
    | default cache store; Playwright sets it because its default is `array`.
    |
    */

    'cache_store' => env('FILAMENT_CACHE_STORE'),

];
