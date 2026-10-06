<?php

namespace App\Providers;

use App\Models\DesignReview;
use App\Models\HeroSlide;
use App\Models\Portfolio;
use App\Models\PortfolioImage;
use App\Models\PortfolioSpec;
use App\Models\Service;
use App\Models\ServiceFeature;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use App\Models\ValueProposition;
use App\Observers\ContentObserver;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /*
         * Catches a whole class of bug that legacy could not have caught:
         * accessing an attribute or relation that was never loaded throws in
         * non-production instead of silently returning null (which is how a
         * page ends up rendering blank sections).
         */
        Model::preventLazyLoading(! app()->isProduction());
        Model::preventAccessingMissingAttributes(! app()->isProduction());

        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        Paginator::useTailwind();

        foreach ([
            HeroSlide::class, Service::class, ServiceFeature::class,
            ValueProposition::class, Testimonial::class, DesignReview::class,
            Portfolio::class, PortfolioImage::class, PortfolioSpec::class,
            SiteSetting::class,
        ] as $model) {
            $model::observe(ContentObserver::class);
        }

        // Every view gets the company details and locale list, so the layout
        // does not have to be passed them from each controller.
        View::composer('*', function ($view) {
            $view->with([
                'settings' => fn (string $key, mixed $default = null) => Settings::get($key, $default),
                'availableLocales' => config('site.locales'),
                'currentLocale' => app()->getLocale(),
            ]);
        });
    }
}
