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

    public function test_it_serves_the_services_page_in_every_locale_but_not_contact(): void
    {
        // Services is a real localized page. D12 means there is still no
        // contact page, so a localized contact URL must 404.
        $this->get('/en/layanan')->assertOk()->assertSee('lang="en"', false);
        $this->get('/en/kontak')->assertNotFound();
    }
}
