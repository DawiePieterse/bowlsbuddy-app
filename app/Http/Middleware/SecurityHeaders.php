<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The security headers from PLAN.md section 6, on every response. The CSP keeps everything on
 * this origin; inline styles and scripts (and eval) stay allowed because Livewire, Alpine and
 * Filament need them, and the WhatsApp share links are plain links, not scripts.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; "
            ."style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; "
            ."connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self' https://wa.me",
        );

        return $response;
    }
}
