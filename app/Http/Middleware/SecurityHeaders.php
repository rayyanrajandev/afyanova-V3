<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers for every response. None of these replace
 * server-side authorization or input validation — they narrow what a
 * browser will do with a response if something else already went wrong
 * (a reflected script, a framed login page, a leaked referrer).
 *
 * CSP uses a per-request nonce rather than 'unsafe-inline'.
 * The nonce is generated before $next($request) so that both
 *
 * @vite and @routes (Ziggy) Blade directives can read it from
 * Vite::cspNonce() when they render their inline <script> tags.
 * A fresh cryptographically random nonce is generated for each
 * response, preventing an attacker from reusing a nonce captured
 * from a previous response to authorize injected scripts.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Generate the nonce BEFORE the response is built so Blade
        // directives (@vite, @routes) pick it up during rendering.
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        // Cross-origin isolation: stops Spectre-class attacks by preventing
        // this document from sharing a browsing context with cross-origin pages.
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        // Prevents other sites from embedding our resources (images, scripts)
        // in their pages — stops hotlinking and leakage via cross-origin reads.
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        // Legacy Flash/PDF plugin policy — belt-and-suspenders for older agents.
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        // script-src uses a per-request nonce: @vite and @routes (Ziggy)
        // both stamp their inline <script> tags with nonce="{value}" so the
        // browser allows only those specific tags and nothing injected later.
        // style-src keeps 'unsafe-inline' — Tailwind emits styles at runtime
        // and there is no practical nonce path for that in a Vite/Vue setup.
        $cspDirectives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            "img-src 'self' data:",
            "font-src 'self' data: https://fonts.bunny.net",
            "connect-src 'self' ws: wss:",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        if ($request->secure() || app()->environment('production') || app()->runningUnitTests()) {
            $cspDirectives[] = 'upgrade-insecure-requests';
        }

        $response->headers->set('Content-Security-Policy', implode('; ', $cspDirectives));

        return $response;
    }
}
