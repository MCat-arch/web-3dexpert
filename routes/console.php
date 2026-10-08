<?php

use App\Jobs\ReconcileEventSeatCounts;
use Illuminate\Support\Facades\Schedule;

// Keeps the rendered-content caches warm after an editor saves. ContentObserver
// already flushes on write, so this is only a safety net against a cache that
// was cleared out of band.
Schedule::command('cache:prune-stale-tags')->hourly();
Schedule::job(new ReconcileEventSeatCounts)->daily();
