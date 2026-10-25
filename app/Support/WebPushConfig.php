<?php

namespace App\Support;

final class WebPushConfig
{
    public static function enabled(): bool
    {
        return config('webpush.enabled') === true
            && filled(config('webpush.vapid.subject'))
            && filled(config('webpush.vapid.public_key'))
            && filled(config('webpush.vapid.private_key'));
    }
}
