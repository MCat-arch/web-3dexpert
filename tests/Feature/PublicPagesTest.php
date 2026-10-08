<?php

namespace Tests\Feature;

use App\Models\LegacyRedirect;
use App\Models\Portfolio;
use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Testimonial::factory()->highlighted()->count(2)->create();
    }

    public function test_it_renders_the_homepage(): void
    {
        Portfolio::factory()->featured()->create();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('csrf-token', false);
    }

    public function test_it_renders_the_homepage_in_english_under_the_en_prefix(): void
    {
        $this->get('/en')->assertOk()->assertSee('lang="en"', false);
    }

    public function test_it_serves_the_default_locale_without_a_prefix(): void
    {
        $this->get('/')->assertOk()->assertSee('lang="id"', false);
    }

    public function test_it_does_not_expose_standalone_content_or_form_pages(): void
    {
        foreach ([
            '/layanan',
            '/karir',
            '/kontak',
            // A slug that does not exist still 404s via route model binding.
            '/portofolio/example',
            '/en/portofolio/example',
            '/en/layanan/example',
        ] as $path) {
            $this->get($path)->assertNotFound();
        }
    }

    public function test_it_serves_the_portfolio_index_in_both_locales(): void
    {
        Portfolio::factory()->create(['is_published' => true]);

        $this->get('/portofolio')->assertOk()->assertSee('lang="id"', false);
        $this->get('/en/portofolio')->assertOk()->assertSee('lang="en"', false);
    }

    public function test_it_serves_a_published_portfolio_detail_page_in_both_locales(): void
    {
        $portfolio = Portfolio::factory()->create(['is_published' => true]);

        $this->get('/portofolio/'.$portfolio->slug)->assertOk();
        $this->get('/en/portofolio/'.$portfolio->slug)->assertOk();
    }

    public function test_it_does_not_serve_an_unpublished_portfolio(): void
    {
        $portfolio = Portfolio::factory()->create(['is_published' => false]);

        $this->get('/portofolio/'.$portfolio->slug)->assertNotFound();
    }

    public function test_it_counts_a_view_when_a_portfolio_detail_page_is_served(): void
    {
        $portfolio = Portfolio::factory()->create(['is_published' => true, 'view_count' => 0]);

        $this->get('/portofolio/'.$portfolio->slug)->assertOk();

        $this->assertSame(1, (int) $portfolio->fresh()->view_count);
    }

    public function test_it_sends_security_headers_on_every_response(): void
    {
        $response = $this->get(route('home'));

        $response->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    public function test_legacy_staff_registration_routes_are_not_exposed(): void
    {
        // The old staff-credential URL stays unavailable. Customer registration
        // now uses the separate customer guard at /daftar.
        $this->post('/register', [
            'name' => 'Intruder',
            'email' => 'intruder@example.test',
            'password' => 'password123',
        ])->assertNotFound();

        // reg.php itself is 301'd away by the migrator's redirect map.
        LegacyRedirect::create(['old_path' => 'reg.php', 'new_path' => '/']);
        $this->get('/reg.php')->assertRedirect('/')->assertStatus(301);
    }
}
