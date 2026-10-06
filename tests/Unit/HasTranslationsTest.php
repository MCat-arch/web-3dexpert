<?php

use App\Models\Portfolio;

it('returns the value for the active locale', function () {
    $portfolio = new Portfolio;
    $portfolio->title = ['id' => 'Rumah Modern', 'en' => 'Modern House'];

    app()->setLocale('id');
    expect($portfolio->title)->toBe('Rumah Modern');

    app()->setLocale('en');
    expect($portfolio->title)->toBe('Modern House');
});

it('falls back to the fallback locale when a translation is missing', function () {
    config(['app.fallback_locale' => 'id']);

    $portfolio = new Portfolio;
    $portfolio->title = ['id' => 'Rumah Modern'];

    app()->setLocale('en');
    expect($portfolio->title)->toBe('Rumah Modern');
});

it('falls back to any non-empty value rather than rendering blank', function () {
    config(['app.fallback_locale' => 'id']);

    $portfolio = new Portfolio;
    // Neither the active nor the fallback locale is present.
    $portfolio->setTranslations('title', ['de' => 'Modernes Haus']);

    app()->setLocale('en');
    expect($portfolio->title)->toBe('Modernes Haus');
});

it('treats a bare string as legacy data in the fallback locale', function () {
    config(['app.fallback_locale' => 'id']);

    $portfolio = new Portfolio;
    // Simulates a pre-translation row read straight from the database.
    $portfolio->setRawAttributes(['title' => 'Rumah Lama'], true);

    app()->setLocale('en');
    expect($portfolio->title)->toBe('Rumah Lama');
});

it('sets only the active locale when assigned a plain string', function () {
    $portfolio = new Portfolio;
    $portfolio->setTranslations('title', ['id' => 'Rumah Modern', 'en' => 'Modern House']);

    app()->setLocale('en');
    $portfolio->title = 'Renamed House';

    expect($portfolio->getTranslations('title'))
        ->toBe(['id' => 'Rumah Modern', 'en' => 'Renamed House']);
});

it('resolves translatable attributes in array output', function () {
    app()->setLocale('id');

    $portfolio = new Portfolio;
    $portfolio->title = ['id' => 'Rumah Modern', 'en' => 'Modern House'];

    // Must not leak the raw JSON blob into API/JSON output.
    expect($portfolio->toArray()['title'])->toBe('Rumah Modern');
});
