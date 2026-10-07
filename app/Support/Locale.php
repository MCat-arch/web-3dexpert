<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * Locale-aware route naming and URL building.
 *
 * Route names are namespaced per locale: the default locale keeps the canonical
 * name ('home') and every other locale is prefixed ('en.home'). See
 * routes/web.php for why the locale is a route-group namespace rather than a
 * route parameter.
 *
 * Legacy had no equivalent: switching language meant a hardcoded link from
 * index.php to index-en.php, and since only the homepage had an English copy,
 * a visitor on karir.php had no way to switch at all.
 */
class Locale
{
    /**
     * The route name for the given (or current) locale.
     *
     * name('home') => 'home' in the default locale, 'en.home' under /en.
     */
    public static function name(string $routeName, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return self::isDefault($locale) ? $routeName : $locale.'.'.$routeName;
    }

    /**
     * Build a URL to a public route in the current locale.
     *
     * Use this instead of route() for anything inside the locale groups, or
     * Indonesian visitors will be sent English URLs and vice versa.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function route(string $routeName, array $parameters = [], ?string $locale = null): string
    {
        return route(self::name($routeName, $locale), $parameters);
    }

    /**
     * The current page in another locale, for the switcher and hreflang tags.
     */
    public static function urlFor(string $locale): string
    {
        $route = Route::current();

        if (! $route) {
            return url(self::prefix($locale));
        }

        // Strip any existing locale namespace to get back to the canonical
        // name, then re-apply the target locale's.
        $canonical = self::canonicalName((string) $route->getName());

        try {
            return self::route($canonical, $route->parameters(), $locale);
        } catch (\Throwable) {
            // A route that cannot be rebuilt (a missing bound model, say)
            // falls back to that locale's homepage rather than erroring.
            return url(self::prefix($locale));
        }
    }

    /**
     * 'en.portfolio.show' => 'portfolio.show'
     */
    public static function canonicalName(string $routeName): string
    {
        foreach (array_keys(config('site.locales')) as $locale) {
            if (str_starts_with($routeName, $locale.'.')) {
                return substr($routeName, strlen($locale) + 1);
            }
        }

        return $routeName;
    }

    private static function prefix(string $locale): string
    {
        return self::isDefault($locale) ? '/' : '/'.$locale;
    }

    public static function isDefault(?string $locale = null): bool
    {
        return ($locale ?? app()->getLocale()) === config('site.default_locale');
    }
}
