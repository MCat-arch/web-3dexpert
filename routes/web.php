<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PortfolioController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Public routes
|------------------------------------------------------------------------------
| Legacy routing was the filesystem: index.php, index-en.php, karir.php,
| detail.php?id=5. That cost the site clean URLs, a canonical structure, and
| keyword slugs -- on a company profile site whose whole job is being found.
|
| Locale handling: the default locale (id) is served WITHOUT a prefix, so "/"
| keeps meaning the Indonesian homepage and the currently-indexed URL does not
| change. English is served under /en.
|
| Every old URL is 301'd by HandleLegacyRedirects, which runs on 404s and reads
| the legacy_redirects table populated during data migration.
*/

// Serves user uploads from a private disk. Not locale-prefixed: it returns
// bytes, not language. The pattern excludes '..' to block path traversal.
Route::get('media/{path}', [MediaController::class, 'show'])
    ->where('path', '^(?!.*\.\.)[A-Za-z0-9\-_/\.% ]+$')
    ->name('media.show');

/*
 * One definition, registered once per locale.
 *
 * This was previously a single group prefixed '{locale?}', which looked tidier
 * but could not work: an optional route parameter cannot be followed by a
 * required segment, so '{locale?}/portofolio' made the locale mandatory. "/"
 * was the only route unaffected, being the one with nothing after the
 * parameter -- so the flaw stayed invisible until a second route was added.
 *
 * Explicit groups instead. The locale is passed to the middleware rather than
 * captured as a parameter, which means no URL::defaults plumbing, no optional
 * segments, and a route table that survives route:cache unchanged.
 *
 * Names: the default locale keeps the canonical names ('home'), other locales
 * are namespaced ('en.home'). Use Locale::route() to reach the current
 * locale's variant; see App\Support\Locale.
 */
$publicRoutes = function (): void {
    Route::get('/', HomeController::class)->name('home');

    // Bound by slug (Portfolio::getRouteKeyName), so the URL carries the
    // project name rather than detail.php?id=5.
    Route::get('portofolio', [PortfolioController::class, 'index'])
        ->name('portfolio.index');

    Route::get('portofolio/{portfolio}', [PortfolioController::class, 'show'])
        ->name('portfolio.show');
};

$defaultLocale = config('site.default_locale');

// Default locale: no prefix, canonical route names.
Route::middleware('locale:'.$defaultLocale)->group($publicRoutes);

// Every other locale: prefixed path, prefixed route names.
foreach (array_keys(config('site.locales')) as $locale) {
    if ($locale === $defaultLocale) {
        continue;
    }

    Route::prefix($locale)
        ->middleware('locale:'.$locale)
        ->name($locale.'.')
        ->group($publicRoutes);
}
