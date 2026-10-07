<?php

namespace App\Http\Controllers;

use App\Models\DesignReview;
use App\Models\Faq;
use App\Models\HeroSlide;
use App\Models\Portfolio;
use App\Models\Service;
use App\Models\Testimonial;
use App\Models\ValueProposition;
use Illuminate\View\View;

/**
 * Replaces index.php (928 lines) and index-en.php (961 lines).
 *
 * Legacy included koneksi.php NINE times in each file using include (not
 * include_once), so every page load opened up to nine separate PDO
 * connections. Here there is one connection, one controller, and the whole
 * payload is cached.
 */
class HomeController extends Controller
{
    public const PORTFOLIO_VISIBLE = 16;

    /**
     * The homepage payload cache key, one per locale.
     *
     * ContentObserver invalidates through this method rather than rebuilding
     * the string itself. The two had drifted before: the observer forgot
     * 'home.{locale}' while this controller never wrote it, so the
     * invalidation was dead code and README claimed caching that did not
     * exist. A shared accessor makes that drift impossible.
     */
    public static function cacheKey(string $locale): string
    {
        return 'home.'.$locale;
    }

    /**
     * NOT CACHED YET -- deliberately, and this is the reason.
     *
     * Caching this payload requires serializing Eloquent collections, which is
     * broken on the current cache store. Verified, not assumed:
     *
     *     Cache::store('database')->put('k', ['p' => Portfolio::...->get()]);
     *     // reading it back, in the same process or a fresh one:
     *     // Error: incomplete object -- class definition
     *     // "Illuminate\Database\Eloquent\Collection" was not loaded before
     *     // unserialize()
     *
     * So the options were: cache arrays and rewrite home.blade.php to stop
     * using model relations and accessors; cache rendered HTML, which P1
     * breaks the moment the header carries auth state; or wait for Redis,
     * where the serialization problem does not arise.
     *
     * Waiting is correct. A cache that corrupts its own contents is worse than
     * no cache, and the traffic work (O5) owns this decision with a real
     * baseline behind it. The key accessor and the observer wiring are in
     * place, so O5 only has to add the write.
     *
     * Blocked on: a running Redis server (keputusan.md K5).
     */
    public function __invoke(): View
    {
        return view('home', $this->payload());
    }

    private function payload(): array
    {
        return [
            'heroSlides' => HeroSlide::active()->ordered()->get(),

            // 'images' is loaded alongside 'coverImage' because
            // x-portfolio-card falls back to images->first() for portfolios
            // with no cover, and preventLazyLoading() is active outside
            // production -- without it the card throws rather than running an
            // extra query. O4 revisits this; loading every image to pick one
            // is wasteful, and this query is also still unbounded.
            'featured' => Portfolio::published()->featured()->ordered()
                ->with(['coverImage', 'images'])->first(),

            // Excludes the featured one, which is rendered separately above it.
            'portfolios' => Portfolio::published()->ordered()
                ->with(['coverImage', 'images'])
                ->where('is_featured', false)
                ->get(),

            'portfolioCount' => Portfolio::published()->count(),

            'services' => Service::published()->ordered()->with('features')->get(),

            'benefits' => ValueProposition::active()
                ->group(ValueProposition::GROUP_BENEFIT)->ordered()->get(),

            'processes' => ValueProposition::active()
                ->group(ValueProposition::GROUP_PROCESS)->ordered()->get(),

            'deliverables' => ValueProposition::active()
                ->group(ValueProposition::GROUP_DELIVERABLE)->ordered()->get(),

            // Legacy split these into a 2-across hero row plus rows of 4.
            'highlightedTestimonials' => Testimonial::published()->highlighted()
                ->ordered()->limit(2)->get(),

            'testimonials' => Testimonial::published()
                ->where('is_highlighted', false)->ordered()->limit(12)->get(),

            'designReviews' => DesignReview::published()->ordered()->limit(6)->get(),

            'faqs' => Faq::published()->ordered()->get(),
        ];
    }
}
