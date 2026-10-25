<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $theme ?? 'ubg-blue' }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="reverb-config" content="{{ json_encode(\App\Support\ReverbClientConfig::toArray()) }}">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- The cookie sets data-theme on the server. When it is missing, fall back to the stored scheme before first paint. --}}
        <script>
            (function() {
                try {
                    const stored = localStorage.getItem('sita_theme_preset');

                    if (@json($themeSchemes ?? []).includes(stored)) {
                        document.documentElement.dataset.theme = stored;
                    }
                } catch (error) {
                    // Storage is blocked; the server value stays.
                }
            })();
        </script>

        {{-- Page background before app.css loads; the scheme tokens live in app.css --}}
        <style>
            html {
                background-color: var(--background, #f8fafc);
            }

            html.dark {
                background-color: var(--background, #0b0f14);
            }
        </style>

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <meta name="theme-color" media="(prefers-color-scheme: light)" content="#f8fafc">
        <meta name="theme-color" media="(prefers-color-scheme: dark)" content="#0b0f14">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="SITA">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @viteReactRefresh
        @vite('resources/js/app.tsx')
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
