<?php

use App\Models\Testimonial;

beforeEach(function () {
    Testimonial::factory()->highlighted()->count(2)->create();
});

it('serves the landing page per locale from one set of views', function () {
    $this->get('/')->assertOk()->assertSee('lang="id"', false);
    $this->get('/en')->assertOk()->assertSee('lang="en"', false);
});

it('falls back to the default locale for an unknown prefix', function () {
    $this->get('/fr')->assertNotFound();
});

it('emits hreflang tags for every locale', function () {
    $response = $this->get('/');

    foreach (array_keys(config('site.locales')) as $locale) {
        $response->assertSee('hreflang="'.$locale.'"', false);
    }

    $response->assertSee('hreflang="x-default"', false);
});

it('emits a canonical url', function () {
    $this->get('/')->assertSee('rel="canonical"', false);
});

it('does not expose localized standalone pages', function () {
    $this->get('/en/karir')->assertNotFound();
});
