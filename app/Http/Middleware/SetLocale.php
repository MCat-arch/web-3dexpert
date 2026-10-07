<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the active locale from the route group it is attached to.
 *
 * Replaces legacy's index.php / index-en.php file duplication, where the
 * English homepage was a ~406-line-divergent copy of the Indonesian one and no
 * other page had an English version at all.
 *
 * The locale arrives as a middleware parameter ('locale:en') rather than a
 * route parameter. The previous version read $request->route('locale') from a
 * '{locale?}' prefix, which could not serve unprefixed sub-paths: an optional
 * parameter cannot be followed by a required segment, so the locale was in
 * practice mandatory for every route except "/". See routes/web.php.
 *
 * The default locale (id) is served with NO prefix, so "/" keeps its current
 * meaning and the existing homepage URL does not change.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next, ?string $locale = null): Response
    {
        if (! array_key_exists((string) $locale, config('site.locales'))) {
            $locale = config('site.default_locale');
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
