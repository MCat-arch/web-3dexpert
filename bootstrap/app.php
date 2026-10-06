<?php

use App\Http\Middleware\EnsureUserCanManageContent;
use App\Http\Middleware\HandleLegacyRedirects;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
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

        $middleware->append(HandleLegacyRedirects::class);
        $middleware->web(append: [SecurityHeaders::class]);

        $middleware->alias([
            'locale' => SetLocale::class,
            'can-manage-content' => EnsureUserCanManageContent::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
