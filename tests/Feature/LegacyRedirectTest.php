<?php

use App\Models\LegacyRedirect;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

/**
 * These are the tests that protect the site's search rankings through the
 * cutover -- the most commonly skipped step in a company-profile rewrite.
 */
it('301s a legacy static url', function () {
    LegacyRedirect::create(['old_path' => 'karir.php', 'new_path' => '/#kontak']);

    $this->get('/karir.php')->assertRedirect('/#kontak')->assertStatus(301);
});

it('301s a legacy portfolio url with its query string', function () {
    LegacyRedirect::create([
        'old_path' => 'detail.php?id=7',
        'new_path' => '/portofolio/rumah',
    ]);

    $this->get('/detail.php?id=7')
        ->assertRedirect('/#portofolio')
        ->assertStatus(301);
});

it('still matches when a crawler appends tracking parameters', function () {
    LegacyRedirect::create(['old_path' => 'detail.php?id=7', 'new_path' => '/portofolio/rumah']);

    $this->get('/detail.php?id=7&utm_source=google&utm_medium=cpc')
        ->assertRedirect('/#portofolio');
});

it('counts hits so you can find the urls you failed to map', function () {
    $redirect = LegacyRedirect::create(['old_path' => 'karir.php', 'new_path' => '/karir']);

    $this->get('/karir.php');
    $this->get('/karir.php');

    $redirect->refresh();

    expect($redirect->hit_count)->toBe(2)
        ->and($redirect->last_hit_at)->not->toBeNull();
});

it('404s an unmapped legacy url rather than redirecting to the homepage', function () {
    // A blanket redirect-to-home would tell Google these are all soft 404s.
    $this->get('/nonexistent.php')->assertNotFound();
});

it('does not run the lookup on non-php paths', function () {
    $this->get('/tidak-ada-halaman-ini')->assertNotFound();
});
