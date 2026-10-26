<?php

namespace App\Http\Requests\Settings;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use NotificationChannels\WebPush\PushSubscription;

class StorePushSubscriptionRequest extends FormRequest
{
    /**
     * Push services the server is willing to deliver to (exact host or a subdomain).
     *
     * @var list<string>
     */
    public const ALLOWED_PUSH_HOSTS = [
        'fcm.googleapis.com',
        'jmt17.google.com',
        'updates.push.services.mozilla.com',
        'web.push.apple.com',
    ];

    /** Hosts that must match exactly (no shard subdomains). */
    private const EXACT_ONLY_HOSTS = ['fcm.googleapis.com', 'jmt17.google.com'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'endpoint' => [
                'required',
                'string',
                'max:'.PushSubscription::ENDPOINT_MAX_LENGTH,
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! self::isAllowedEndpoint($value)) {
                        $fail('Endpoint push tidak didukung.');
                    }
                },
            ],
            'keys' => ['required', 'array'],
            'keys.p256dh' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $raw = is_string($value) ? self::decodeBase64Url($value) : null;

                    if ($raw === null || strlen($raw) !== 65 || $raw[0] !== "\x04") {
                        $fail('Kunci publik push tidak valid.');
                    }
                },
            ],
            'keys.auth' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $raw = is_string($value) ? self::decodeBase64Url($value) : null;

                    if ($raw === null || strlen($raw) !== 16) {
                        $fail('Rahasia autentikasi push tidak valid.');
                    }
                },
            ],
            'contentEncoding' => ['nullable', 'string', 'in:aes128gcm'],
        ];
    }

    public function endpoint(): string
    {
        return (string) $this->validated('endpoint');
    }

    public static function isAllowedEndpoint(string $endpoint): bool
    {
        if ($endpoint === '' || strlen($endpoint) > PushSubscription::ENDPOINT_MAX_LENGTH) {
            return false;
        }

        // Printable ASCII only: no whitespace, control chars, backslashes, or fragments.
        if (preg_match('/\A[\x21-\x7E]+\z/', $endpoint) !== 1 || str_contains($endpoint, '\\') || str_contains($endpoint, '#')) {
            return false;
        }

        if (preg_match('#\Ahttps://([a-z0-9.-]+)(/[^?\#]*)?(\?.*)?\z#', $endpoint, $matches) !== 1) {
            return false;
        }

        $host = $matches[1];

        // Authority was matched strictly above (no userinfo, no port); reject IPs and malformed labels.
        if (preg_match('/\A([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]([a-z0-9-]*[a-z0-9])?\z/', $host) !== 1) {
            return false;
        }

        $parsed = parse_url($endpoint);

        if ($parsed === false || ($parsed['host'] ?? null) !== $host
            || isset($parsed['user']) || isset($parsed['pass']) || isset($parsed['port']) || isset($parsed['fragment'])) {
            return false;
        }

        foreach (self::ALLOWED_PUSH_HOSTS as $allowed) {
            if ($host === $allowed) {
                return true;
            }

            if (! in_array($allowed, self::EXACT_ONLY_HOSTS, true) && str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }

    private static function decodeBase64Url(string $value): ?string
    {
        if (preg_match('/\A[A-Za-z0-9_-]+\z/', $value) !== 1) {
            return null;
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
