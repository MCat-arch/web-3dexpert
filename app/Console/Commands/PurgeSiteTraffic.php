<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeSiteTraffic extends Command
{
    protected $signature = 'traffic:purge {--days= : Override the configured retention window}';

    protected $description = 'Delete site traffic rows older than the retention window';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('traffic.retention_days', 60));

        if ($days < 1) {
            $this->error('Retention must be at least one day.');

            return self::FAILURE;
        }

        $deleted = DB::table('site_traffic')
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Deleted {$deleted} traffic rows older than {$days} days.");

        return self::SUCCESS;
    }
}
