<?php

namespace Tests\Unit;

use App\Models\Portfolio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlugGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_a_slug_from_the_fallback_locale_title(): void
    {
        $portfolio = Portfolio::factory()->create([
            'title' => ['id' => 'Desain Rumah Klasik Lantai 2', 'en' => 'Classic Two Storey House'],
            'slug' => null,
        ]);

        $this->assertSame('desain-rumah-klasik-lantai-2', $portfolio->slug);
    }

    public function test_it_appends_a_suffix_rather_than_colliding(): void
    {
        $title = ['id' => 'Rumah Modern', 'en' => 'Modern House'];

        $first = Portfolio::factory()->create(['title' => $title, 'slug' => null]);
        $second = Portfolio::factory()->create(['title' => $title, 'slug' => null]);
        $third = Portfolio::factory()->create(['title' => $title, 'slug' => null]);

        $this->assertSame('rumah-modern', $first->slug);
        $this->assertSame('rumah-modern-2', $second->slug);
        $this->assertSame('rumah-modern-3', $third->slug);
    }

    public function test_it_does_not_collide_with_a_soft_deleted_slug(): void
    {
        // A soft-deleted row still occupies its slug, and the unique index would
        // reject a duplicate -- so withTrashed() must be part of the check.
        $deleted = Portfolio::factory()->create([
            'title' => ['id' => 'Rumah Lama'], 'slug' => null,
        ]);
        $deleted->delete();

        $fresh = Portfolio::factory()->create(['title' => ['id' => 'Rumah Lama'], 'slug' => null]);

        $this->assertSame('rumah-lama-2', $fresh->slug);
    }

    public function test_it_keeps_an_explicitly_provided_slug(): void
    {
        $portfolio = Portfolio::factory()->create([
            'title' => ['id' => 'Rumah Modern'],
            'slug' => 'slug-pilihan-saya',
        ]);

        $this->assertSame('slug-pilihan-saya', $portfolio->slug);
    }
}
