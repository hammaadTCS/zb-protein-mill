<?php

namespace App\Http\Middleware;

use App\Domain\Settings\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * NFR-02: a GET request reads each company setting from the database once, however
 * many numbers and dates its page or PDF formats. Saves (POST, PUT) read them fresh.
 */
class RememberSettings
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodSafe()) {
            return $next($request);
        }

        /** @var Response */
        return Settings::remember(fn () => $next($request));
    }
}
