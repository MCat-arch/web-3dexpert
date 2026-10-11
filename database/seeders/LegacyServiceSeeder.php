<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceFeature;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The four `layanan_section` rows, reproduced exactly as they exist in the
 * legacy database (dump: dexc8115_3dexperts.sql).
 *
 * ---------------------------------------------------------------------------
 * WHAT THE LEGACY DATA ACTUALLY CONTAINS -- read this before publishing
 * ---------------------------------------------------------------------------
 * All four rows are the SAME package. They are not four products:
 *
 *   id | title                  | original_price | discount_text | price
 *   ---+------------------------+----------------+---------------+----------
 *    1 | Pembangunan Lengkaps   | 24.0002        | Diskon 17,5%  | 98.6002
 *    2 | Pembangunan Lengkap    | 24.000         | Diskon 17,5%  | 98.600
 *    3 | Pembangunan Lengkap    | 24.000         | Diskon 17,5%  | 98.600
 *    4 | Pembangunan Lengkap    | 24.000         | Diskon 17,5%  | 98.600
 *
 * Row 1's title has a trailing "s" and both of its prices have a stray
 * trailing "2". Rows 2-4 differ from each other only in line endings
 * (\n vs \r\n) inside the features JSON, and row 3 repeats
 * "Gambar kerja detail struktur atap" twice. That is the signature of someone
 * pressing Save repeatedly in edit.php while testing, not of four packages.
 *
 * The prices also appear to be the wrong way round. edit.php labels the fields:
 *   original_price -> "Harga Asli"            (and style.css:2335 strikes it out)
 *   price          -> "Harga Setelah Diskon"
 * so `price` is supposed to be LOWER than `original_price`. Here the
 * "discounted" price (98.600) is about four times the "original" (24.000), and
 * "Diskon 17,5%" matches neither figure: 17.5% off 24.000 is 19.800, and
 * 17.5% off 98.600 is 81.345.
 *
 * Per your instruction, this seeder follows the legacy data and does NOT
 * correct any of it. The values below are what legacy had; nothing is inferred.
 * Two switches are provided so you can change that without editing values:
 *
 *   SWAP_PRICES          -- put 98.600 in original_price and 24.000 in price,
 *                           which is what the admin labels imply.
 *   PUBLISH_DUPLICATES   -- false publishes only legacy row 4 (the cleanest of
 *                           the four) and leaves 1-3 unpublished but intact.
 *
 * Both default to the faithful reading: no swap, all four published.
 *
 *     php artisan db:seed --class="Database\Seeders\LegacyServiceSeeder"
 *
 * Idempotent, and slug-compatible with `app:migrate-legacy-data`, which keys
 * services as Str::slug(title).'-'.legacy_id -- so running both does not
 * create duplicates.
 */
class LegacyServiceSeeder extends Seeder
{
    /** Legacy had original_price < price. Set true to read them the other way. */
    private const SWAP_PRICES = false;

    /** Legacy showed all four duplicate cards. Set false to publish only row 4. */
    private const PUBLISH_DUPLICATES = true;

    /** index.php:668-678 mapped brochures to services by hardcoded legacy id. */
    private const BROCHURES = [
        1 => 'brochures/Gambar Perijinan.pdf',
        2 => 'brochures/Arsitektur EKSTERIOR.pdf',
        3 => 'brochures/Arsitektur INTERIOR.pdf',
        4 => 'brochures/PEMBANGUNAN Lengkap.pdf',
    ];

