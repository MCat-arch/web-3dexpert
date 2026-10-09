<?php

namespace App\Observers;

use App\Models\Course;
use Illuminate\Support\Facades\Cache;

class CourseContentObserver
{
    public function saved(): void
    {
        $this->flush();
    }

    public function deleted(): void
    {
        $this->flush();
    }

    public function restored(): void
    {
        $this->flush();
    }

    private function flush(): void
    {
        foreach (array_keys(config('site.locales', ['id' => 'Bahasa Indonesia', 'en' => 'English'])) as $locale) {
            Cache::forget(Course::CACHE_CATALOG_PREFIX.$locale);
        }
    }
}
