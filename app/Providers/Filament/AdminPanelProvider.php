<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\RequestPasswordReset;
use App\Filament\Pages\Auth\ResetPassword;
use App\Http\Middleware\StartSessionWithoutBackgroundWrites;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Platform;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    /**
     * Semantic color roles rendered by resources/views/filament/semantic-colors.blade.php.
     *
     * Values are copied from the --primary/--success/--warning/--danger/--info token
     * families in resources/css/app.css. The admin panel does not load that stylesheet,
     * so PHP, TS and CSS keep separate copies: change them together. Each role lists
     * [solid, foreground, muted, muted-foreground] for light and dark mode.
     *
     * @var array<string, array{hue: int, chroma: float, light: array<int, string>, dark: array<int, string>}>
     */
    public const SEMANTIC_COLORS = [
        'primary' => [
            'hue' => 250,
            'chroma' => 0.08,
            'light' => ['#2563eb', '#ffffff', '#edf2fe', '#2563eb'],
            'dark' => ['#60a5fa', '#0f172a', '#1a2a3d', '#93c5fd'],
        ],
        'success' => [
            'hue' => 160,
            'chroma' => 0.12,
            'light' => ['oklch(0.5 0.12 160)', 'oklch(0.98 0.01 160)', 'oklch(0.95 0.05 160)', 'oklch(0.4 0.1 160)'],
            'dark' => ['oklch(0.7 0.15 160)', 'oklch(0.2 0.04 160)', 'oklch(0.3 0.06 160)', 'oklch(0.85 0.12 160)'],
        ],
        'warning' => [
            'hue' => 80,
            'chroma' => 0.12,
            'light' => ['oklch(0.8 0.16 80)', 'oklch(0.25 0.05 70)', 'oklch(0.96 0.07 90)', 'oklch(0.42 0.09 70)'],
            'dark' => ['oklch(0.8 0.16 80)', 'oklch(0.22 0.05 70)', 'oklch(0.32 0.06 80)', 'oklch(0.88 0.12 85)'],
        ],
        'danger' => [
            'hue' => 27,
            'chroma' => 0.18,
            'light' => ['oklch(0.55 0.2 27)', 'oklch(0.98 0 0)', 'oklch(0.95 0.04 20)', 'oklch(0.45 0.18 27)'],
            'dark' => ['oklch(0.68 0.19 25)', 'oklch(0.18 0.03 25)', 'oklch(0.32 0.09 25)', 'oklch(0.85 0.1 25)'],
        ],
        'info' => [
            'hue' => 235,
            'chroma' => 0.12,
            'light' => ['oklch(0.5 0.13 235)', 'oklch(0.98 0.01 235)', 'oklch(0.95 0.04 235)', 'oklch(0.42 0.11 240)'],
            'dark' => ['oklch(0.72 0.13 235)', 'oklch(0.18 0.03 235)', 'oklch(0.3 0.06 235)', 'oklch(0.86 0.09 235)'],
        ],
    ];

    /**
     * Filament palette for a status role. Shade 50 is the muted surface, 400 the dark
     * solid, 500 the light solid and 600 the light muted-foreground, so text, icons and
     * links that Filament draws from shades 400/600 follow the semantic tokens. Buttons
     * and badges are pinned exactly by the style view.
     *
     * @return array<int, string>
     */
    private static function statusPalette(string $role): array
    {
        $tokens = self::SEMANTIC_COLORS[$role];
        $hue = $tokens['hue'];
        $chroma = $tokens['chroma'];

        return [
            50 => $tokens['light'][2],
            100 => sprintf('oklch(0.92 %.3f %d)', $chroma * 0.4, $hue),
            200 => sprintf('oklch(0.86 %.3f %d)', $chroma * 0.7, $hue),
            300 => sprintf('oklch(0.79 %.3f %d)', $chroma, $hue),
            400 => $tokens['dark'][0],
            500 => $tokens['light'][0],
            600 => $tokens['light'][3],
            700 => sprintf('oklch(0.36 %.3f %d)', $chroma * 0.9, $hue),
            800 => sprintf('oklch(0.31 %.3f %d)', $chroma * 0.8, $hue),
            900 => sprintf('oklch(0.26 %.3f %d)', $chroma * 0.7, $hue),
            950 => sprintf('oklch(0.2 %.3f %d)', $chroma * 0.5, $hue),
        ];
    }

    /**
     * Primary palette: shade 600 is the light --primary and 400 the dark --primary.
     * Filament draws dark-mode text, icons, active navigation, tabs and pagination
     * from shade 400, so it must stay a light blue that reads on dark surfaces.
     * Solid buttons are pinned to the exact tokens by the style view because
     * Filament picks shade 600 for button backgrounds in both modes.
     *
     * @return array<int, string>
     */
    private static function primaryPalette(): array
    {
        return [
            50 => '#eff6ff',
            100 => '#dbeafe',
            200 => '#bfdbfe',
            300 => '#93c5fd',
            400 => self::SEMANTIC_COLORS['primary']['dark'][0],
            500 => '#3b82f6',
            600 => self::SEMANTIC_COLORS['primary']['light'][0],
            700 => '#1d4ed8',
            800 => '#1e40af',
            900 => '#1e3a8a',
            950 => '#172554',
        ];
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('SiTA Universitas Bumigora')
            ->favicon(asset('favicon.svg'))
            ->login(Login::class)
            ->passwordReset(RequestPasswordReset::class, ResetPassword::class)
            ->profile(EditProfile::class, isSimple: false)
            ->userMenuItems([
                'profile' => fn(Action $action): Action => $action
                    ->label('Edit profil')
                    ->icon('heroicon-m-user-circle'),
            ])
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->globalSearchFieldSuffix(fn(): string => match (Platform::detect()) {
                Platform::Mac => 'Cmd+K',
                Platform::Windows, Platform::Linux => 'Ctrl+K',
                default => 'Ctrl+K',
            })
            ->colors([
                'primary' => self::primaryPalette(),
                'success' => self::statusPalette('success'),
                'warning' => self::statusPalette('warning'),
                'danger' => self::statusPalette('danger'),
                'info' => self::statusPalette('info'),
            ])
            ->font('Instrument Sans')
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn(): string => view('filament.semantic-colors', ['roles' => self::SEMANTIC_COLORS])->render(),
            )
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn(): string => view('filament.admin-layout')->render(),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->databaseNotifications()
            ->databaseNotificationsPolling('15s')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSessionWithoutBackgroundWrites::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                'role:admin|super_admin',
            ]);
    }
}
