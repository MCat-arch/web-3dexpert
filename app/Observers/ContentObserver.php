<?php

namespace App\Observers;

use App\Support\Settings;
use Illuminate\Support\Facades\Cache;

/**
 * Flushes the rendered-content caches whenever content changes, so an editor
 * sees their edit immediately instead of waiting out a TTL.
 *
 * Legacy had no caching at all -- every page view re-queried every content
 * section -- so there was nothing to invalidate. Adding a cache without
 * invalidation would have been a downgrade for editors.
 */
class ContentObserver
{
    public function saved(): void
    {
        $this->flush();
    }

    public function deleted(): void
    {
        $this->flush();
    }

    private function flush(): void
    {
        foreach (array_keys(config('site.locales')) as $locale) {
            Cache::forget('home.'.$locale);
        }

        Settings::flush();
    }
}