    public function run(): void
    {
        $this->removeDemoServiceDuplicates();

        foreach ($this->legacyRows() as $sortOrder => $row) {
            [$original, $price] = self::SWAP_PRICES
                ? [$row['price'], $row['original_price']]
                : [$row['original_price'], $row['price']];

            $publish = self::PUBLISH_DUPLICATES || $row['id'] === 4;

            // Same slug scheme as MigrateLegacyData::migrateServices(), so this
            // seeder updates those rows instead of creating a second set.
            $service = Service::updateOrCreate(
                ['slug' => Str::slug($row['title']).'-'.$row['id']],
                [
                    'title' => ['id' => $row['title'], 'en' => $row['title_en']],
                    'original_price' => $original,
                    'price' => $price,
                    'discount_text' => ['id' => $row['discount_text'], 'en' => $row['discount_text_en']],
                    'brochure_path' => self::BROCHURES[$row['id']] ?? null,
                    'sort_order' => $sortOrder,
                    'is_published' => $publish,
                    // Left true on purpose: the duplication and the price
                    // ordering are real data problems, and this flag is how the
                    // admin panel will surface them later.
                    'needs_review' => true,
                ],
            );

            $service->features()->delete();

            foreach ($row['features'] as $order => $label) {
                ServiceFeature::create([
                    'service_id' => $service->id,
                    'label' => ['id' => $label, 'en' => $label],
                    // Legacy rendered features[0] as a bare <strong> outside the
                    // <li>. Only rows 2-4 actually have a heading there; row 1's
                    // first entry is a real feature, because its JSON opens with
                    // two blank entries that json_decode drops.
                    'is_heading' => str_ends_with(trim($label), ':'),
                    'sort_order' => $order,
                ]);
            }
        }

    }

    /**
     * Older DatabaseSeeder versions created four demo services with these
     * slugs. They have no legacy id or brochure, so remove only those known
     * demo rows before restoring the canonical legacy records.
     */
    private function removeDemoServiceDuplicates(): void
    {
        Service::query()
            ->whereIn('slug', [
                'pembangunan-lengkap',
                'arsitektur-interior',
                'arsitektur-eksterior',
                'gambar-perizinan',
            ])
            ->whereNull('brochure_path')
            ->where('needs_review', false)
            ->get()
            ->each
            ->delete();
    }

    /**
     * Verbatim from `INSERT INTO layanan_section` in dexc8115_3dexperts.sql.
     * English titles are translations added for this migration -- legacy had
     * no English version of the services section at all.
     *
     * @return array<int, array<string, mixed>>
     */
    private function legacyRows(): array
    {
        $structuralFeatures = [
            'Gambar kerja detail pondasi',
            'Gambar kerja detail denah balok',
            'Gambar kerja detail denah kolom',
            'Gambar kerja detail plat lantai',
            'Gambar kerja detail tangga',
            'Gambar kerja detail rencana atap',
            'Gambar kerja detail struktur atap',
        ];

        return [

            [
                'id' => 1,
                'title' => 'Pembangunan Lengkap',
                'title_en' => 'Complete Construction Package',
                'original_price' => 24000,
                'price' => 98600,
                'discount_text' => 'Diskon 17,5%',
                'discount_text_en' => '17.5% off',
                'features' => array_merge(['Gambar Kerja Struktur:'], $structuralFeatures),
            ],
            [
                'id' => 2,
                'title' => 'Pembangunan Lengkap',
                'title_en' => 'Complete Construction Package',
                'original_price' => 24000,
                'price' => 98600,
                'discount_text' => 'Diskon 17,5%',
                'discount_text_en' => '17.5% off',
                // Legacy row 3 lists the last feature twice. Preserved.
                'features' => array_merge(
                    ['Gambar Kerja Struktur:'],
                    $structuralFeatures,
                    ['Gambar kerja detail struktur atap'],
                ),
            ],
            [
                'id' => 3,
                'title' => 'Pembangunan Lengkap',
                'title_en' => 'Complete Construction Package',
                'original_price' => 24000,
                'price' => 98600,
                'discount_text' => 'Diskon 17,5%',
                'discount_text_en' => '17.5% off',
                'features' => array_merge(['Gambar Kerja Struktur:'], $structuralFeatures),
            ],
            [
                'id' => 4,
                'title' => 'Pembangunan Lengkap',
                'title_en' => 'Complete Construction Package',
                'original_price' => 24000,
                'price' => 98600,
                'discount_text' => 'Diskon 17,5%',
                'discount_text_en' => '17.5% off',
                'features' => array_merge(['Gambar Kerja Struktur:'], $structuralFeatures),
            ],
        ];
    }
}
