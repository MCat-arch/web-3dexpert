<?php

namespace App\Support;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\HeroSlide;
use App\Models\MediaAsset;
use App\Models\PortfolioImage;
use App\Models\Service;
use App\Models\ValueProposition;

/**
 * Where a media asset is referenced from.
 *
 * Models store the public route path in *_path columns. CourseLesson stores a
 * FK to the asset instead, so both forms are checked here.
 */
final class MediaUsage
{
    /**
     * @return array<string, int>
     */
    public static function references(MediaAsset $asset): array
    {
        $references = [
            'Hero slide' => HeroSlide::query()->where('image_path', $asset->path)->count(),
            'Layanan (ikon)' => Service::query()->where('icon_path', $asset->path)->count(),
            'Layanan (brosur)' => Service::query()->where('brochure_path', $asset->path)->count(),
            'Gambar portofolio' => PortfolioImage::query()->where('image_path', $asset->path)->count(),
            'Sampul kursus' => Course::query()->where('cover_path', $asset->path)->count(),
            'Lampiran materi' => CourseLesson::query()->where('attachment_media_id', $asset->getKey())->count(),
            'Value proposition' => ValueProposition::query()->where('icon_path', $asset->path)->count(),
        ];

        return array_filter($references, fn (int $count) => $count > 0);
    }

    public static function isReferenced(MediaAsset $asset): bool
    {
        return self::references($asset) !== [];
    }
}
