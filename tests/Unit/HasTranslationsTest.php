<?php

namespace Tests\Unit;

use App\Models\Portfolio;
use Tests\TestCase;

class HasTranslationsTest extends TestCase
{
    public function test_it_returns_the_value_for_the_active_locale(): void
    {
        $portfolio = new Portfolio;
        $portfolio->title = ['id' => 'Rumah Modern', 'en' => 'Modern House'];

        app()->setLocale('id');
        $this->assertSame('Rumah Modern', $portfolio->title);

        app()->setLocale('en');
        $this->assertSame('Modern House', $portfolio->title);
    }

    public function test_it_falls_back_to_the_fallback_locale_when_a_translation_is_missing(): void
    {
        config(['app.fallback_locale' => 'id']);

        $portfolio = new Portfolio;
        $portfolio->title = ['id' => 'Rumah Modern'];

        app()->setLocale('en');
        $this->assertSame('Rumah Modern', $portfolio->title);
    }

    public function test_it_falls_back_to_any_non_empty_value_rather_than_rendering_blank(): void
    {
        config(['app.fallback_locale' => 'id']);

        $portfolio = new Portfolio;
        // Neither the active nor the fallback locale is present.
        $portfolio->setTranslations('title', ['de' => 'Modernes Haus']);

        app()->setLocale('en');
        $this->assertSame('Modernes Haus', $portfolio->title);
    }

    public function test_it_treats_a_bare_string_as_legacy_data_in_the_fallback_locale(): void
    {
        config(['app.fallback_locale' => 'id']);

        $portfolio = new Portfolio;
        // Simulates a pre-translation row read straight from the database.
        $portfolio->setRawAttributes(['title' => 'Rumah Lama'], true);

        app()->setLocale('en');
        $this->assertSame('Rumah Lama', $portfolio->title);
    }

    public function test_it_sets_only_the_active_locale_when_assigned_a_plain_string(): void
    {
        $portfolio = new Portfolio;
        $portfolio->setTranslations('title', ['id' => 'Rumah Modern', 'en' => 'Modern House']);

        app()->setLocale('en');
        $portfolio->title = 'Renamed House';

        $this->assertSame(
            ['id' => 'Rumah Modern', 'en' => 'Renamed House'],
            $portfolio->getTranslations('title'),
        );
    }

    public function test_it_resolves_translatable_attributes_in_array_output(): void
    {
        app()->setLocale('id');

        $portfolio = new Portfolio;
        $portfolio->title = ['id' => 'Rumah Modern', 'en' => 'Modern House'];

        // Must not leak the raw JSON blob into API/JSON output.
        $this->assertSame('Rumah Modern', $portfolio->toArray()['title']);
    }
}
