<?php

use App\Http\Middleware\EnsureCustomerEmailIsVerified;
use App\Http\Middleware\EnsureUserCanManageContent;
use App\Http\Middleware\HandleLegacyRedirects;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Support\AuditLogger;
use App\Support\Locale;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

/*
 * MERGE NOTE: `composer create-project laravel/laravel` generates its own
 * bootstrap/app.php. Merge the middleware and alias blocks below into the
 * generated file rather than overwriting it wholesale -- the withRouting()
 * arguments can differ between framework versions.
 */

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        /*
         * Trusted proxies.
         *
         * Three things depend on getting this right:
         *   1. throttle:5,10 on contact.store keys on $request->ip(). Behind an
         *      untrusted proxy every visitor shares the proxy's IP, so the
         *      limit becomes collective -- one bot locks the form for everyone.
         *   2. SecurityHeaders only sends HSTS when $request->secure(), which
         *      is false behind an untrusted proxy even over HTTPS.
         *   3. contact_submissions.ip_address records the proxy, not the visitor.
         *
         * Default is EMPTY: trust nothing. That is correct for plain cPanel /
         * LiteSpeed hosting serving Apache directly, which is the current
         * setup, and it is never unsafe. Set TRUSTED_PROXIES in .env only when
         * a proxy is actually in front -- '*' is safe ONLY if the origin cannot
         * be reached directly, because otherwise anyone can forge
         * X-Forwarded-For and defeat the rate limiter.
         */
        $proxies = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TRUSTED_PROXIES', ''))
        )));

        $middleware->trustProxies(
            at: $proxies === ['*'] ? '*' : $proxies,
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        // Applies to every web request. CSRF protection is already part of the
        // 'web' group by default -- legacy had no CSRF token anywhere, so every
        // admin form and every ?hapus= link was forgeable.
        $middleware->web(append: [SecurityHeaders::class]);

        /*
         * HandleLegacyRedirects must stay GLOBAL. It works by inspecting the
         * response for a 404, and the URLs it exists to rescue -- detail.php,
         * karir.php, index-en.php -- match no route at all. Route-group
         * middleware never runs for an unmatched request, so moving this into
         * the 'web' group silently disables every legacy 301 while leaving the
         * middleware present and apparently wired up.
         *
         * That is precisely the failure its own docblock warns about: invisible
         * until the rankings drop weeks later. It was briefly moved into the
         * web group and reverted here after detail.php?id=1 was observed
         * returning a bare 404.
         *
         * The cost of running globally is one str_ends_with('.php') check on
         * non-404 responses.
         */
        $middleware->append(HandleLegacyRedirects::class);

        $middleware->alias([
            'locale' => SetLocale::class,
            'can-manage-content' => EnsureUserCanManageContent::class,
            // Reads the `customer` guard explicitly; Laravel's built-in
            // EnsureEmailIsVerified reads the default guard (`web`) and would
            // see no user on a customer route.
            'verified.customer' => EnsureCustomerEmailIsVerified::class,
        ]);

        // Unauthenticated customers go to the customer login page, not the
        // Filament one. Without this, 'auth:customer' falls back to a route
        // named 'login', which does not exist on the public side.
        $middleware->redirectGuestsTo(fn () => Locale::route('customer.login'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if ($request->is('admin') || $request->is('admin/*')) {
                app(AuditLogger::class)->log('authorization.denied', null, [], [
                    'method' => $request->method(),
                    'path' => $request->path(),
                ]);
            }

            return null;
        });
    })->create();
