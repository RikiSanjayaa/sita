<?php

namespace App\Support;

/**
 * Public Reverb settings the browser needs to open its WebSocket.
 *
 * Rendered into the root view at request time so one built asset bundle works
 * on any server. Only the public app key is exposed, never the app secret.
 */
final class ReverbClientConfig
{
    /**
     * @return array{key: ?string, host: ?string, port: int, scheme: string}
     */
    public static function toArray(): array
    {
        $app = (array) config('reverb.apps.apps.0', []);
        $options = (array) ($app['options'] ?? []);
        $scheme = (string) ($options['scheme'] ?? 'https');

        return [
            'key' => filled($app['key'] ?? null) ? (string) $app['key'] : null,
            'host' => filled($options['host'] ?? null) ? (string) $options['host'] : null,
            'port' => (int) ($options['port'] ?? ($scheme === 'https' ? 443 : 8080)),
            'scheme' => $scheme,
        ];
    }
}
