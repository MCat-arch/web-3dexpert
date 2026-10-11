<?php

namespace Database\Seeders;

use App\Models\DesignReview;
use App\Models\Portfolio;
use App\Models\PortfolioImage;
use App\Models\PortfolioSpec;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;

class LegacyPortfolioContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedTestimonials();
        $this->seedDesignReviews();
        $this->seedPortfolios();
    }

    private function seedTestimonials(): void
    {
        foreach ($this->testimonials() as $order => $row) {
            Testimonial::updateOrCreate(
                ['youtube_url' => $row['youtube_url']],
                [
                    'youtube_id' => Testimonial::extractYoutubeId($row['youtube_url']),
                    'title' => ['id' => $row['title'], 'en' => $row['title']],
                    'rating' => $row['rating'],
                    'is_highlighted' => $row['is_highlighted'],
                    'is_published' => true,
                    'sort_order' => $order,
                    'created_at' => $row['created_at'],
                ],
            );
        }

        Testimonial::query()
            ->whereNotIn('youtube_url', array_column($this->testimonials(), 'youtube_url'))
            ->delete();
    }

    private function seedDesignReviews(): void
    {
        foreach ($this->designReviews() as $order => $url) {
            DesignReview::updateOrCreate(
                ['youtube_url' => $url],
                [
                    'youtube_id' => DesignReview::extractYoutubeId($url),
                    'sort_order' => $order,
                    'is_published' => true,
                ],
            );
        }

        DesignReview::query()
            ->whereNotIn('youtube_url', $this->designReviews())
            ->delete();
    }

    private function seedPortfolios(): void
    {
        Portfolio::query()->whereNull('legacy_source')->forceDelete();

        foreach ($this->portfolios() as $order => $row) {
            $portfolio = Portfolio::withTrashed()->updateOrCreate(
                ['legacy_source' => 'properties', 'legacy_id' => $row['id']],
                [
                    'title' => ['id' => $row['title'], 'en' => $row['title']],
                    'slug' => $row['slug'],
                    'price' => $row['price'],
                    'location' => $row['location'],
                    'style' => $row['style'],
                    'floors' => $row['floors'],
                    'bedrooms' => $row['bedrooms'],
                    'bathrooms' => $row['bathrooms'],
                    'is_featured' => $row['id'] === 1,
                    'is_published' => true,
                    'sort_order' => $order,
                ],
            );

            if ($portfolio->trashed()) {
                $portfolio->restore();
            }

            $portfolio->images()->delete();
            $portfolio->specs()->delete();

            foreach ($row['images'] as $imageOrder => $image) {
                PortfolioImage::create([
                    'portfolio_id' => $portfolio->id,
                    'image_path' => 'portfolio/legacy/'.$image['file'],
                    'alt_text' => ['id' => $row['title'], 'en' => $row['title']],
                    'is_cover' => $image['is_cover'],
                    'sort_order' => $imageOrder,
                ]);
            }

            foreach ($row['specs'] as $specOrder => [$label, $value]) {
                PortfolioSpec::create([
                    'portfolio_id' => $portfolio->id,
                    'label' => ['id' => $label, 'en' => $label],
                    'value' => ['id' => $value, 'en' => $value],
                    'sort_order' => $specOrder,
                ]);
            }
        }
    }

    private function testimonials(): array
    {
        return [
            ['youtube_url' => 'https://www.youtube.com/shorts/iGX0voQWSLM', 'title' => 'tes', 'rating' => 3, 'is_highlighted' => true, 'created_at' => '2025-06-03 06:55:32'],
            ['youtube_url' => 'https://www.youtube.com/shorts/_leiKFXObLg', 'title' => 'tes', 'rating' => 2, 'is_highlighted' => true, 'created_at' => '2025-06-03 06:55:32'],
            ['youtube_url' => 'https://www.youtube.com/shorts/VB9dt2yvDmQ', 'title' => 'tes', 'rating' => 5, 'is_highlighted' => false, 'created_at' => '2025-06-03 06:55:32'],
            ['youtube_url' => 'https://www.youtube.com/shorts/YOTyMo3SJTw', 'title' => 'testtes', 'rating' => 5, 'is_highlighted' => false, 'created_at' => '2025-06-03 06:55:32'],
            ['youtube_url' => 'https://www.youtube.com/shorts/4hYGXjoGt1o', 'title' => 'tes', 'rating' => 5, 'is_highlighted' => false, 'created_at' => '2025-06-03 06:55:32'],
            ['youtube_url' => 'https://www.youtube.com/shorts/H7zW6Y4-TQc', 'title' => 'tes', 'rating' => 5, 'is_highlighted' => false, 'created_at' => '2025-06-03 06:55:32'],
            ['youtube_url' => 'https://www.youtube.com/shorts/sIOBOgZGXnA', 'title' => 'tes', 'rating' => 5, 'is_highlighted' => false, 'created_at' => '2025-06-03 06:55:32'],
            ['youtube_url' => 'https://www.youtube.com/shorts/iaREqBNR8yk', 'title' => 'tes', 'rating' => 5, 'is_highlighted' => false, 'created_at' => '2025-06-03 06:55:32'],
            ['youtube_url' => 'https://www.youtube.com/shorts/kI5uwIyVFls', 'title' => 'tes', 'rating' => 5, 'is_highlighted' => false, 'created_at' => '2025-06-03 06:55:32'],
            ['youtube_url' => 'https://www.youtube.com/shorts/gSGiB9P8seM', 'title' => 'tes', 'rating' => 5, 'is_highlighted' => false, 'created_at' => '2025-06-03 06:55:32'],
        ];
    }

    private function designReviews(): array
    {
        return [
            'https://www.youtube.com/watch?v=7xljJbmknbw&t=21s',
            'https://www.youtube.com/watch?v=U9YR3gh0Vs8&list=PL6UvqUjki2OuxB1lwVPzaiY6WExkNar6t',
            'https://www.youtube.com/shorts/8Dogk6YJdzI',
        ];
    }

    private function portfolios(): array
    {
        return [
            [
                'id' => 1,
                'title' => 'Desain Rumah Klasik Lantai Bapa HOH di Sidoarjo Jawa Timur',
                'slug' => 'desain-rumah-klasik-lantai-bapa-hoh-di-sidoarjo-jawa-timur',
                'price' => 2500000000,
                'location' => 'Sidoarjo, Jawa Timur',
                'style' => 'Classic-Tropis',
                'floors' => 2,
                'bedrooms' => 5,
                'bathrooms' => 3,
                'specs' => [['Style', 'Classic-Tropis'], ['Ukuran Tanah', '20.3m x 22m'], ['Kamar Tidur', '5'], ['Kamar Mandi', '3'], ['Powder Room', '1'], ['Kapasitas Mobil', '1'], ['Jumlah Lantai', '2'], ['Status Meeting', '100% Online'], ['Lantai 1 Toilet', '2']],
                'images' => [
                    ['file' => 'property-1-cover.jpg', 'source' => 'https://images.unsplash.com/photo-1570129477492-45c003edd2be?w=1200&h=800&fit=crop', 'is_cover' => true],
                    ['file' => 'property-1-gallery.jpg', 'source' => 'https://images.unsplash.com/photo-1568605114967-8130f3a36994?w=1200&h=800&fit=crop', 'is_cover' => false],
                ],
            ],
            [
                'id' => 2,
                'title' => 'Desain Rumah Klasik Lantai Bapa HOH di Sidoarjo Jawa Timur',
                'slug' => 'desain-rumah-klasik-lantai-bapa-hoh-di-sidoarjo-jawa-timur-2',
                'price' => 1450000000,
                'location' => 'Sidoarjo, Jawa Timur',
                'style' => 'Tropis Modern',
                'floors' => null,
                'bedrooms' => 5,
                'bathrooms' => null,
                'specs' => [['Style', 'Tropis Modern'], ['Ukuran Tanah', '12m x 20m'], ['Kamar Tidur', '5']],
                'images' => [
                    ['file' => 'property-2-cover.jpg', 'source' => 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=1200&h=800&fit=crop', 'is_cover' => true],
                    ['file' => 'property-2-gallery.jpg', 'source' => 'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?w=1200&h=800&fit=crop', 'is_cover' => false],
                ],
            ],
            [
                'id' => 3,
                'title' => 'Desain Rumah Tropis Modern',
                'slug' => 'desain-rumah-tropis-modern',
                'price' => 980000000,
                'location' => 'Surabaya, Jawa Timur',
                'style' => 'Modern Minimalis',
                'floors' => null,
                'bedrooms' => 6,
                'bathrooms' => 4,
                'specs' => [['Style', 'Modern Minimalis'], ['Ukuran Tanah', '15m x 25m'], ['Kamar Tidur', '6'], ['Kamar Mandi', '4']],
                'images' => [
                    ['file' => 'property-3-cover.jpg', 'source' => 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=1200&h=800&fit=crop', 'is_cover' => true],
                ],
            ],
            [
                'id' => 4,
                'title' => 'Desain Rumah Mewah 2 Lantai',
                'slug' => 'desain-rumah-mewah-2-lantai',
                'price' => 2100000000,
                'location' => 'Malang, Jawa Timur',
                'style' => null,
                'floors' => 2,
                'bedrooms' => 2,
                'bathrooms' => null,
                'specs' => [['Kamar Tidur', '2']],
                'images' => [
                    ['file' => 'property-4-cover.jpg', 'source' => 'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?w=1200&h=800&fit=crop', 'is_cover' => true],
                ],
            ],
        ];
    }
}
