<?php

use App\Http\Middleware\ContentSecurityPolicy;
use App\Http\Middleware\EnsureOwnerTwoFactor;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Only APP_URL's host is accepted (inactive in local and tests by framework design).
        $middleware->trustHosts(subdomains: false);

        $middleware->web(append: [
            HandleAppearance::class,
            // T1.12: these three act only when a user is logged in.
            EnsureUserIsActive::class,       // BR-SEC-13
            EnsurePasswordIsChanged::class,  // BR-USR-08
            EnsureOwnerTwoFactor::class,     // BR-SEC-09
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            ContentSecurityPolicy::class,    // BR-SEC-14, last so the nonce exists before the page renders
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
