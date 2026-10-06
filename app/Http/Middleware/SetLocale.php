<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active locale from the {locale} route prefix.
 *
 * Replaces legacy's index.php / index-en.php file duplication, where the
 * English homepage was a ~406-line-divergent copy of the Indonesian one and no
 * other page had an English version at all.
 *
 * The default locale (id) is served with NO prefix, so "/" keeps its current
 * meaning and the existing homepage URL does not change.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale') ?? config('site.default_locale');

        if (! array_key_exists($locale, config('site.locales'))) {
            $locale = config('site.default_locale');
        }

        app()->setLocale($locale);

        // So redirect()->route() and route() keep the visitor's language
        // without every call site having to pass it.
        \Illuminate\Support\Facades\URL::defaults([
            'locale' => $locale === config('site.default_locale') ? null : $locale,
        ]);

        return $next($request);
    }
}
