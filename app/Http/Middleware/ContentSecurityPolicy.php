<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * BR-SEC-14, ADR-0008: a Content Security Policy with a new nonce on every HTML page.
 * Vite adds the nonce to its script tags; the one inline script in app.blade.php
 * carries it too. The other security headers come from nginx.
 */
class ContentSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('security.csp_enabled')) {
            return $next($request);
        }

        $nonce = Vite::useCspNonce();

        $response = $next($request);

        if (str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'nonce-{$nonce}'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data: blob:",
                "font-src 'self'",
                "connect-src 'self'",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'none'",
            ]));
        }

        return $response;
    }
}
