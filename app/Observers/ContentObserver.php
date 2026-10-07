<?php

namespace App\Observers;

use App\Http\Controllers\HomeController;
use App\Support\Settings;
use Illuminate\Support\Facades\Cache;

/**
 * Flushes the rendered-content caches whenever content changes, so an editor
 * sees their edit immediately instead of waiting out a TTL.
 *
 * Legacy had no caching at all -- every page view re-queried every content
 * section -- so there was nothing to invalidate. Adding a cache without
 * invalidation would have been a downgrade for editors.
 *
 * The homepage payload is not written yet; see HomeController::__invoke for
 * why, and keputusan.md K5. Forgetting a key that is not yet written is a
 * no-op, and it means O5 adds the write without having to remember to come
 * back here. The key comes from HomeController::cacheKey() so the two cannot
 * drift -- they did before, which is how the invalidation ended up pointing at
 * a key nothing produced.
 *
 * Which models are registered matters as much as the flushing: see
 * AppServiceProvider. Anything the homepage payload reads has to be here, or
 * editing it will not invalidate once O5 lands.
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
            Cache::forget(HomeController::cacheKey($locale));
        }

        Settings::flush();
    }
}
