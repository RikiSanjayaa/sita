<?php

namespace App\Http\Middleware;

use App\Support\ThemeSchemes;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        View::share('appearance', $request->cookie('appearance') ?? 'system');
        View::share('theme', ThemeSchemes::resolve($request->cookie(ThemeSchemes::COOKIE)));
        View::share('themeSchemes', ThemeSchemes::IDS);

        return $next($request);
    }
}
