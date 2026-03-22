<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LogPasswordResetRequest;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'role' => EnsureRole::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            LogPasswordResetRequest::class,
        ]);

        $middleware->redirectUsersTo(function (Request $request): string {
            $user = $request->user();

            if ($user instanceof User && $user->hasRole('admin')) {
                return Filament::getPanel('admin')?->getUrl() ?? url('/admin');
            }

            return url('/dashboard');
        });
    })
    ->withEvents()
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
