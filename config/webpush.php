<?php

use NotificationChannels\WebPush\PushSubscription;

return [
    'enabled' => env('WEB_PUSH_ENABLED', false),

    'vapid' => [
        'subject' => env('VAPID_SUBJECT'),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'pem_file' => null,
    ],

    'model' => PushSubscription::class,
    'table_name' => 'push_subscriptions',
    'database_connection' => null,
    'client_options' => ['timeout' => 15, 'connect_timeout' => 5],
    'automatic_padding' => true,

    // Push must stay asynchronous even when another channel uses the sync driver.
    'queue_connection' => 'database',
    'queue' => 'default',
];
