<?php

namespace Tests\Feature;

use App\Support\Locale;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Tests\TestCase;

/**
 * Guards the failure that took every page down: four route names were used in
 * Blade views with nothing registered to resolve them, and because
 * partials/header.blade.php is part of the layout, the whole site returned 500.
 *
 * Also guards the locale-namespacing that replaced the '{locale?}' prefix. A
 * $lroute('x') call needs 'x' AND every '<locale>.x' variant to exist, or one
 * language silently 404s -- which is exactly how '/portofolio' broke while
 * '/en/portofolio' worked.
 *
 * Written in PHPUnit syntax deliberately. The rest of tests/ is still Pest
 * syntax with Pest uninstalled (task.md P0 Step 1 converts it), so this is the
 * only way the guard can actually run today -- and a guard that cannot run is
 * not a guard.
 *
 * Known limits, so nobody over-trusts a pass:
 *   - Only literal names are checked. route($variable) is invisible here.
 *   - Route *parameters* are not validated, so a correct name with a missing
 *     required parameter still fails at runtime.
 *   - Reads the live route table, so run it without a stale route:cache.
 */
class BladeRouteIntegrityTest extends TestCase
{
    public function test_every_route_name_used_in_blade_is_registered(): void
    {
        $missing = [];

        foreach ($this->bladeFiles() as $file) {
            foreach ($this->routeNamesIn($file) as $name) {
                if (! Route::has($name)) {
                    $missing[] = $file->getRelativePathname().' => '.$name;
                }
            }
        }

        sort($missing);

        $this->assertSame([], $missing, implode(PHP_EOL, array_merge(
            ['Blade views reference route names that are not registered:'],
            $missing,
        )));
    }

    public function test_locale_routes_exist_in_every_locale(): void
    {
        $locales = array_keys(config('site.locales'));
        $missing = [];

        foreach ($this->bladeFiles() as $file) {
            // $lroute('x') resolves to 'x' or '<locale>.x' depending on the
            // active locale, so every variant has to exist.
            preg_match_all(
                "/\\\$lroute\(\s*'([a-z][a-zA-Z0-9._-]*)'/",
                (string) file_get_contents($file->getRealPath()),
                $matches,
            );

            foreach (array_unique($matches[1]) as $name) {
                foreach ($locales as $locale) {
                    $localised = Locale::name($name, $locale);

                    if (! Route::has($localised)) {
                        $missing[] = $file->getRelativePathname().' => '.$localised;
                    }
                }
            }
        }

        sort($missing);

        $this->assertSame([], $missing, implode(PHP_EOL, array_merge(
            ['Locale-namespaced route names are missing:'],
            $missing,
        )));
    }

    public function test_the_route_names_the_layout_depends_on_exist(): void
    {
        // The layout renders on every page, so a missing name here is a
        // site-wide outage rather than one broken link.
        foreach (['home', 'media.show'] as $name) {
            $this->assertTrue(Route::has($name), "Route [{$name}] is missing.");
        }
    }

    public function test_no_view_references_a_contact_route(): void
    {
        // D12: contact is a WhatsApp link, never a form. ContactFormTest asserts
        // the endpoints 404; this asserts no view tries to link to one.
        $offenders = [];

        foreach ($this->bladeFiles() as $file) {
            foreach ($this->routeNamesIn($file) as $name) {
                if (str_starts_with(Locale::canonicalName($name), 'contact.')) {
                    $offenders[] = $file->getRelativePathname().' => '.$name;
                }
            }
        }

        $this->assertSame([], $offenders, implode(PHP_EOL, array_merge(
            ['Views must not link to a contact route (D12):'],
            $offenders,
        )));
    }

    /** @return list<string> */
    private function routeNamesIn(SplFileInfo $file): array
    {
        // Matches route('x') and $lroute('x') alike -- the latter contains the
        // former as a substring.
        preg_match_all(
            "/route\(\s*'([a-z][a-zA-Z0-9._-]*)'/",
            (string) file_get_contents($file->getRealPath()),
            $matches,
        );

        return array_values(array_unique($matches[1]));
    }

    /** @return iterable<SplFileInfo> */
    private function bladeFiles(): iterable
    {
        return Finder::create()
            ->files()
            ->in(resource_path('views'))
            ->name('*.blade.php');
    }
}
