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

    public function __invoke(): View
    {
        // Keep Eloquent collections out of the cache. Serializing them into
        // the database cache can restore them as incomplete objects when the
        // model class is not loaded before unserialization.
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
