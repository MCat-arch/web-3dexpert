<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The legacy site sent no security headers at all.
 *
 * The CSP is deliberately explicit about the third parties the pages actually
 * use, rather than being permissive:
 *   - youtube-nocookie.com : testimonial and review embeds (ytFacade)
 *   - instagram.com        : "Alur Pemesanan" reel embed (frame only; content
 *                            inside the iframe is governed by Instagram's own CSP)
 *   - fonts.googleapis/gstatic : webfonts
 *   - i.ytimg.com : video thumbnails
 *   - images.unsplash.com : legacy property_images rows hold external Unsplash
 *     URLs. Remove this once those images are hosted locally.
 *
 * script-src stays WITHOUT 'unsafe-eval' and WITHOUT 'unsafe-inline'. That is
 * only possible because resources/js/app.js uses the CSP-safe Alpine build and
 * every view carries a bare x-data component name with no inline expressions.
 * Switching back to standard Alpine, or adding an inline <script>, breaks the
 * page under this policy -- fix the markup, do not loosen the header.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $csp = implode('; ', [
            "default-src 'self'",
            // 'unsafe-inline' for styles only, because Tailwind emits a few
            // inline styles. Scripts do NOT get it.
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            "script-src 'self'",
            "img-src 'self' data: https://i.ytimg.com https://images.unsplash.com",
            // [FIXED] Semua frame: YouTube (ytFacade) + Instagram reel (order flow).
            // DITULIS SEBAGAI SATU STRING DENGAN NAMA DIREKTIF — bukan key array,
            // karena implode() mengabaikan key dan nama direktifnya hilang.
            'frame-src https://www.youtube-nocookie.com https://www.youtube.com https://www.instagram.com',
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);

        $headers = [
            'Content-Security-Policy' => $csp,
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        if ($request->secure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value, false);
        }

        return $response;
    }
}
