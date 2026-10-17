<?php

it('uses the configured Reverb origins instead of a fixed wildcard', function () {
    $key = 'REVERB_ALLOWED_ORIGINS';
    $previousServer = $_SERVER[$key] ?? null;
    $previousEnv = $_ENV[$key] ?? null;
    $previousPutenv = getenv($key);

    // Laravel reads $_SERVER and $_ENV before putenv, and .env may already define the key.
    $value = 'sita.example.test, admin.example.test';
    $_SERVER[$key] = $value;
    $_ENV[$key] = $value;
    putenv("{$key}={$value}");

    try {
        $reverb = require base_path('config/reverb.php');

        expect($reverb['apps']['apps'][0]['allowed_origins'])
            ->toBe(['sita.example.test', 'admin.example.test']);
    } finally {
        if ($previousServer === null) {
            unset($_SERVER[$key]);
        } else {
            $_SERVER[$key] = $previousServer;
        }

        if ($previousEnv === null) {
            unset($_ENV[$key]);
        } else {
            $_ENV[$key] = $previousEnv;
        }

        putenv($previousPutenv === false ? $key : "{$key}={$previousPutenv}");
    }
});
