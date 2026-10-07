<?php

namespace Tests\Unit;

use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every URL shape present in the production dump, so the migration cannot
 * silently drop an embed.
 */
class HasYoutubeVideoTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('legacyUrlShapes')]
    public function test_it_extracts_the_id_from_every_legacy_url_shape(string $url, ?string $expected): void
    {
        $this->assertSame($expected, Testimonial::extractYoutubeId($url));
    }

    /** @return array<int, array{string, string|null}> */
    public static function legacyUrlShapes(): array
    {
        return [
            // Real values from dexc8115_3dexperts.sql
            ['https://www.youtube.com/watch?v=7xljJbmknbw&t=21s', '7xljJbmknbw'],
            ['https://www.youtube.com/watch?v=U9YR3gh0Vs8&list=PL6UvqUjki2OuxB1lwVPzaiY6WExkNar6t', 'U9YR3gh0Vs8'],
            ['https://www.youtube.com/shorts/8Dogk6YJdzI', '8Dogk6YJdzI'],
            ['https://www.youtube.com/shorts/iGX0voQWSLM', 'iGX0voQWSLM'],
            ['https://youtu.be/dQw4w9WgXcQ?si=ooeDmwXbBAx2vXGc', 'dQw4w9WgXcQ'],
            ['https://www.youtube.com/watch?v=ZN5d8k0Ii9w', 'ZN5d8k0Ii9w'],
            ['https://www.youtube.com/embed/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],

            // Not a video
            ['https://www.instagram.com/reel/DD4BD7tS71i/', null],
            ['not a url at all', null],
        ];
    }

    public function test_it_returns_null_for_an_empty_url(): void
    {
        $this->assertNull(Testimonial::extractYoutubeId(null));
        $this->assertNull(Testimonial::extractYoutubeId(''));
    }

    public function test_it_caches_the_id_on_save_and_builds_a_cookieless_embed_url(): void
    {
        $testimonial = Testimonial::create([
            'youtube_url' => 'https://www.youtube.com/watch?v=7xljJbmknbw&t=21s',
        ]);

        $this->assertSame('7xljJbmknbw', $testimonial->youtube_id);
        $this->assertStringContainsString(
            'youtube-nocookie.com/embed/7xljJbmknbw',
            (string) $testimonial->embed_url,
        );
    }
}
