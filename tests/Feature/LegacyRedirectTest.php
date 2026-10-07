<?php

namespace Tests\Feature;

use App\Models\LegacyRedirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * These are the tests that protect the site's search rankings through the
 * cutover -- the most commonly skipped step in a company-profile rewrite.
 *
 * They matter more than they look: HandleLegacyRedirects detects a 404 by
 * inspecting the response, so it only works as GLOBAL middleware. It was once
 * moved into the 'web' group, which silently disabled every redirect here while
 * leaving the middleware apparently wired up. Nothing but these tests would
 * have caught that.
 */
class LegacyRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_it_301s_a_legacy_static_url(): void
    {
        LegacyRedirect::create(['old_path' => 'karir.php', 'new_path' => '/#kontak']);

        $this->get('/karir.php')->assertRedirect('/#kontak')->assertStatus(301);
    }

    public function test_it_301s_a_legacy_portfolio_url_to_its_own_slug(): void
    {
        // Previously this asserted a redirect to '/#portofolio', because
        // landingDestination() rewrote every portfolio path onto that anchor
        // while the routes did not exist. The routes exist again, so each
        // legacy project URL must reach its own page -- collapsing them would
        // merge four distinct URLs into one and discard their ranking.
        LegacyRedirect::create([
            'old_path' => 'detail.php?id=7',
            'new_path' => '/portofolio/rumah',
        ]);

        $this->get('/detail.php?id=7')
            ->assertRedirect('/portofolio/rumah')
            ->assertStatus(301);
    }

    public function test_it_still_matches_when_a_crawler_appends_tracking_parameters(): void
    {
        LegacyRedirect::create(['old_path' => 'detail.php?id=7', 'new_path' => '/portofolio/rumah']);

        $this->get('/detail.php?id=7&utm_source=google&utm_medium=cpc')
            ->assertRedirect('/portofolio/rumah');
    }

    public function test_it_still_rewrites_destinations_whose_pages_do_not_exist(): void
    {
        // No services page exists, so a 301 pointing at one would be a 301 to a
        // 404 -- worse than no redirect, because crawlers follow it and drop
        // the page.
        LegacyRedirect::create(['old_path' => 'layanan.php', 'new_path' => '/layanan/desain']);

        $this->get('/layanan.php')->assertRedirect('/#layanan');
    }

    public function test_it_counts_hits_so_you_can_find_the_urls_you_failed_to_map(): void
    {
        $redirect = LegacyRedirect::create(['old_path' => 'karir.php', 'new_path' => '/#kontak']);

        $this->get('/karir.php');
        $this->get('/karir.php');

        $redirect->refresh();

        $this->assertSame(2, (int) $redirect->hit_count);
        $this->assertNotNull($redirect->last_hit_at);
    }

    public function test_it_404s_an_unmapped_legacy_url_rather_than_redirecting_to_the_homepage(): void
    {
        // A blanket redirect-to-home would tell Google these are all soft 404s.
        $this->get('/nonexistent.php')->assertNotFound();
    }

    public function test_it_does_not_run_the_lookup_on_non_php_paths(): void
    {
        $this->get('/tidak-ada-halaman-ini')->assertNotFound();
    }
}
