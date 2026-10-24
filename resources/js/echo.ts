import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo?: Echo<'reverb'>;
    }
}

type ReverbClientConfig = {
    key: string | null;
    host: string | null;
    port: number;
    scheme: string;
};

// The server renders this meta tag on every request (App\Support\ReverbClientConfig),
// so the same built bundle works against any Reverb host, port and app key.
function readReverbConfig(): ReverbClientConfig | null {
    const content = document
        .querySelector('meta[name="reverb-config"]')
        ?.getAttribute('content');

    if (!content) {
        return null;
    }

    try {
        const config = JSON.parse(content) as ReverbClientConfig;

        return config.key ? config : null;
    } catch {
        return null;
    }
}

if (typeof window !== 'undefined') {
    const config = readReverbConfig();

    if (config?.key) {
        window.Pusher = Pusher;

        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: config.key,
            wsHost: config.host || window.location.hostname,
            wsPort: config.port,
            wssPort: config.port,
            forceTLS: config.scheme === 'https',
            enabledTransports: ['ws', 'wss'],
        });
    } else {
        console.warn(
            'Reverb is not configured: set REVERB_APP_KEY, REVERB_HOST, REVERB_PORT and REVERB_SCHEME on the server.',
        );
    }
}

export {};
