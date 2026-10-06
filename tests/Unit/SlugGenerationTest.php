<?php

use App\Models\Portfolio;

it('generates a slug from the fallback locale title', function () {
    $portfolio = Portfolio::factory()->create([
        'title' => ['id' => 'Desain Rumah Klasik Lantai 2', 'en' => 'Classic Two Storey House'],
        'slug' => null,
    ]);

    expect($portfolio->slug)->toBe('desain-rumah-klasik-lantai-2');
});

it('appends a suffix rather than colliding', function () {
    $title = ['id' => 'Rumah Modern', 'en' => 'Modern House'];

    $first = Portfolio::factory()->create(['title' => $title, 'slug' => null]);
    $second = Portfolio::factory()->create(['title' => $title, 'slug' => null]);
    $third = Portfolio::factory()->create(['title' => $title, 'slug' => null]);

    expect($first->slug)->toBe('rumah-modern')
        ->and($second->slug)->toBe('rumah-modern-2')
        ->and($third->slug)->toBe('rumah-modern-3');
});

it('does not collide with a soft-deleted slug', function () {
    // A soft-deleted row still occupies its slug, and the unique index would
    // reject a duplicate -- so withTrashed() must be part of the check.
    $deleted = Portfolio::factory()->create([
        'title' => ['id' => 'Rumah Lama'], 'slug' => null,
    ]);
    $deleted->delete();

    $fresh = Portfolio::factory()->create(['title' => ['id' => 'Rumah Lama'], 'slug' => null]);

    expect($fresh->slug)->toBe('rumah-lama-2');
});

it('keeps an explicitly provided slug', function () {
    $portfolio = Portfolio::factory()->create([
        'title' => ['id' => 'Rumah Modern'],
        'slug' => 'slug-pilihan-saya',
    ]);

    expect($portfolio->slug)->toBe('slug-pilihan-saya');
});
