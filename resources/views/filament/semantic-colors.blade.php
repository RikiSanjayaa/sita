{{--
    Pins Filament solid buttons and badges to the semantic tokens declared in
    App\Providers\Filament\AdminPanelProvider::SEMANTIC_COLORS (mirrors resources/css/app.css).
    Unlayered rules outrank Filament's layered utilities; only .fi-btn and .fi-badge are touched.
--}}
<style>
    @foreach ($roles as $role => $tokens)
        @php
            [$solid, $foreground, $muted, $mutedForeground] = $tokens['light'];
            [$darkSolid, $darkForeground, $darkMuted, $darkMutedForeground] = $tokens['dark'];
        @endphp
        .fi-btn.fi-color.fi-color-{{ $role }}:not(.fi-outlined) {
            --bg: {{ $solid }};
            --text: {{ $foreground }};
            --hover-bg: color-mix(in oklab, {{ $solid }} 88%, {{ $foreground }});
            --hover-text: {{ $foreground }};
            --dark-bg: {{ $darkSolid }};
            --dark-text: {{ $darkForeground }};
            --dark-hover-bg: color-mix(in oklab, {{ $darkSolid }} 88%, {{ $darkForeground }});
            --dark-hover-text: {{ $darkForeground }};
        }

        .fi-badge.fi-color.fi-color-{{ $role }} {
            --text: {{ $mutedForeground }};
            --dark-text: {{ $darkMutedForeground }};
            --tw-ring-color: color-mix(in oklab, {{ $mutedForeground }} 20%, transparent);
            background-color: {{ $muted }};
        }

        .dark .fi-badge.fi-color.fi-color-{{ $role }} {
            --tw-ring-color: color-mix(in oklab, {{ $darkMutedForeground }} 30%, transparent);
            background-color: {{ $darkMuted }};
        }
    @endforeach
</style>
