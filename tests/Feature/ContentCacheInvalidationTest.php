<?php

namespace Tests\Feature;

use App\Http\Controllers\HomeController;
use App\Models\DesignReview;
use App\Models\Faq;
use App\Models\HeroSlide;
use App\Models\Portfolio;
use App\Models\Service;
use App\Models\Testimonial;
use App\Models\ValueProposition;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Guards two defects that were live in this codebase at once:
 *
 *  1. ContentObserver forgot 'home.{locale}' while HomeController never wrote
 *     it, so the invalidation was dead code -- and README claimed the homepage
 *     was "cached as a unit, invalidated by ContentObserver". Both the writer
 *     and the invalidator now go through HomeController::cacheKey().
 *
 *  2. Faq was never registered with the observer despite being read by
 *     HomeController::payload(), so editing an FAQ would not have invalidated
 *     anything once a cache existed.
 *
 * The payload is not cached yet (Redis is unavailable -- keputusan.md K5), so
 * these tests assert the *invalidation contract* rather than cache hits. That
 * is deliberate: it means O5 can add the write without having to rediscover
 * which models matter.
 */
class ContentCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every model HomeController::payload() reads. If a model is added to the
     * payload without being observed, this provider is where it gets caught.
     *
     * @return array<string, array{class-string<Model>}>
     */
    public static function homepagePayloadModels(): array
    {
        return [
            'hero slide' => [HeroSlide::class],
            'portfolio' => [Portfolio::class],
            'service' => [Service::class],
            'value proposition' => [ValueProposition::class],
            'testimonial' => [Testimonial::class],
            'design review' => [DesignReview::class],
            'faq' => [Faq::class],
        ];
    }

    /**
     * @param  class-string<Model>  $model
     */
    #[DataProvider('homepagePayloadModels')]
    public function test_saving_a_model_in_the_homepage_payload_invalidates_every_locale(string $model): void
    {
        $locales = array_keys(config('site.locales'));

        foreach ($locales as $locale) {
            Cache::put(HomeController::cacheKey($locale), 'stale', 600);
        }

        $model::factory()->create();

        foreach ($locales as $locale) {
            $this->assertNull(
                Cache::get(HomeController::cacheKey($locale)),
                "Saving {$model} did not invalidate the {$locale} homepage cache. ".
                'Is it registered with ContentObserver in AppServiceProvider?',
            );
        }
    }

    /**
     * @param  class-string<Model>  $model
     */
    #[DataProvider('homepagePayloadModels')]
    public function test_deleting_a_model_in_the_homepage_payload_invalidates_the_cache(string $model): void
    {
        $record = $model::factory()->create();

        Cache::put(HomeController::cacheKey('id'), 'stale', 600);

        $record->delete();

        $this->assertNull(Cache::get(HomeController::cacheKey('id')));
    }

    public function test_the_observer_and_the_controller_agree_on_the_key(): void
    {
        // The original bug was two string literals drifting apart. Asserting
        // the shape here means a change to one side without the other fails.
        $this->assertSame('home.id', HomeController::cacheKey('id'));
        $this->assertSame('home.en', HomeController::cacheKey('en'));
    }

    public function test_content_changes_also_flush_the_settings_cache(): void
    {
        // Every page reads settings through the layout, so a stale settings
        // cache is as visible as a stale payload. Uses the constant rather
        // than a literal -- an earlier draft of this test guessed the key as
        // 'site.settings' and passed without asserting anything real.
        Cache::put(Settings::CACHE_KEY, ['stale' => true], 600);

        Faq::factory()->create();

        $this->assertNull(Cache::get(Settings::CACHE_KEY));
    }
}
