<?php

namespace App\Providers;

use App\Listeners\LogStaffAuthentication;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseSection;
use App\Models\DesignReview;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Faq;
use App\Models\HeroSlide;
use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Models\MediaAsset;
use App\Models\Portfolio;
use App\Models\PortfolioImage;
use App\Models\PortfolioSpec;
use App\Models\Service;
use App\Models\ServiceFeature;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\ValueProposition;
use App\Observers\AdminAuditObserver;
use App\Observers\ContentObserver;
use App\Observers\CourseContentObserver;
use App\Policies\AssignmentSubmissionPolicy;
use App\Policies\CourseLessonPolicy;
use App\Policies\EventRegistrationPolicy;
use App\Policies\JobApplicationPolicy;
use App\Support\Locale;
use App\Support\Settings;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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

        if (app()->isProduction() && (bool) env('FORCE_HTTPS', false)) {
            URL::forceScheme('https');
        }

        Paginator::useTailwind();

        /*
         * Admin content is Indonesian-only. The public /en layer still uses the
         * existing server-side locale routes and legacy `en` values, falling
         * back to Indonesian when no English value exists, so the old per-field
         * English inputs are no longer part of the workflow. Keep the legacy
         * `en` JSON values for existing rows, but do not render or dehydrate
         * these fields -- otherwise a required `title.en` would block an
         * Indonesian-only save with no visible field to fill in.
         */
        $hideLegacyEnglishField = function (TextInput|Textarea $component): void {
            if (str_ends_with((string) $component->getName(), '.en')) {
                $component
                    ->hidden()
                    ->dehydrated(false);
            }
        };

        TextInput::configureUsing($hideLegacyEnglishField);
        Textarea::configureUsing($hideLegacyEnglishField);

        $this->registerRateLimiters();

        // Customers are authorised by ownership, never by role (D1).
        Gate::policy(EventRegistration::class, EventRegistrationPolicy::class);
        Gate::policy(JobApplication::class, JobApplicationPolicy::class);
        Gate::policy(AssignmentSubmission::class, AssignmentSubmissionPolicy::class);
        Gate::policy(CourseLesson::class, CourseLessonPolicy::class);
        Course::observe(CourseContentObserver::class);
        CourseSection::observe(CourseContentObserver::class);
        CourseLesson::observe(CourseContentObserver::class);

        /*
         * Audit hooks. Registered on every model whose state is reviewed in
         * the audit log; business actions use AuditLogger explicitly.
         */
        foreach ([
            Course::class,
            Event::class,
            JobVacancy::class,
            SiteSetting::class,
            User::class,
            MediaAsset::class,
            Assignment::class,
        ] as $auditedModel) {
            $auditedModel::observe(AdminAuditObserver::class);
        }

        \Illuminate\Support\Facades\Event::listen(
            Login::class,
            [LogStaffAuthentication::class, 'handleLogin'],
        );
        \Illuminate\Support\Facades\Event::listen(
            Logout::class,
            [LogStaffAuthentication::class, 'handleLogout'],
        );

        /*
         * Every model the homepage payload reads must be here, or editing it
         * will not invalidate the cache once O5 adds the write.
         *
         * Faq and MediaAsset were missing. Faq is read directly by
         * HomeController::payload(), so an editor changing an FAQ would have
         * seen a stale page. Harmless today only because nothing is cached yet
         * -- which is exactly why it is fixed alongside the cache wiring
         * rather than after it.
         *
         * MediaAsset is here for payload correctness, but note it does NOT fix
         * stale images: replacing an asset's content leaves its path and
         * therefore its URL unchanged, and MediaController sends
         * Cache-Control: max-age=2592000, so browsers hold the old file for 30
         * days regardless of any server-side invalidation. That needs
         * cache-busting off the existing sha256 column -- tracked in O3.
         */
        foreach ([
            HeroSlide::class, Service::class, ServiceFeature::class,
            ValueProposition::class, Testimonial::class, DesignReview::class,
            Portfolio::class, PortfolioImage::class, PortfolioSpec::class,
            SiteSetting::class, Faq::class, MediaAsset::class,
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

                // Public routes are registered once per locale with namespaced
                // names ('home', 'en.home'), so a bare route('home') in a view
                // would send English visitors to Indonesian URLs. Use
                // $lroute() for anything inside those groups.
                'lroute' => fn (string $name, array $parameters = []) => Locale::route($name, $parameters),

                /*
                 * Authorisation for the CUSTOMER guard in Blade.
                 *
                 * Do not use @can on a public page. Blade's @can resolves the
                 * DEFAULT guard, and that is only `customer` on routes behind
                 * auth:customer -- because Laravel's Authenticate middleware
                 * calls shouldUse() when it succeeds. On a public route the
                 * default stays `web`, so @can sees no user and every ability
                 * silently returns false.
                 *
                 * That is not hypothetical: it shipped. The cancel button on
                 * the public event detail page never rendered for the owner,
                 * and the test that should have caught it passed because
                 * actingAs($c, 'customer') ALSO calls shouldUse() -- masking
                 * the difference between the test and a real browser.
                 */
                'customerCan' => fn (string $ability, mixed $model) => Gate::forUser(auth('customer')->user())
                    ->allows($ability, $model),
            ]);
        });
    }

    /**
     * Rate limiters for the public auth surface.
     *
     * The important one is 'customer-register', and it is deliberately NOT
     * keyed on IP alone (decision D17).
     *
     * Indonesian mobile carriers use CGNAT heavily, so hundreds of unrelated
     * subscribers share one egress address -- and a launch event on venue WiFi
     * puts everyone behind a single NAT. A tight per-IP limit would 429 most of
     * a 500-person launch, and the logs would show the application working
     * exactly as configured. That failure is indistinguishable from a
     * successful launch going wrong, which is why it gets a regression test.
     *
     * Abuse control for registration comes from the unique email constraint
     * plus the verification requirement, not from an IP counter. The IP limit
     * that remains is a generous ceiling against scripted floods only.
     *
     * Login is different: keyed on email AND IP, so one attacker cannot lock a
     * real user out by hammering their address from elsewhere.
     */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('customer-register', fn (Request $request) => [
            // Per address: stops one person scripting many accounts.
            Limit::perMinute(3)->by('reg-email:'.Str::lower((string) $request->input('email'))),
            // Per IP: high on purpose. Shared NAT is normal, not suspicious.
            Limit::perMinute(100)->by('reg-ip:'.$request->ip()),
        ]);

        RateLimiter::for('customer-login', fn (Request $request) => [
            Limit::perMinute(5)->by(
                'login:'.Str::lower((string) $request->input('email')).'|'.$request->ip()
            ),
            Limit::perMinute(30)->by('login-ip:'.$request->ip()),
        ]);

        RateLimiter::for('customer-password-reset', fn (Request $request) => [
            Limit::perMinute(3)->by('pwd:'.Str::lower((string) $request->input('email'))),
            Limit::perMinute(20)->by('pwd-ip:'.$request->ip()),
        ]);

        RateLimiter::for('customer-verification-resend', fn (Request $request) => [
            Limit::perMinute(3)->by('verify:'.($request->user('customer')?->getKey() ?? $request->ip())),
        ]);

        /*
         * Job applications. Keyed on the customer, not the IP.
         *
         * Each submission writes PDFs to disk, so this is the most expensive
         * public POST in the application -- but the limit is per account
         * because a shared office or campus network is a realistic place for
         * several people to apply from, and the D17 reasoning applies here
         * too. The unique index already prevents duplicate applications; this
         * only stops one account hammering the upload path.
         */
        RateLimiter::for('customer-apply', fn (Request $request) => [
            Limit::perMinute(5)->by('apply:'.($request->user('customer')?->getKey() ?? $request->ip())),
            Limit::perHour(20)->by('apply-hour:'.($request->user('customer')?->getKey() ?? $request->ip())),
        ]);

        /*
         * Progress tracker (P7). The most exposed surface on the site:
         * unauthenticated, and it reads customer data.
         *
         * TWO limits, and the per-CODE one is the one that matters. A per-IP
         * limit alone would let a distributed attempt grind through
         * verification values against one known project code -- which is the
         * only attack the second factor is actually defending against. Keyed
         * on the code, that becomes 5 attempts per hour no matter how many
         * addresses are used.
         *
         * The per-IP limit is the cheap backstop against someone enumerating
         * codes, and it is kept loose for the same CGNAT reason as D17.
         */
        RateLimiter::for('project-lookup', fn (Request $request) => [
            Limit::perHour(5)->by(
                'track-code:'.Str::upper(preg_replace('/\s+/', '', (string) $request->input('project_code')))
            ),
            Limit::perMinute(10)->by('track-ip:'.$request->ip()),
        ]);
    }
}
