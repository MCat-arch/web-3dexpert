<?php

namespace App\Observers;

use App\Models\Event;
use Illuminate\Support\Facades\Cache;

class EventObserver
{
    public function saved(Event $event): void
    {
        $this->invalidateListing();
    }

    public function deleted(Event $event): void
    {
        $this->invalidateListing();
    }

    public function restored(Event $event): void
    {
        $this->invalidateListing();
    }

    private function invalidateListing(): void
    {
        foreach (array_keys(config('site.locales')) as $locale) {
            Cache::forget(Event::listingCacheKey($locale));
        }
    }
}
