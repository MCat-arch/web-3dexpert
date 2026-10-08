<?php

namespace Tests\Feature;

use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Testimonial::factory()->highlighted()->count(2)->create();
    }

    public function test_it_serves_the_landing_page_per_locale_from_one_set_of_views(): void
    {
        $this->get('/')->assertOk()->assertSee('lang="id"', false);
        $this->get('/en')->assertOk()->assertSee('lang="en"', false);
    }

    public function test_it_404s_an_unknown_locale_prefix(): void
    {
        // Each locale is now its own route group (D20), so '/fr' matches
        // nothing at all rather than being captured and coerced.
        $this->get('/fr')->assertNotFound();
    }

    public function test_it_emits_hreflang_tags_for_every_locale(): void
    {
        $response = $this->get('/');

        foreach (array_keys(config('site.locales')) as $locale) {
            $response->assertSee('hreflang="'.$locale.'"', false);
        }

        $response->assertSee('hreflang="x-default"', false);
    }

    public function test_it_emits_a_canonical_url(): void
    {
        $this->get('/')->assertSee('rel="canonical"', false);
    }

    public function test_it_does_not_expose_localized_standalone_pages(): void
    {
        // '/en/karir' is a real page as of P6. '/en/layanan' is not: there are
        // no service pages, and D12 means there is no contact page either.
        $this->get('/en/layanan')->assertNotFound();
        $this->get('/en/kontak')->assertNotFound();
    }
}
