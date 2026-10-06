<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\MediaController;
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

Route::group([
    // Matches either nothing (Indonesian) or a known locale code.
    'prefix' => '{locale?}',
    'where' => ['locale' => '('.implode('|', array_keys(config('site.locales'))).')?'],
    'middleware' => 'locale',
], function () {
    Route::get('/', HomeController::class)->name('home');

});
