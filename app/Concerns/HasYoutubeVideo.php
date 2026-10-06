<?php

namespace App\Concerns;

/**
 * Extracts and caches a YouTube video id from a URL.
 *
 * Legacy index.php parsed the URL with a getYouTubeID() helper on every loop
 * iteration while building <iframe> markup inside an echo. Here the id is
 * resolved once on save and stored, so views never parse anything.
 *
 * Handles the four URL shapes present in the production data:
 *   youtube.com/watch?v=ID   (with extra params such as &t= and &list=)
 *   youtu.be/ID
 *   youtube.com/shorts/ID
 *   youtube.com/embed/ID
 */
trait HasYoutubeVideo
{
    public static function extractYoutubeId(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        $patterns = [
            '#youtu\.be/([A-Za-z0-9_-]{11})#',
            '#youtube\.com/shorts/([A-Za-z0-9_-]{11})#',
            '#youtube\.com/embed/([A-Za-z0-9_-]{11})#',
            '#youtube\.com/live/([A-Za-z0-9_-]{11})#',
            '#[?&]v=([A-Za-z0-9_-]{11})#',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    public function getEmbedUrlAttribute(): ?string
    {
        if (blank($this->youtube_id)) {
            return null;
        }

        return 'https://www.youtube-nocookie.com/embed/'.$this->youtube_id
            .'?rel=0&modestbranding=1';
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (blank($this->youtube_id)) {
            return null;
        }

        return 'https://i.ytimg.com/vi/'.$this->youtube_id.'/hqdefault.jpg';
    }

    protected static function bootHasYoutubeVideo(): void
    {
        static::saving(function ($model) {
            if ($model->isDirty('youtube_url')) {
                $model->youtube_id = static::extractYoutubeId($model->youtube_url);
            }
        });
    }
}
