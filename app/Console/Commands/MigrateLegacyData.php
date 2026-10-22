<?php

namespace App\Console\Commands;

use App\Models\DesignReview;
use App\Models\HeroSlide;
use App\Models\JobVacancy;
use App\Models\LegacyRedirect;
use App\Models\Portfolio;
use App\Models\PortfolioImage;
use App\Models\PortfolioSpec;
use App\Models\Service;
use App\Models\ServiceFeature;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\ValueProposition;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Moves the legacy 3D Expert database into the new schema.
 *
 * DESIGN CONSTRAINTS
 *  - Idempotent: safe to run repeatedly. Rows are matched on
 *    (legacy_source, legacy_id) or on a natural key, and updated rather than
 *    duplicated.
 *  - Transactional: one transaction for the whole run, so a failure halfway
 *    leaves nothing behind.
 *  - Non-destructive: nothing in the legacy database is written to or deleted.
 *  - Loud about ambiguity: anything it cannot decide confidently is flagged
 *    (needs_review / left unpublished) and listed in the closing report rather
 *    than silently guessed.
 *
 * WHY THIS IS THE RISKIEST STEP: the production schema does not match the SQL
 * dump. The dump has no desain_rumah/galeri_rumah even though edit.php reads
 * and writes them (you confirmed they exist in production), and the live
 * error_log records "Unknown column 'likes'" at index.php:66 for a column the
 * dump does have. So every legacy table is probed with Schema::hasTable() and
 * every column with hasColumn() before it is touched.
 *
 * USAGE
 *   php artisan app:migrate-legacy-data --dry-run     # report only
 *   php artisan app:migrate-legacy-data               # commit
 *   php artisan app:migrate-legacy-data --fresh       # wipe new tables first
 */
class MigrateLegacyData extends Command
{
    protected $signature = 'app:migrate-legacy-data
                            {--dry-run : Roll back at the end and report only}
                            {--fresh : Truncate the destination tables first}
                            {--connection= : Legacy DB connection name}';

    protected $description = 'Migrate the legacy 3D Expert database into the new schema';

    private ConnectionInterface $legacy;

    /** Human-readable notes surfaced in the closing report. */
    private array $warnings = [];

    private array $counts = [];

    public function handle(): int
    {
        $connectionName = $this->option('connection') ?: config('site.legacy_connection');

        try {
            $this->legacy = DB::connection($connectionName);
            $this->legacy->getPdo();
        } catch (\Throwable $e) {
            $this->error("Cannot connect to legacy connection [{$connectionName}]: {$e->getMessage()}");
            $this->line('Add a "legacy" connection to config/database.php and set the LEGACY_DB_* keys in .env.');

            return self::FAILURE;
        }

        $this->info("Legacy connection: {$connectionName}");
        $this->newLine();

        $this->reportLegacyInventory();

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN -- everything will be rolled back at the end.');
        }

        DB::beginTransaction();

        try {
            if ($this->option('fresh')) {
                $this->truncateDestination();
            }

            $this->migrateSettings();
            $this->migrateHeroSlides();
            $this->migrateServices();
            $this->migrateValuePropositions();
            $this->migrateTestimonials();
            $this->migrateDesignReviews();
            $this->migratePortfoliosFromProperties();
            $this->migratePortfoliosFromDesainRumah();
            $this->seedFeaturedPortfolio();
            $this->migrateJobVacancies();
            $this->migrateUsers();
            $this->buildRedirectMap();

            if ($this->option('dry-run')) {
                DB::rollBack();
                $this->newLine();
                $this->warn('Rolled back (dry run).');
            } else {
                DB::commit();
                Settings::flush();
                $this->newLine();
                $this->info('Committed.');
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->newLine();
            $this->error('Rolled back. '.$e->getMessage());
            $this->line($e->getTraceAsString());

            return self::FAILURE;
        }

        $this->printReport();

        return self::SUCCESS;
    }

    /* ===================================================================== */
    /* Inventory */
    /* ===================================================================== */

