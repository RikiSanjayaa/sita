<?php

use App\Support\ReverbClientConfig;

function reverbMetaPayload(string $html): array
{
    preg_match('/<meta name="reverb-config" content="([^"]*)">/', $html, $matches);

    return json_decode(html_entity_decode($matches[1] ?? ''), true) ?? [];
}

it('renders the runtime Reverb config from server settings without the secret', function () {
    config([
        'reverb.apps.apps.0.key' => 'runtime-key',
        'reverb.apps.apps.0.secret' => 'super-secret-value',
        'reverb.apps.apps.0.options.host' => 'ws.kampus.test',
        'reverb.apps.apps.0.options.port' => 443,
        'reverb.apps.apps.0.options.scheme' => 'https',
    ]);

    $response = $this->get(route('login'))->assertOk();

    expect(reverbMetaPayload($response->getContent()))->toBe([
        'key' => 'runtime-key',
        'host' => 'ws.kampus.test',
        'port' => 443,
        'scheme' => 'https',
    ]);
    $response->assertDontSee('super-secret-value');
});

it('follows a different server configuration with the same code', function () {
    config([
        'reverb.apps.apps.0.key' => 'other-key',
        'reverb.apps.apps.0.options.host' => '127.0.0.1',
        'reverb.apps.apps.0.options.port' => 9011,
        'reverb.apps.apps.0.options.scheme' => 'http',
    ]);

    expect(ReverbClientConfig::toArray())->toBe([
        'key' => 'other-key',
        'host' => '127.0.0.1',
        'port' => 9011,
        'scheme' => 'http',
    ]);
});

it('does not pass the browser the internal broadcast host', function () {
    config([
        'reverb.apps.apps.0.options.host' => 'public.test',
        'broadcasting.connections.reverb.options.host' => '127.0.0.1',
    ]);

    expect(ReverbClientConfig::toArray()['host'])->toBe('public.test');
});
