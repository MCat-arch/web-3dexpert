<?php

namespace App\Http\Middleware;

use App\Models\LegacyRedirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * 301s every URL the old site served to its new equivalent.
 *
 * This is what stops the cutover from throwing away the domain's search
 * rankings -- the most commonly skipped step in a company-profile rewrite, and
 * the one whose absence is invisible until traffic drops weeks later.
 *
 * Runs only on a 404, so it costs nothing on normal requests.
 *
 * Matching is done on "path?query" (e.g. "detail.php?id=1") because the legacy
 * portfolio URLs were distinguished only by their query string.
 */
class HandleLegacyRedirects
{
    private const CACHE_KEY = 'legacy_redirects.map';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() !== 404) {
            return $response;
        }

        $path = ltrim($request->path(), '/');

        // Only .php paths can be legacy URLs; skip the lookup otherwise.
        if (! str_ends_with($path, '.php')) {
            return $response;
        }

        $candidates = [$path];

        if ($request->getQueryString()) {
            // Most specific first.
            array_unshift($candidates, $path.'?'.$request->getQueryString());

            // Also try just the id param, since crawlers append tracking params
            // (?id=1&utm_source=... should still match "detail.php?id=1").
            if ($request->has('id')) {
                $candidates[] = $path.'?id='.$request->query('id');
            }
        }

        $map = $this->map();

        foreach ($candidates as $candidate) {
            if (! isset($map[$candidate])) {
                continue;
            }

            [$id, $newPath, $statusCode] = $map[$candidate];

            // Fire-and-forget hit counter: after cutover this tells you which
            // old URLs are still being requested, and therefore which ones you
            // failed to map.
            LegacyRedirect::query()->whereKey($id)->update([
                'hit_count' => DB::raw('hit_count + 1'),
                'last_hit_at' => now(),
            ]);

            return redirect()->to($this->landingDestination($newPath), $statusCode);
        }

        return $response;
    }

    /**
     * Rewrites a mapped destination when the page it names does not exist.
     *
     * Without this, a 301 would point at a 404 -- which is worse than no
     * redirect, because search engines follow it and then drop the page.
     *
     * Portfolio paths are deliberately NOT rewritten: /portofolio and
     * /portofolio/{slug} are real routes again, and the migrator already
     * mapped each detail.php?id=N to its own slug. Collapsing them onto
     * '/#portofolio' would merge every legacy project URL into one anchor and
     * throw away exactly the per-page ranking those 301s exist to preserve.
     *
     * The remaining entries are still rewritten because those pages genuinely
     * do not exist: there are no service pages, no contact page (D12 -- contact
     * is a WhatsApp link), and no careers page until task.md P6. Remove each
     * line as its real route lands.
     */
    private function landingDestination(string $destination): string
    {
        $path = '/'.ltrim((string) parse_url($destination, PHP_URL_PATH), '/');

        return match (true) {
            str_starts_with($path, '/layanan'),
            str_starts_with($path, '/services') => '/#layanan',

            // Contact genuinely has no page: D12 routes it to WhatsApp.
            str_starts_with($path, '/kontak'),
            str_starts_with($path, '/contact') => '/#kontak',

            // '/karir' is NOT rewritten any more -- P6 made it a real page, so
            // karir.php can 301 to the actual careers index instead of being
            // collapsed onto a homepage anchor. Same correction as the
            // portfolio branch in T0: a rewrite that outlived its reason was
            // quietly discarding the destination's SEO value.
            default => $destination,
        };
    }

    private function map(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addHour(), function () {
            return LegacyRedirect::all()
                ->mapWithKeys(fn (LegacyRedirect $r) => [
                    $r->old_path => [$r->id, $r->new_path, $r->status_code],
                ])
                ->all();
        });
    }
}