    /**
     * Task 3.2 from the implementation plan: report what is actually in the
     * legacy database before touching anything, because it differs from the
     * dump.
     */
    private function reportLegacyInventory(): void
    {
        $expected = [
            'features_section', 'hero_banner', 'layanan_section', 'mengapa_section',
            'testimonial_section', 'review_design', 'properties', 'property_images',
            'property_details', 'desain_rumah', 'galeri_rumah', 'job_detail',
            'alur_section', 'location_section', 'users',
        ];

        $rows = [];
        foreach ($expected as $table) {
            if ($this->legacyHasTable($table)) {
                $rows[] = [$table, 'present', (string) $this->legacy->table($table)->count()];
            } else {
                $rows[] = [$table, 'MISSING', '-'];
            }
        }

        $this->table(['legacy table', 'status', 'rows'], $rows);

        foreach (['desain_rumah', 'galeri_rumah'] as $table) {
            if (! $this->legacyHasTable($table)) {
                $this->warnings[] = "Legacy table `{$table}` is absent. edit.php reads and "
                    .'writes it, so those handlers were dead code on this database. Nothing '
                    .'to migrate from it.';
            }
        }

        if ($this->legacyHasTable('properties') && ! $this->legacyHasColumn('properties', 'likes')) {
            $this->warnings[] = '`properties.likes` is absent (this is the column behind the '
                .'live fatal at index.php:66). is_featured will be set on the newest portfolio '
                .'instead -- review it in the admin panel.';
        }
    }

    /* ===================================================================== */
    /* Settings */
    /* ===================================================================== */

    private function migrateSettings(): void
    {
        // features_section -> homepage headline + counters
        if ($this->legacyHasTable('features_section')) {
            $row = $this->legacy->table('features_section')->orderBy('id')->first();
            if ($row) {
                Settings::set('home_heading', $row->judul_konten ?? null);
                Settings::set('home_subheading', $row->sub_konten ?? null);
                Settings::set('stat_projects', $row->project ?? 0);
                Settings::set('stat_clients', $row->client ?? 0);
                $this->bump('settings', 4);
            }
        }

        // mengapa_section: row 0 held the description + video; later rows were
        // list items (migrated separately into value_propositions).
        if ($this->legacyHasTable('mengapa_section')) {
            $main = $this->legacy->table('mengapa_section')->orderBy('id')->first();
            if ($main) {
                Settings::set('why_description', $main->description ?? null);
                Settings::set('why_video_url', $main->video_url ?? null);
                $this->bump('settings', 2);
            }
        }

        // alur_section / location_section: singleton rows addressed by LIMIT 1.
        if ($this->legacyHasTable('alur_section')) {
            $row = $this->legacy->table('alur_section')->orderBy('id')->first();
            if ($row) {
                Settings::set('process_link_url', $row->link_url ?? null);
                $this->bump('settings');
            }
        }

        if ($this->legacyHasTable('location_section')) {
            $row = $this->legacy->table('location_section')->orderBy('id')->first();
            if ($row) {
                Settings::set('location_video_url', $row->video_url ?? null);
                $this->bump('settings');
            }
        }

        // Company details were hardcoded in partials/footer.php, not in the DB.
        // Seed placeholders so the footer renders; fill them in the admin panel.
        foreach ([
            'company_name' => '3D Experts',
            'company_whatsapp' => '',
            'company_email' => '',
            'company_instagram' => '',
            'company_youtube' => '',
        ] as $key => $default) {
            if (blank(Settings::get($key))) {
                Settings::set($key, $default);
            }
        }

        $this->warnings[] = 'Company contact details (address, phone, WhatsApp, socials) were '
            .'hardcoded in partials/footer.php, not stored in the database. They are seeded '
            .'empty -- fill them in under Settings before launch.';

        $this->line('  settings ....... done');
    }

    /* ===================================================================== */
    /* Hero slides */
    /* ===================================================================== */

    private function migrateHeroSlides(): void
    {
        if (! $this->legacyHasTable('hero_banner')) {
            return;
        }

        foreach ($this->legacy->table('hero_banner')->orderBy('id')->get() as $i => $row) {
            if (blank($row->photo_name ?? null)) {
                continue;
            }

            HeroSlide::updateOrCreate(
                ['image_path' => 'hero/'.$row->photo_name],
                ['sort_order' => $i, 'is_active' => true],
            );
            $this->bump('hero_slides');
        }

        $this->warnings[] = 'Hero slides reference files at hero/<filename>. After migrating '
            .'the legacy database, run `php artisan app:import-media-assets` to import local '
            .'media into the database, then verify that each referenced hero file exists.';

        $this->line('  hero slides .... '.($this->counts['hero_slides'] ?? 0));
    }

    /* ===================================================================== */
    /* Services */
    /* ===================================================================== */

