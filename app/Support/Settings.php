<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Typed accessor over the site_settings key/value table.
 *
 * Replaces legacy's magic-id singletons: edit.php hardcoded
 * $features_id = 1, and alur_section/location_section were read with
 * "LIMIT 1" and written with "UPDATE ... LIMIT 1" (no WHERE at all), so if
 * row 1 were ever deleted the admin panel would silently stop saving.
 *
 * Settings are read directly so Eloquent models are never serialized into the
 * database cache as incomplete objects.
 */
class Settings
{
    public const CACHE_KEY = 'site_settings';

    /**
     * Per-request memo.
     *
     * Without it, every $settings('key') call in a view ran its own
     * `SELECT * FROM site_settings` and rehydrated every row into an Eloquent
     * model. The homepage alone calls it 51 times (25 in home.blade.php, 12 in
     * the footer, 10 in the layout, 4 in the header), so one page view issued
     * 51 identical full-table queries -- the same shape of problem as legacy
     * opening seven PDO connections per request, just moved up a layer.
     *
     * @var array<string, SiteSetting>|null
     */
    private static ?array $memo = null;

    /** Keys used by the views, with their types and whether they translate. */
    public const SCHEMA = [
        // Hero block (legacy: hardcoded <h1>/<p>/CTA in index.php:33-36).
        // Kept separate from home_heading: legacy's hero headline and the
        // features headline are different strings and both are on the page.
        'hero_heading'        => ['text',    true,  'hero'],
        'hero_subheading'     => ['text',    true,  'hero'],

        // Features block headline (legacy features_section.judul_konten /
        // .sub_konten). NOT the hero -- see above.
        'home_heading'        => ['text',    true,  'home'],
        'home_subheading'     => ['text',    true,  'home'],
        'stat_projects'       => ['integer', false, 'home'],
        'stat_clients'        => ['integer', false, 'home'],

        // "Mengapa" block (legacy mengapa_section main row)
        'why_description'     => ['text',    true,  'why'],
        'why_video_url'       => ['string',  false, 'why'],

        // Process / flow block (legacy alur_section)
        'process_link_url'    => ['string',  false, 'process'],
        // Legacy's thumbnail was the unreplaced placeholder
        // "path-to-your-thumbnail.jpg" (index.php:744), which 404'd in
        // production. Optional here: when empty, the view derives a thumbnail
        // from the linked YouTube video, and falls back to a plain CTA card.
        'process_thumbnail'   => ['string',  false, 'process'],

        // Location block (legacy location_section + the hardcoded office
        // details at index.php:501-514)
        'location_video_url'  => ['string',  false, 'location'],
        'location_office_label' => ['text',  true,  'location'],

        // Company contact details (legacy: hardcoded in partials/footer.php)
        'company_name'        => ['string',  false, 'company'],
        'company_address'     => ['text',    true,  'company'],
        'company_phone'       => ['string',  false, 'company'],
        'company_whatsapp'    => ['string',  false, 'company'],
        'company_email'       => ['string',  false, 'company'],
        'company_facebook'    => ['string',  false, 'company'],
        'company_instagram'   => ['string',  false, 'company'],
        'company_youtube'     => ['string',  false, 'company'],
        'company_tiktok'      => ['string',  false, 'company'],
        // A human-facing Google Maps LINK (legacy used goo.gl/maps/...), not an
        // /maps/embed URL. Rendered as an anchor, never as an iframe src:
        // Google refuses to be framed from a non-embed URL, so the previous
        // iframe treatment produced an empty box.
        'company_maps_url'    => ['string',  false, 'company'],

        // SEO defaults
        'seo_title'           => ['string',  true,  'seo'],
        'seo_description'     => ['text',    true,  'seo'],
    ];

    public static function all(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        return self::$memo = SiteSetting::all()
            ->mapWithKeys(fn (SiteSetting $s) => [$s->key => $s])
            ->all();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = self::all()[$key] ?? null;

        if (! $setting) {
            return $default;
        }

        $value = $setting->resolved();

        return blank($value) ? $default : $value;
    }

    public static function set(string $key, mixed $value, ?string $locale = null): SiteSetting
    {
        [$type, $translatable, $group] = self::SCHEMA[$key] ?? ['string', false, 'general'];

        $setting = SiteSetting::firstOrNew(['key' => $key]);
        $setting->type = $type;
        $setting->is_translatable = $translatable;
        $setting->group = $group;

        if ($translatable) {
            if (is_array($value)) {
                $setting->setTranslations('value_translations', $value);
            } else {
                $setting->setTranslation(
                    'value_translations',
                    $locale ?? config('app.fallback_locale'),
                    $value
                );
            }
        } else {
            $setting->value = is_array($value) ? json_encode($value) : $value;
        }

        $setting->save();
        self::flush();

        return $setting;
    }

    /**
     * Drops the memo so a write is visible to the rest of the request.
     *
     * This used to be an empty method, which also made ContentObserver's call
     * to it a no-op. Nothing writes a 'home.<locale>' cache entry either, so
     * the observer was flushing keys that never existed.
     */
    public static function flush(): void
    {
        self::$memo = null;
        Cache::forget(self::CACHE_KEY);
    }
}
