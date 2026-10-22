<?php

namespace Database\Seeders;

use App\Models\DesignReview;
use App\Models\HeroSlide;
use App\Models\JobVacancy;
use App\Models\Portfolio;
use App\Models\PortfolioImage;
use App\Models\PortfolioSpec;
use App\Models\Service;
use App\Models\ServiceFeature;
use App\Models\Testimonial;
use App\Models\ValueProposition;
use App\Support\Settings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo data, so a fresh clone has something to look at.
 *
 * NOTE: this is NOT the production data path. Real content comes from
 * `php artisan app:migrate-legacy-data`. Never run both against the same
 * database.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(LandingContentSeeder::class);
        // Keep the legacy service cards (including their brochure mapping) as
        // the canonical landing-page service data. Do not create the old demo
        // package set here, otherwise a fresh seed renders duplicate services.
        $this->call(LegacyServiceSeeder::class);
        $this->seedHeroSlides();
        $this->seedValuePropositions();
        $this->call(LegacyPortfolioContentSeeder::class);
        $this->seedJobs();
        $this->call(CourseSeeder::class);

        $this->command->warn('No admin user was created. Run: php artisan app:create-admin');
    }

    private function seedSettings(): void
    {
        Settings::set('home_heading', [
            'id' => 'Satukan Imajinasi dan Realitas Bersama Arsitek Profesional',
            'en' => 'Bring Your Imagination to Life With Professional Architects',
        ]);

        Settings::set('home_subheading', [
            'id' => 'Desain rumah, interior, dan eksterior yang siap dibangun.',
            'en' => 'House, interior and exterior designs that are ready to build.',
        ]);

        Settings::set('stat_projects', 736);
        Settings::set('stat_clients', 581);

        Settings::set('why_description', [
            'id' => 'Kami memahami kebutuhan Anda lebih dulu, supaya revisi desain minimal.',
            'en' => 'We understand your needs first, so design revisions stay minimal.',
        ]);

        Settings::set('company_name', '3D Expert');
        Settings::set('company_address', [
            'id' => "Head Office PT Luxora Aura Semesta\nJl. Bendul Merisi Selatan 7 no 57 Surabaya",
            'en' => "Head Office PT Luxora Aura Semesta\nJl. Bendul Merisi Selatan 7 no 57 Surabaya",
        ]);
        Settings::set('company_maps_url', 'https://share.google/1hqg3Cm8EAEpmMP05');
        Settings::set('company_email', 'info@3dexpert.id');
        Settings::set('company_phone', '+6282338389488');
        Settings::set('company_whatsapp', 'https://wa.me/6282338389488');
        Settings::set('location_video_url', 'https://www.youtube.com/watch?v=ZN5d8k0Ii9w');
        Settings::set('why_video_url', 'https://www.youtube.com/watch?v=ZN5d8k0Ii9w');
        Settings::set('process_link_url', 'https://wa.me/6282338389488');

        Settings::set('seo_title', [
            'id' => '3D Expert — Jasa Desain Arsitektur & Interior',
            'en' => '3D Expert — Architecture & Interior Design Services',
        ]);
        Settings::set('seo_description', [
            'id' => 'Jasa desain rumah, interior, eksterior, dan gambar kerja perizinan siap bangun.',
            'en' => 'House, interior and exterior design plus permit drawing services ready to build.',
        ]);
    }

    private function seedHeroSlides(): void
    {
        if (HeroSlide::count() > 0) {
            return;
        }

        $heroImages = [
            'hero/hero_1_1750153978.jpg',
            'hero/hero_2_1750153978.jpg',
            'hero/hero_4_1750153978.jpg',
        ];

        foreach ($heroImages as $i => $path) {
            HeroSlide::create([
                'image_path' => $path,
                'alt_text' => ['id' => '3D Expert Hero '.($i + 1), 'en' => '3D Expert Hero '.($i + 1)],
                'sort_order' => $i + 1,
                'is_active' => true,
            ]);
        }
    }

    /** The four real service packages, matching the four PDFs in assets/pdf/. */
    private function seedServices(): void
    {
        $packages = [
            [
                'id' => 'Pembangunan Lengkap', 'en' => 'Complete Construction Package',
                'price' => 98_600_000, 'original' => 119_500_000,
                'features' => [
                    ['Gambar Kerja Struktur:', true],
                    ['Gambar kerja detail pondasi', false],
                    ['Gambar kerja detail denah balok', false],
                    ['Gambar kerja detail denah kolom', false],
                    ['Gambar kerja detail plat lantai', false],
                    ['Gambar kerja detail tangga', false],
                    ['Gambar kerja detail rencana atap', false],
                    ['Gambar kerja detail struktur atap', false],
                ],
            ],
            [
                'id' => 'Arsitektur Interior', 'en' => 'Interior Architecture',
                'price' => 45_000_000, 'original' => 55_000_000,
                'features' => [['Desain interior per ruang', false], ['Detail furnitur', false], ['Render 3D', false]],
            ],
            [
                'id' => 'Arsitektur Eksterior', 'en' => 'Exterior Architecture',
                'price' => 38_000_000, 'original' => 46_000_000,
                'features' => [['Desain fasad', false], ['Animasi 3D', false], ['Pemilihan material', false]],
            ],
            [
                'id' => 'Gambar Perizinan', 'en' => 'Permit Drawings',
                'price' => 24_000_000, 'original' => 29_000_000,
                'features' => [['Gambar kerja perizinan', false], ['Pendampingan sidang', false], ['Garansi lolos perizinan', false]],
            ],
        ];

        foreach ($packages as $i => $package) {
            $service = Service::create([
                'title' => ['id' => $package['id'], 'en' => $package['en']],
                'slug' => Str::slug($package['id']),
                'price' => $package['price'],
                'original_price' => $package['original'],
                'is_published' => true,
                'sort_order' => $i,
            ]);

            foreach ($package['features'] as $order => [$label, $isHeading]) {
                ServiceFeature::create([
                    'service_id' => $service->id,
                    'label' => ['id' => $label, 'en' => $label],
                    'is_heading' => $isHeading,
                    'sort_order' => $order,
                ]);
            }
        }
    }

    private function seedValuePropositions(): void
    {
        $benefits = [
            ['id' => 'Meminimalisir revisi desain', 'en' => 'Fewer design revisions'],
            ['id' => 'Meningkatkan kepuasan pengguna', 'en' => 'Higher client satisfaction'],
            ['id' => 'Efisiensi biaya pembangunan', 'en' => 'Lower build cost'],
        ];

        $processes = [
            ['id' => 'Konsultasi gratis', 'en' => 'Free consultation'],
            ['id' => 'Survey lokasi proyek', 'en' => 'Site survey'],
            ['id' => 'Koordinasi desain', 'en' => 'Design coordination'],
            ['id' => 'Pengawasan konstruksi', 'en' => 'Construction supervision'],
        ];

        foreach ([[ValueProposition::GROUP_BENEFIT, $benefits], [ValueProposition::GROUP_PROCESS, $processes]] as [$group, $items]) {
            foreach ($items as $i => $label) {
                ValueProposition::create(['group' => $group, 'label' => $label, 'sort_order' => $i]);
            }
        }
    }

    private function seedTestimonials(): void
    {
        // At least 2 highlighted -- the rule edit.php enforced, because the
        // homepage puts highlighted items in a 2-across hero row.
        Testimonial::factory()->highlighted()->count(2)->create();
        Testimonial::factory()->count(8)->create();
        DesignReview::factory()->count(4)->create();
    }

    private function seedPortfolios(): void
    {
        Portfolio::factory()->featured()->create()->each(function (Portfolio $portfolio) {
            $this->attachDemoImagesAndSpecs($portfolio);
        });

        Portfolio::factory()->count(18)->create()->each(function (Portfolio $portfolio) {
            $this->attachDemoImagesAndSpecs($portfolio);
        });
    }

    private function attachDemoImagesAndSpecs(Portfolio $portfolio): void
    {
        foreach (range(0, 3) as $i) {
            PortfolioImage::create([
                'portfolio_id' => $portfolio->id,
                // Placeholder service, so the demo renders without local files.
                'external_url' => "https://picsum.photos/seed/{$portfolio->id}-{$i}/1200/800",
                'is_cover' => $i === 0,
                'sort_order' => $i,
            ]);
        }

        $specs = [
            ['Gaya', 'Style', $portfolio->style],
            ['Ukuran Tanah', 'Land Size', $portfolio->land_size.' m²'],
            ['Jumlah Lantai', 'Floors', (string) $portfolio->floors],
            ['Kamar Tidur', 'Bedrooms', (string) $portfolio->bedrooms],
            ['Kamar Mandi', 'Bathrooms', (string) $portfolio->bathrooms],
        ];

        foreach ($specs as $order => [$labelId, $labelEn, $value]) {
            PortfolioSpec::create([
                'portfolio_id' => $portfolio->id,
                'label' => ['id' => $labelId, 'en' => $labelEn],
                'value' => ['id' => $value, 'en' => $value],
                'sort_order' => $order,
            ]);
        }
    }

    private function seedJobs(): void
    {
        JobVacancy::factory()->count(5)->create();
        JobVacancy::factory()->unpublished()->count(2)->create();
    }
}
