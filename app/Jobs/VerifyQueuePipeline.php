<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Proves the queue pipeline end to end: dispatch, persist, pick up, execute.
 *
 * This is the project's first queued class. Until now app/Jobs did not exist
 * and nothing implemented ShouldQueue, so there was no evidence a worker could
 * run anything at all -- and P1 onwards depends on queued mail for email
 * verification, receipts and status notifications.
 *
 * Kept rather than deleted after verification: it is three lines, it needs no
 * data, and `queue:work --once` against it is the fastest way to tell whether a
 * worker is alive on a new environment. Run it with:
 *
 *     php artisan tinker --execute 'App\Jobs\VerifyQueuePipeline::dispatch();'
 *     php artisan queue:work --once
 */
class VerifyQueuePipeline implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(): void
    {
        Log::info('Queue pipeline verified.', [
            'connection' => config('queue.default'),
            'at' => now()->toIso8601String(),
        ]);
    }
}
