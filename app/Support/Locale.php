<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * Builds the URL for the current page in another locale, for the language
 * switcher and the hreflang tags.
 *
 * Legacy had no equivalent: switching language meant a hardcoded link from
 * index.php to index-en.php, and since only the homepage had an English copy,
 * a visitor on karir.php had no way to switch at all.
 */
class Locale
{
    public static function urlFor(string $locale): string
    {
        $route = Route::current();

        if (! $route) {
            return url(self::prefix($locale));
        }

        $parameters = $route->parameters();

        // The default locale is served without a prefix, so the parameter is
        // dropped rather than set.
        if ($locale === config('site.default_locale')) {
            unset($parameters['locale']);
        } else {
            $parameters['locale'] = $locale;
        }

        try {
            return route($route->getName(), $parameters);
        } catch (\Throwable) {
            // A route that cannot be rebuilt (a missing bound model, say)
            // falls back to that locale's homepage rather than erroring.
            return url(self::prefix($locale));
        }
    }

    private static function prefix(string $locale): string
    {
        return $locale === config('site.default_locale') ? '/' : '/'.$locale;
    }

    public static function isDefault(?string $locale = null): bool
    {
        return ($locale ?? app()->getLocale()) === config('site.default_locale');
    }
}
