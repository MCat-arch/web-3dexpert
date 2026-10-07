<?php

use App\Models\LegacyRedirect;
use App\Models\Portfolio;
use App\Models\Testimonial;

beforeEach(function () {
    Testimonial::factory()->highlighted()->count(2)->create();
});

it('renders the homepage', function () {
    Portfolio::factory()->featured()->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('csrf-token', false);
});

it('renders the homepage in english under the /en prefix', function () {
    $this->get('/en')->assertOk()->assertSee('lang="en"', false);
});

it('serves the default locale without a prefix', function () {
    $this->get('/')->assertOk()->assertSee('lang="id"', false);
});

it('does not expose standalone content or form pages', function () {
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
});

it('serves the portfolio index in both locales', function () {
    Portfolio::factory()->create(['is_published' => true]);

    $this->get('/portofolio')->assertOk()->assertSee('lang="id"', false);
    $this->get('/en/portofolio')->assertOk()->assertSee('lang="en"', false);
});

it('serves a published portfolio detail page in both locales', function () {
    $portfolio = Portfolio::factory()->create(['is_published' => true]);

    $this->get('/portofolio/'.$portfolio->slug)->assertOk();
    $this->get('/en/portofolio/'.$portfolio->slug)->assertOk();
});

it('does not serve an unpublished portfolio', function () {
    $portfolio = Portfolio::factory()->create(['is_published' => false]);

    $this->get('/portofolio/'.$portfolio->slug)->assertNotFound();
});

it('counts a view when a portfolio detail page is served', function () {
    $portfolio = Portfolio::factory()->create(['is_published' => true, 'view_count' => 0]);

    $this->get('/portofolio/'.$portfolio->slug)->assertOk();

    expect($portfolio->fresh()->view_count)->toBe(1);
});

it('sends security headers on every response', function () {
    $response = $this->get(route('home'));

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("object-src 'none'")
        ->toContain("frame-ancestors 'none'");
});

it('does not expose a public registration route', function () {
    // Legacy reg.php let anyone self-register, and because edit.php gated only
    // on isset($_SESSION['user_id']) with no role column, every registered
    // account was a superadmin.
    $this->post('/register', [
        'name' => 'Intruder',
        'email' => 'intruder@example.test',
        'password' => 'password123',
    ])->assertNotFound();

    // reg.php itself is 301'd away by the migrator's redirect map.
    LegacyRedirect::create(['old_path' => 'reg.php', 'new_path' => '/']);
    $this->get('/reg.php')->assertRedirect('/')->assertStatus(301);
});
