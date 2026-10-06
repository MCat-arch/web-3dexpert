<?php

use App\Models\Testimonial;

/**
 * Every URL shape present in the production dump, so the migration cannot
 * silently drop an embed.
 */
it('extracts the id from every legacy url shape', function (string $url, ?string $expected) {
    expect(Testimonial::extractYoutubeId($url))->toBe($expected);
})->with([
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
]);

it('returns null for an empty url', function () {
    expect(Testimonial::extractYoutubeId(null))->toBeNull()
        ->and(Testimonial::extractYoutubeId(''))->toBeNull();
});

it('caches the id on save and builds a cookieless embed url', function () {
    $testimonial = Testimonial::create([
        'youtube_url' => 'https://www.youtube.com/watch?v=7xljJbmknbw&t=21s',
    ]);

    expect($testimonial->youtube_id)->toBe('7xljJbmknbw')
        ->and($testimonial->embed_url)->toContain('youtube-nocookie.com/embed/7xljJbmknbw');
});