    /**
     * You confirmed (#4) that all four layanan_section rows are real services.
     * The production data has all four titled "Pembangunan Lengkap(s)" with
     * near-identical feature lists, which means the TITLES are corrupted, not
     * the rows. There are also exactly four brochures in assets/pdf/:
     * PEMBANGUNAN Lengkap, Arsitektur INTERIOR, Arsitektur EKSTERIOR,
     * Gambar Perijinan -- so all four rows are migrated and every one whose
     * title looks corrupted is flagged needs_review and left unpublished.
     */
    private function migrateServices(): void
    {
        if (! $this->legacyHasTable('layanan_section')) {
            return;
        }

        $seenTitles = [];

        foreach ($this->legacy->table('layanan_section')->orderBy('id')->get() as $i => $row) {
            $title = trim((string) ($row->title ?? ''));
            $priceParsed = $this->parseLegacyPrice($row->price ?? null);
            $originalParsed = $this->parseLegacyPrice($row->original_price ?? null);

            $needsReview = false;
            $reasons = [];

            if ($title === '') {
                $title = 'Layanan '.$row->id;
                $needsReview = true;
                $reasons[] = 'empty title';
            }

            $normalized = Str::lower(preg_replace('/[^a-z]/i', '', $title));
            if (isset($seenTitles[$normalized])) {
                $needsReview = true;
                $reasons[] = 'duplicate title (also legacy id '.$seenTitles[$normalized].')';
            }
            $seenTitles[$normalized] = $row->id;

            if ($priceParsed['flagged']) {
                $needsReview = true;
                $reasons[] = "price '{$row->price}' did not parse cleanly";
            }
            if ($originalParsed['flagged']) {
                $needsReview = true;
                $reasons[] = "original_price '{$row->original_price}' did not parse cleanly";
            }

            $service = Service::updateOrCreate(
                ['slug' => Str::slug($title).'-'.$row->id],
                [
                    'title' => [config('app.fallback_locale') => $title],
                    'price' => $priceParsed['value'],
                    'original_price' => $originalParsed['value'],
                    'discount_text' => [config('app.fallback_locale') => $row->discount_text ?? null],
                    'sort_order' => $i,
                    // Never auto-publish something flagged for review.
                    'is_published' => ! $needsReview,
                    'needs_review' => $needsReview,
                ],
            );

            if ($needsReview) {
                $this->warnings[] = "Service (legacy id {$row->id}) flagged: "
                    .implode('; ', $reasons).'. Left unpublished.';
            }

            $this->migrateServiceFeatures($service, $row->features ?? null);
            $this->bump('services');
        }

        $this->line('  services ....... '.($this->counts['services'] ?? 0));
    }

    /**
     * Legacy stored the feature list as a JSON array inside a `text` column,
     * with literal \r\n sequences baked into the string. Entries ending in ':'
     * were used as visual headings ("Gambar Kerja Struktur:").
     */
    private function migrateServiceFeatures(Service $service, ?string $raw): void
    {
        $service->features()->delete();

        if (blank($raw)) {
            return;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            // Fall back to line splitting rather than losing the content.
            $decoded = preg_split('/\r\n|\r|\n/', $raw) ?: [];
            $this->warnings[] = "Service '{$service->slug}': features column was not valid "
                .'JSON; split on line breaks instead. Verify the list.';
        }

        $order = 0;
        foreach ($decoded as $label) {
            $label = trim((string) $label);
            if ($label === '') {
                continue;
            }

            ServiceFeature::create([
                'service_id' => $service->id,
                'label' => [config('app.fallback_locale') => $label],
                'is_heading' => str_ends_with($label, ':'),
                'sort_order' => $order++,
            ]);
            $this->bump('service_features');
        }
    }

    /**
     * Legacy prices were varchar(50) holding values like '24.000' (Indonesian
     * thousands separator) and '98.6002' / '24.0002' (a stray trailing digit --
     * almost certainly a typo during editing).
     *
     * Rule: interpret '.' and ',' as thousands separators, since every observed
     * value is a whole rupiah amount. Anything left over after stripping
     * non-digits is flagged rather than guessed -- a wrong price is worse than
     * a blank one.
     */
    private function parseLegacyPrice(?string $raw): array
    {
        if (blank($raw)) {
            return ['value' => null, 'flagged' => false];
        }

        $raw = trim($raw);
        $digits = preg_replace('/\D/', '', $raw);

        if ($digits === '' || $digits === null) {
            return ['value' => null, 'flagged' => true];
        }

        // Group separators in Indonesian formatting come in threes. A final
        // group that is not 3 digits long means the string is malformed --
        // exactly the '24.0002' case.
        $groups = preg_split('/[.,]/', $raw);
        $flagged = false;

        if (count($groups) > 1) {
            foreach (array_slice($groups, 1) as $group) {
                if (strlen(preg_replace('/\D/', '', $group)) !== 3) {
                    $flagged = true;
                    break;
                }
            }
        }

        return ['value' => (float) $digits, 'flagged' => $flagged];
    }

