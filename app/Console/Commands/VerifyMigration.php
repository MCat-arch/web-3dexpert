<?php

namespace App\Console\Commands;

use App\Models\DesignReview;
use App\Models\JobVacancy;
use App\Models\Portfolio;
use App\Models\Service;
use App\Models\Testimonial;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Task 3.15 from the implementation plan: reconcile old and new row counts, and
 * list everything the migrator flagged, so "did the migration work?" has an
 * answer that is not "the homepage looks fine".
 *
 * Exit code is non-zero when a blocking problem is found, so it can gate a
 * deploy.
 */
class VerifyMigration extends Command
{
    protected $signature = 'app:verify-migration {--connection=}';

    protected $description = 'Reconcile migrated data against the legacy database';

    private array $blocking = [];

    private array $advisory = [];

    public function handle(): int
    {
        $connectionName = $this->option('connection') ?: config('site.legacy_connection');

        $this->compareCounts($connectionName);
        $this->checkIntegrity();
        $this->checkLaunchReadiness();

        $this->newLine();

        foreach ($this->advisory as $note) {
            $this->warn('  [check] '.$note);
        }

        foreach ($this->blocking as $note) {
            $this->error('  [BLOCK] '.$note);
        }

        if ($this->blocking !== []) {
            $this->newLine();
            $this->error(count($this->blocking).' blocking issue(s). Do not launch yet.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('No blocking issues.'.($this->advisory === [] ? '' : ' Review the checks above.'));

        return self::SUCCESS;
    }

    private function compareCounts(string $connectionName): void
    {
        try {
            $legacy = DB::connection($connectionName);
            $legacy->getPdo();
        } catch (\Throwable) {
            $this->warn("Legacy connection [{$connectionName}] unavailable -- skipping count reconciliation.");

            return;
        }

        $pairs = [
            'layanan_section' => ['services', Service::query()],
            'testimonial_section' => ['testimonials', Testimonial::query()],
            'review_design' => ['design_reviews', DesignReview::query()],
            'job_detail' => ['job_vacancies', JobVacancy::withTrashed()],
        ];

        $rows = [];

        foreach ($pairs as $legacyTable => [$newTable, $query]) {
            if (! Schema::connection($connectionName)->hasTable($legacyTable)) {
                $rows[] = [$legacyTable, 'absent', $newTable, (string) $query->count(), '-'];

                continue;
            }

            $old = $legacy->table($legacyTable)->count();
            $new = $query->count();
            $status = $old === $new ? 'ok' : 'DIFF';

            if ($status === 'DIFF') {
                $this->advisory[] = "{$legacyTable} has {$old} row(s) but {$newTable} has {$new}. "
                    .'Explain the difference before launch (blank rows and orphans are skipped '
                    .'on purpose).';
            }

            $rows[] = [$legacyTable, (string) $old, $newTable, (string) $new, $status];
        }

        // Portfolios come from two legacy tables, so reconcile separately.
        $oldPortfolios = 0;
        foreach (['properties', 'desain_rumah'] as $table) {
            if (Schema::connection($connectionName)->hasTable($table)) {
                $oldPortfolios += $legacy->table($table)->count();
            }
        }
        $newPortfolios = Portfolio::withTrashed()->count();
        $rows[] = [
            'properties + desain_rumah', (string) $oldPortfolios,
            'portfolios', (string) $newPortfolios,
            $oldPortfolios === $newPortfolios ? 'ok' : 'DIFF',
        ];

        if ($oldPortfolios !== $newPortfolios) {
            $this->advisory[] = "Portfolio count differs: {$oldPortfolios} legacy row(s) across "
                ."both modules vs {$newPortfolios} migrated.";
        }

        $this->table(['legacy table', 'legacy rows', 'new table', 'new rows', ''], $rows);
    }

    private function checkIntegrity(): void
    {
        // A published portfolio with no cover renders an empty card.
        $noCover = Portfolio::published()
            ->whereDoesntHave('images', fn ($q) => $q->where('is_cover', true))
            ->count();

        if ($noCover > 0) {
            $this->blocking[] = "{$noCover} published portfolio(s) have no cover image.";
        }

        // More than one cover means the grid picks arbitrarily.
        $multiCover = Portfolio::whereHas('images', fn ($q) => $q->where('is_cover', true), '>', 1)->count();
        if ($multiCover > 0) {
            $this->blocking[] = "{$multiCover} portfolio(s) have more than one cover image.";
        }

        // Slugs are the new URLs -- a duplicate or empty slug breaks routing.
        $badSlugs = Portfolio::withTrashed()
            ->where(fn ($q) => $q->whereNull('slug')->orWhere('slug', ''))
            ->count();
        if ($badSlugs > 0) {
            $this->blocking[] = "{$badSlugs} portfolio(s) have an empty slug.";
        }

        // Anything the migrator could not parse.
        $needsReview = Service::where('needs_review', true)->count();
        if ($needsReview > 0) {
            $this->blocking[] = "{$needsReview} service(s) are flagged needs_review (duplicate "
                .'titles or unparseable prices). Fix and publish them in the admin panel.';
        }

        // Video embeds that will render an empty iframe.
        foreach ([Testimonial::class => 'testimonial', DesignReview::class => 'design review'] as $model => $label) {
            $broken = $model::query()->where('is_published', true)->whereNull('youtube_id')->count();
            if ($broken > 0) {
                $this->blocking[] = "{$broken} published {$label}(s) have no resolvable YouTube id.";
            }
        }

        // The rule edit.php enforced.
        $highlighted = Testimonial::published()->highlighted()->count();
        if ($highlighted < Testimonial::MIN_HIGHLIGHTED) {
            $this->blocking[] = "Only {$highlighted} highlighted testimonial(s); the homepage "
                .'hero row needs '.Testimonial::MIN_HIGHLIGHTED.'.';
        }
    }

    private function checkLaunchReadiness(): void
    {
        foreach (['company_whatsapp', 'company_email', 'home_heading'] as $key) {
            if (blank(Settings::get($key))) {
                $this->advisory[] = "Setting '{$key}' is empty (it was hardcoded in the legacy "
                    .'footer, not stored in the database).';
            }
        }

        if (Portfolio::featured()->count() === 0) {
            $this->blocking[] = 'No portfolio is marked is_featured, so the homepage hero has '
                .'nothing to show.';
        }

        if (Portfolio::featured()->count() > 1) {
            $this->advisory[] = 'More than one portfolio is marked is_featured; the homepage '
                .'shows the first by sort order.';
        }

        $unpublishedFromLegacy = Portfolio::where('legacy_source', 'desain_rumah')
            ->where('is_published', false)->count();
        if ($unpublishedFromLegacy > 0) {
            $this->advisory[] = "{$unpublishedFromLegacy} portfolio(s) imported from desain_rumah "
                .'are still unpublished. Review them for duplicates against the properties '
                .'module, then publish or delete.';
        }

        if (User::where('role', User::ROLE_SUPERADMIN)
            ->where('is_active', true)->doesntExist()) {
            $this->blocking[] = 'No active superadmin exists. Run `php artisan app:create-admin`.';
        }

        $activeLegacy = User::where('is_active', true)
            ->where('role', '!=', User::ROLE_SUPERADMIN)->count();
        if ($activeLegacy > 0) {
            $this->advisory[] = "{$activeLegacy} migrated account(s) are active. The legacy admin "
                .'password was public, so confirm each one has had its password changed.';
        }
    }
}
