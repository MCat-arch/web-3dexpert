<?php

use App\Http\Controllers\Customer\AccountController;
use App\Http\Controllers\Customer\Auth\EmailVerificationController;
use App\Http\Controllers\Customer\Auth\LoginController;
use App\Http\Controllers\Customer\Auth\LogoutController;
use App\Http\Controllers\Customer\Auth\PasswordResetController;
use App\Http\Controllers\Customer\Auth\RegisterController;
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

    /*
     * Public accounts (P1). The `customer` guard, never `web` -- see D1.
     *
     * Throttles are named limiters defined in AppServiceProvider.
     * customer-register is deliberately not IP-only: CGNAT and shared venue
     * WiFi would otherwise 429 most of a 500-person launch (D17).
     */
    Route::middleware('guest:customer')->group(function () {
        Route::get('daftar', [RegisterController::class, 'create'])->name('customer.register');
        Route::post('daftar', [RegisterController::class, 'store'])
            ->middleware('throttle:customer-register');

        Route::get('masuk', [LoginController::class, 'create'])->name('customer.login');
        Route::post('masuk', [LoginController::class, 'store'])
            ->middleware('throttle:customer-login');

        Route::get('lupa-sandi', [PasswordResetController::class, 'request'])
            ->name('customer.password.request');
        Route::post('lupa-sandi', [PasswordResetController::class, 'email'])
            ->middleware('throttle:customer-password-reset')
            ->name('customer.password.email');

        Route::get('atur-sandi/{token}', [PasswordResetController::class, 'reset'])
            ->name('customer.password.reset');
        Route::post('atur-sandi', [PasswordResetController::class, 'update'])
            ->middleware('throttle:customer-password-reset')
            ->name('customer.password.update');
    });

    Route::middleware('auth:customer')->group(function () {
        Route::post('keluar', LogoutController::class)->name('customer.logout');

        Route::get('verifikasi-email', [EmailVerificationController::class, 'notice'])
            ->name('customer.verification.notice');

        // 'signed' proves the link is ours and unexpired; the controller also
        // checks it was issued for this account, so a valid link cannot be
        // replayed against a different signed-in customer.
        Route::get('verifikasi-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware('signed')
            ->name('customer.verification.verify');

        Route::post('verifikasi-email/kirim-ulang', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:customer-verification-resend')
            ->name('customer.verification.resend');

        // Verified-only area. P5a's enrollment lands behind this same gate.
        Route::middleware('verified.customer')->group(function () {
            Route::get('akun', AccountController::class)->name('customer.account');
        });
    });
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
