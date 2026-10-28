<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records one lightweight row per public page view for the superadmin
 * dashboard.
 *
 * Deliberate choices:
 *   - terminate() runs AFTER the response is sent, so the insert never adds
 *     latency to the page the visitor is waiting for.
 *   - The visitor IP is hashed together with a daily salt (never stored raw),
 *     so "unique visitors today" is answerable but cross-day tracking is not.
 *   - Only GET requests become rows: form posts, admin panel traffic, Livewire
 *     endpoints and static assets are not "website traffic".
 *   - Every failure is swallowed. Analytics must never take the site down.
 */
class TrackSiteTraffic
{
    /** Exact paths that are infrastructure, not pages. */
    private const EXCLUDED_PATHS = [
        'up',
        'favicon.ico',
        'robots.txt',
        'sitemap.xml',
    ];

    /** Path prefixes that are infrastructure, not pages. */
    private const EXCLUDED_PREFIXES = [
        'admin',
        'livewire',
        'build',
        'storage',
        '.well-known',
        'pembayaran/notifikasi',
    ];

    private const BOT_PATTERN = '/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|headless|pingdom|uptime|python-requests|python-urllib|curl|wget|go-http-client|scrapy|ahrefs|semrush/i';

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            $this->record($request, $response);
        } catch (\Throwable) {
            // Never let analytics break a request.
        }
    }

    private function record(Request $request, Response $response): void
    {
        if (! $request->isMethod('GET')) {
            return;
        }

        $path = trim($request->path(), '/');

        if (in_array($path, self::EXCLUDED_PATHS, true)) {
            return;
        }

        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return;
            }
        }

        $userAgent = (string) $request->userAgent();

        DB::table('site_traffic')->insert([
            'path' => '/'.($path === '' ? '' : $path),
            'method' => $request->method(),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $this->durationMs(),
            'locale' => app()->getLocale(),
            'visitor_hash' => $this->visitorHash($request),
            'is_bot' => $userAgent !== '' && preg_match(self::BOT_PATTERN, $userAgent) === 1,
            'is_staff' => auth('web')->check(),
            'created_at' => now(),
        ]);
    }

    private function durationMs(): ?int
    {
        if (! defined('LARAVEL_START')) {
            return null;
        }

        return max(0, (int) round((microtime(true) - LARAVEL_START) * 1000));
    }

    /**
     * Daily-rotated, keyed hash. Rotating the salt each day makes yesterday's
     * hashes useless for following a visitor into today.
     */
    private function visitorHash(Request $request): ?string
    {
        $ip = $request->ip();

        if ($ip === null) {
            return null;
        }

        return hash('sha256', implode('|', [
            (string) config('app.key'),
            now()->toDateString(),
            $ip,
        ]));
    }
}