    /* ===================================================================== */
    /* Value propositions */
    /* ===================================================================== */

    /**
     * mengapa_section held two lists in one table. Row 0 was the "main" record
     * (already migrated into settings). Every later row carried a value in
     * either list1 or list2, and deletion was implemented as SET col = NULL,
     * so all-null orphan rows accumulated permanently. Those are skipped.
     */
    private function migrateValuePropositions(): void
    {
        if (! $this->legacyHasTable('mengapa_section')) {
            return;
        }

        $rows = $this->legacy->table('mengapa_section')->orderBy('id')->get();
        $skippedOrphans = 0;
        $order = ['benefit' => 0, 'process' => 0];

        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue; // the "main" row
            }

            $list1 = trim((string) ($row->list1 ?? ''));
            $list2 = trim((string) ($row->list2 ?? ''));

            if ($list1 === '' && $list2 === '') {
                $skippedOrphans++;

                continue;
            }

            foreach ([
                ValueProposition::GROUP_BENEFIT => $list1,
                ValueProposition::GROUP_PROCESS => $list2,
            ] as $group => $label) {
                if ($label === '') {
                    continue;
                }

                // Same reason as upsertSpec(): `label` is translatable, so it
                // cannot be an updateOrCreate() where-key.
                $locale = config('app.fallback_locale');

                $proposition = ValueProposition::where('group', $group)
                    ->where('label->'.$locale, $label)
                    ->first();

                if (! $proposition) {
                    $proposition = new ValueProposition(['group' => $group]);
                    $proposition->setTranslation('label', $locale, $label);
                }

                $proposition->sort_order = $order[$group]++;
                $proposition->is_active = true;
                $proposition->save();

                $this->bump('value_propositions');
            }
        }

        if ($skippedOrphans > 0) {
            $this->warnings[] = "Skipped {$skippedOrphans} all-null orphan row(s) in "
                .'mengapa_section -- these were left behind by legacy deletes, which nulled '
                .'the column instead of removing the row. Nothing was lost.';
        }

        $this->line('  value props .... '.($this->counts['value_propositions'] ?? 0));
    }

    /* ===================================================================== */
    /* Testimonials & design reviews (kept separate per decision #1) */
    /* ===================================================================== */

    private function migrateTestimonials(): void
    {
        if (! $this->legacyHasTable('testimonial_section')) {
            return;
        }

        foreach ($this->legacy->table('testimonial_section')->orderBy('id')->get() as $i => $row) {
            if (blank($row->youtube_url ?? null)) {
                continue;
            }

            $id = Testimonial::extractYoutubeId($row->youtube_url);
            if ($id === null) {
                $this->warnings[] = "Testimonial (legacy id {$row->id}): could not extract a "
                    ."YouTube id from '{$row->youtube_url}'. Imported unpublished.";
            }

            Testimonial::updateOrCreate(
                ['youtube_url' => $row->youtube_url],
                [
                    'title' => [config('app.fallback_locale') => $row->title ?? null],
                    'rating' => max(1, min(5, (int) ($row->rating ?? 5))),
                    'is_highlighted' => (bool) ($row->is_highlighted ?? false),
                    'is_published' => $id !== null,
                    'sort_order' => $i,
                ],
            );
            $this->bump('testimonials');
        }

        // Carry over the legacy rule from edit.php: at least 2 highlighted.
        $highlighted = Testimonial::published()->highlighted()->count();
        if ($highlighted < Testimonial::MIN_HIGHLIGHTED) {
            $this->warnings[] = "Only {$highlighted} testimonial(s) are highlighted, but the "
                .'homepage hero row needs '.Testimonial::MIN_HIGHLIGHTED
                .' (the rule edit.php enforced). Highlight more in the admin panel.';
        }

        $this->line('  testimonials ... '.($this->counts['testimonials'] ?? 0));
    }

    private function migrateDesignReviews(): void
    {
        if (! $this->legacyHasTable('review_design')) {
            return;
        }

        foreach ($this->legacy->table('review_design')->orderBy('id')->get() as $i => $row) {
            if (blank($row->youtube_url ?? null)) {
                continue;
            }

            $id = DesignReview::extractYoutubeId($row->youtube_url);

            DesignReview::updateOrCreate(
                ['youtube_url' => $row->youtube_url],
                ['sort_order' => $i, 'is_published' => $id !== null],
            );
            $this->bump('design_reviews');
        }

        $this->line('  design reviews . '.($this->counts['design_reviews'] ?? 0));
    }

    /* ===================================================================== */
    /* Portfolios -- unified from BOTH legacy modules */
    /* ===================================================================== */

    private function migratePortfoliosFromProperties(): void
    {
        if (! $this->legacyHasTable('properties')) {
            return;
        }

        foreach ($this->legacy->table('properties')->orderBy('id')->get() as $i => $row) {
            $title = trim((string) ($row->title ?? '')) ?: 'Desain '.$row->id;

            $portfolio = Portfolio::updateOrCreate(
                ['legacy_source' => 'properties', 'legacy_id' => $row->id],
                [
                    'title' => [config('app.fallback_locale') => $title],
                    'price' => $row->price ?? null,
                    'location' => $row->location ?? null,
                    'sort_order' => $i,
                    'is_published' => true,
                ],
            );

            $this->migratePropertyImages($portfolio, $row->id);
            $this->migratePropertyDetails($portfolio, $row->id);
            $this->bump('portfolios_from_properties');
        }

        $this->line('  portfolios (properties) .... '
            .($this->counts['portfolios_from_properties'] ?? 0));
    }

    private function migratePropertyImages(Portfolio $portfolio, int $legacyId): void
    {
        if (! $this->legacyHasTable('property_images')) {
            return;
        }

        $images = $this->legacy->table('property_images')
            ->where('property_id', $legacyId)->orderBy('id')->get();

        foreach ($images as $i => $img) {
            $url = trim((string) ($img->image_url ?? ''));
            if ($url === '') {
                continue;
            }

            // Legacy mixed absolute external URLs (Unsplash) with local
            // filenames in the same column.
            $isExternal = Str::startsWith($url, ['http://', 'https://']);

            PortfolioImage::updateOrCreate(
                [
                    'portfolio_id' => $portfolio->id,
                    ...($isExternal
                        ? ['external_url' => $url]
                        : ['image_path' => 'portfolio/'.ltrim($url, '/')]),
                ],
                [
                    'is_cover' => (bool) ($img->is_cover ?? false),
                    'sort_order' => $i,
                ],
            );
            $this->bump($isExternal ? 'images_external' : 'images_local');
        }

        // Guarantee exactly one cover, so the card grid never renders blank.
        $this->ensureSingleCover($portfolio);
    }

    private function migratePropertyDetails(Portfolio $portfolio, int $legacyId): void
    {
        if (! $this->legacyHasTable('property_details')) {
            return;
        }

        $details = $this->legacy->table('property_details')
            ->where('property_id', $legacyId)->orderBy('id')->get();

        foreach ($details as $i => $detail) {
            $label = trim((string) ($detail->label ?? ''));
            $value = trim((string) ($detail->value ?? ''));
            if ($label === '' || $value === '') {
                continue;
            }

            $this->upsertSpec($portfolio, $label, $value, $i);

            // Promote the specs that the portfolio index filters on.
            $this->promoteSpecToColumn($portfolio, $label, $value);
        }

        $portfolio->save();
    }

    /**
     * You confirmed (#2) these tables exist in production.
     *
     * desain_rumah stored specs as fixed columns; properties stored the same
     * information as property_details rows. The row shape wins, so each
     * desain_rumah column becomes a portfolio_specs row (and the filterable
     * few are also promoted to real columns).
     */
    private function migratePortfoliosFromDesainRumah(): void
    {
        if (! $this->legacyHasTable('desain_rumah')) {
            return;
        }

        // legacy column => [spec label (id), promoted portfolio column|null]
        $map = [
            'style' => ['Gaya', 'style'],
            'ukuran_tanah' => ['Ukuran Tanah', 'land_size'],
            'jumlah_lantai' => ['Jumlah Lantai', 'floors'],
            'jumlah_kamar_tidur' => ['Kamar Tidur', 'bedrooms'],
            'jumlah_kamar_mandi' => ['Kamar Mandi', 'bathrooms'],
            'jumlah_powder_room' => ['Powder Room', null],
            'jumlah_maid_room' => ['Maid Room', null],
            'jumlah_mobil' => ['Kapasitas Mobil', null],
            'status_meeting' => ['Status Meeting', null],
        ];

        foreach ($this->legacy->table('desain_rumah')->orderBy('id')->get() as $i => $row) {
            $title = trim((string) ($row->judul ?? '')) ?: 'Desain Rumah '.$row->id;

            $portfolio = Portfolio::updateOrCreate(
                ['legacy_source' => 'desain_rumah', 'legacy_id' => $row->id],
                [
                    'title' => [config('app.fallback_locale') => $title],
                    'description' => [config('app.fallback_locale') => $row->keterangan ?? null],
                    'location' => $row->lokasi ?? null,
                    // Offset so these sort after the newer properties rows.
                    'sort_order' => 1000 + $i,
                    // Not auto-published: this module predates `properties` and
                    // its content has never been reviewed against the new site.
                    'is_published' => false,
                ],
            );

            $order = 0;
            foreach ($map as $column => [$label, $promoteTo]) {
                if (! $this->legacyHasColumn('desain_rumah', $column)) {
                    continue;
                }

                $value = trim((string) ($row->{$column} ?? ''));
                if ($value === '' || $value === '0') {
                    continue;
                }

                $this->upsertSpec($portfolio, $label, $value, $order++);

                if ($promoteTo !== null) {
                    $this->promoteSpecToColumn($portfolio, $label, $value);
                }
            }

            $portfolio->save();

            $this->migrateGaleriRumah($portfolio, $row->id);
            $this->bump('portfolios_from_desain_rumah');
        }

        $count = $this->counts['portfolios_from_desain_rumah'] ?? 0;
        if ($count > 0) {
            $this->warnings[] = "Imported {$count} portfolio(s) from desain_rumah, all left "
                .'UNPUBLISHED. This module predates `properties` and may duplicate rows that '
                .'already exist there -- review and publish or delete them in the admin panel. '
                .'Filter on legacy_source = desain_rumah to find them.';
        }

        $this->line('  portfolios (desain_rumah) .. '.$count);
    }

    private function migrateGaleriRumah(Portfolio $portfolio, int $legacyId): void
    {
        if (! $this->legacyHasTable('galeri_rumah')) {
            return;
        }

        $images = $this->legacy->table('galeri_rumah')
            ->where('id_rumah', $legacyId)->orderBy('id')->get();

        foreach ($images as $i => $img) {
            $file = trim((string) ($img->nama_file ?? ''));
            if ($file === '') {
                continue;
            }

            PortfolioImage::updateOrCreate(
                [
                    'portfolio_id' => $portfolio->id,
                    'image_path' => 'portfolio/'.ltrim($file, '/'),
                ],
                [
                    // legacy tipe_gambar was 'utama' (main) or 'galeri'
                    'is_cover' => ($img->tipe_gambar ?? '') === 'utama',
                    'sort_order' => $i,
                ],
            );
            $this->bump('images_local');
        }

        $this->ensureSingleCover($portfolio);
    }

    /**
     * NOTE: updateOrCreate() cannot be used with a translatable column as a
     * where-key. It also FILLS the attributes it matched on, so a JSON string
     * passed as a key would hit HasTranslations::setAttribute() as a plain
     * string and get wrapped a second time:
     *     {"id":"{\"id\":\"Gaya\"}"}
     *
     * So the lookup uses a JSON path comparison and the write goes through
     * setTranslations().
     */
    private function upsertSpec(Portfolio $portfolio, string $label, string $value, int $order): void
    {
        $locale = config('app.fallback_locale');

        $spec = PortfolioSpec::where('portfolio_id', $portfolio->id)
            ->where('label->'.$locale, $label)
            ->first();

        if (! $spec) {
            $spec = new PortfolioSpec(['portfolio_id' => $portfolio->id]);
            $spec->setTranslation('label', $locale, $label);
        }

        $spec->setTranslation('value', $locale, $value);
        $spec->sort_order = $order;
        $spec->save();

        $this->bump('portfolio_specs');
    }

    /**
     * Copies a handful of specs into real columns so the portfolio index can
     * filter and sort without a join. Kept intentionally small -- everything
     * else stays in portfolio_specs.
     */
    private function promoteSpecToColumn(Portfolio $portfolio, string $label, string $value): void
    {
        $normalized = Str::lower($label);
        $number = (int) preg_replace('/\D/', '', $value);

        match (true) {
            str_contains($normalized, 'style') || str_contains($normalized, 'gaya') => $portfolio->style = Str::limit($value, 100, ''),
            str_contains($normalized, 'lantai') || str_contains($normalized, 'floor') => $portfolio->floors = $number ?: null,
            str_contains($normalized, 'kamar tidur') || str_contains($normalized, 'bedroom') => $portfolio->bedrooms = $number ?: null,
            str_contains($normalized, 'kamar mandi') || str_contains($normalized, 'bathroom') => $portfolio->bathrooms = $number ?: null,
            str_contains($normalized, 'tanah') || str_contains($normalized, 'land') => $portfolio->land_size = $number ?: null,
            str_contains($normalized, 'bangunan') || str_contains($normalized, 'building') => $portfolio->building_size = $number ?: null,
            default => null,
        };
    }

    private function ensureSingleCover(Portfolio $portfolio): void
    {
        $images = $portfolio->images()->get();

        if ($images->isEmpty()) {
            return;
        }

        $covers = $images->where('is_cover', true);

        if ($covers->count() === 1) {
            return;
        }

        $keep = $covers->first() ?? $images->first();

        $portfolio->images()->whereKeyNot($keep->getKey())->update(['is_cover' => false]);
        $keep->update(['is_cover' => true]);
    }

    /**
     * Decision #3, decided here: legacy `likes` was incremented on every GET
     * page render in detail.php, so it is a bot-inflated page-view counter with
     * no user identity and no dedup -- it was never a like count.
     *
     * It is NOT carried into view_count, because importing inflated numbers
     * would give the new counter a false baseline. Instead the row that
     * currently ranks top is marked is_featured, so the homepage keeps showing
     * the same design on launch day. From then on it is an editorial choice.
     */
    private function seedFeaturedPortfolio(): void
    {
        if (Portfolio::featured()->exists()) {
            return; // idempotent: a human may already have chosen
        }

        $legacyId = null;

        if ($this->legacyHasTable('properties') && $this->legacyHasColumn('properties', 'likes')) {
            $top = $this->legacy->table('properties')
                ->orderByDesc('likes')->orderBy('id')->first();
            $legacyId = $top->id ?? null;
        }

        $portfolio = $legacyId
            ? Portfolio::where('legacy_source', 'properties')->where('legacy_id', $legacyId)->first()
            : null;

        $portfolio ??= Portfolio::published()->ordered()->first();

        if ($portfolio) {
            $portfolio->update(['is_featured' => true]);
            $this->warnings[] = "Marked portfolio '{$portfolio->slug}' as featured "
                .($legacyId ? '(it had the highest legacy `likes` count). ' : '(fallback: newest published). ')
                .'Legacy `likes` was a page-view counter, so it was not imported as a number -- '
                .'only used to pick this one row. Change the featured pick in the admin panel.';
        }
    }

    /* ===================================================================== */
    /* Job vacancies */
    /* ===================================================================== */

    /**
     * The production dump contains obvious test rows on the live careers page:
     * 'asddddddddddddddMARKETING EXECUTIVE' in department 'Marketingssssssssssss'.
     * Legacy had no draft state, so they shipped to visitors.
     *
     * Heuristic: 4+ identical consecutive characters marks a row as suspect.
     * Suspect rows are imported UNPUBLISHED, never deleted -- deciding what is
     * junk is the client's call, not the migrator's.
     */
    private function migrateJobVacancies(): void
    {
        if (! $this->legacyHasTable('job_detail')) {
            return;
        }

        foreach ($this->legacy->table('job_detail')->orderByDesc('id')->get() as $row) {
            $title = trim((string) ($row->title ?? '')) ?: 'Lowongan '.$row->id;
            $department = trim((string) ($row->departemen ?? ''));

            $suspect = $this->looksLikeTestData($title)
                || $this->looksLikeTestData($department);

            JobVacancy::updateOrCreate(
                ['slug' => Str::slug(Str::limit($title, 60, '')).'-'.$row->id],
                [
                    'title' => [config('app.fallback_locale') => $title],
                    'location' => trim((string) ($row->location ?? '')) ?: '-',
                    'department' => $department ?: '-',
                    'employment_type' => trim((string) ($row->type ?? '')) ?: '-',
                    'requirement' => [config('app.fallback_locale') => $row->requirement ?? null],
                    'contact_url' => $row->contact ?? null,
                    'is_published' => ! $suspect,
                ],
            );

            if ($suspect) {
                $this->warnings[] = "Job vacancy (legacy id {$row->id}, \""
                    .Str::limit($title, 40).'") looks like test data -- imported UNPUBLISHED. '
                    .'Review or delete it.';
            }

            $this->bump('job_vacancies');
        }

        $this->line('  job vacancies .. '.($this->counts['job_vacancies'] ?? 0));
    }

    private function looksLikeTestData(string $value): bool
    {
        return $value !== '' && preg_match('/(.)\1{3,}/', $value) === 1;
    }

    /* ===================================================================== */
    /* Users */
    /* ===================================================================== */

    /**
     * Legacy bcrypt hashes carry over unchanged -- Laravel's Hash::check()
     * verifies $2y$ hashes, so existing admins keep their passwords.
     *
     * BUT: the admin password was published in plaintext in Kredensial Akses.txt
     * inside the webroot, so every migrated account is deactivated. Reactivate
     * them from the admin panel only after the password has been changed.
     *
     * Roles are assigned explicitly and nobody is granted superadmin.
     */
    private function migrateUsers(): void
    {
        if (! $this->legacyHasTable('users')) {
            return;
        }

        foreach ($this->legacy->table('users')->orderBy('id')->get() as $row) {
            $email = trim((string) ($row->email ?? ''));
            $username = trim((string) ($row->username ?? ''));

            if ($email === '') {
                $this->warnings[] = "Legacy user id {$row->id} ('{$username}') has no email "
                    .'and was skipped -- the new users table requires one.';

                continue;
            }

            $user = User::withoutEvents(fn () => User::firstOrNew(['email' => $email]));

            $user->name = $username ?: $email;
            $user->username = $username ?: null;
            $user->role = User::ROLE_EDITOR;
            $user->is_active = false;

            if (! $user->exists) {
                // Assign the legacy hash directly. The 'hashed' cast would
                // re-hash an already-hashed value, so bypass the mutator.
                $user->setRawAttributes(
                    array_merge($user->getAttributes(), ['password' => $row->password]),
                    false
                );
            }

            $user->save();
            $this->bump('users');
        }

        $this->warnings[] = 'All migrated users were imported DEACTIVATED with role=editor. '
            .'The legacy admin password was published in plaintext in Kredensial Akses.txt in '
            .'the webroot, so treat every one of these accounts as compromised: change the '
            .'password, then reactivate. Create your own superadmin with '
            .'`php artisan app:create-admin`.';

        $this->line('  users .......... '.($this->counts['users'] ?? 0));
    }

    /* ===================================================================== */
    /* Redirect map */
    /* ===================================================================== */

    /**
     * The single highest-value output of this command. Without it the cutover
     * discards every search ranking the domain has earned.
     */
    private function buildRedirectMap(): void
    {
        $static = [
            'index.php' => '/',
            'index-en.php' => '/en',
            'karir.php' => '/#kontak',
            'login.php' => '/admin/login',
            'upload.php' => '/',   // removed: was an unauthenticated upload endpoint
            'reg.php' => '/',      // removed: was open self-registration
            'edit.php' => '/admin',
            'editkarir.php' => '/admin',
        ];

        foreach ($static as $old => $new) {
            LegacyRedirect::updateOrCreate(['old_path' => $old], ['new_path' => $new]);
            $this->bump('redirects');
        }

        // detail.php?id=N -> /portofolio/{slug}
        foreach (Portfolio::whereNotNull('legacy_id')->get() as $portfolio) {
            if ($portfolio->legacy_source !== 'properties') {
                continue; // only `properties` ids were ever public via detail.php
            }

            LegacyRedirect::updateOrCreate(
                ['old_path' => 'detail.php?id='.$portfolio->legacy_id],
                ['new_path' => '/portofolio/'.$portfolio->slug],
            );
            $this->bump('redirects');
        }

        $this->line('  redirects ...... '.($this->counts['redirects'] ?? 0));
    }

    /* ===================================================================== */
    /* Helpers */
    /* ===================================================================== */

    private function truncateDestination(): void
    {
        $this->warn('  --fresh: truncating destination tables');

        foreach ([
            'portfolio_specs', 'portfolio_images', 'portfolios',
            'service_features', 'services', 'value_propositions',
            'testimonials', 'design_reviews', 'hero_slides',
            'job_vacancies', 'legacy_redirects', 'site_settings',
        ] as $table) {
            DB::table($table)->delete();
        }

        Settings::flush();
    }

    private function legacyHasTable(string $table): bool
    {
        return Schema::connection($this->legacy->getName())->hasTable($table);
    }

    private function legacyHasColumn(string $table, string $column): bool
    {
        return $this->legacyHasTable($table)
            && Schema::connection($this->legacy->getName())->hasColumn($table, $column);
    }

    private function bump(string $key, int $by = 1): void
    {
        $this->counts[$key] = ($this->counts[$key] ?? 0) + $by;
    }

    private function printReport(): void
    {
        $this->newLine();
        $this->info('=== Migration report ===');

        $rows = [];
        foreach ($this->counts as $key => $count) {
            $rows[] = [$key, (string) $count];
        }
        $this->table(['migrated', 'count'], $rows);

        if ($this->warnings === []) {
            $this->info('No warnings.');

            return;
        }

        $this->newLine();
        $this->warn('Items needing your attention ('.count($this->warnings).'):');
        foreach ($this->warnings as $i => $warning) {
            $this->line('  '.($i + 1).'. '.$warning);
            $this->newLine();
        }

        $this->warn('Verify with: php artisan app:verify-migration');
    }
}
