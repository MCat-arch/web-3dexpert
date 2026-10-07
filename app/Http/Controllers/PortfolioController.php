<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use Illuminate\View\View;

/**
 * Replaces the browsable part of detail.php (1,143 lines).
 *
 * These routes were dropped during the initial rebuild, which left four
 * route(...) calls in the Blade views with nothing to resolve to -- every page
 * returned a 500 because partials/header.blade.php is in the layout. Restoring
 * them also lets the legacy detail.php?id=N redirects target a real slug
 * instead of collapsing every project onto the /#portofolio anchor.
 */
class PortfolioController extends Controller
{
    public const PER_PAGE = 24;

    public function index(): View
    {
        return view('portfolio.index', [
            // x-portfolio-card falls back to images->first() when a portfolio
            // has no cover, and preventLazyLoading() is active outside
            // production, so both relations must be loaded or the card throws.
            // O4 revisits this -- loading every image to pick one is wasteful.
            'portfolios' => Portfolio::published()
                ->ordered()
                ->with(['coverImage', 'images'])
                ->paginate(self::PER_PAGE),
        ]);
    }

    public function show(Portfolio $portfolio): View
    {
        abort_unless($portfolio->is_published, 404);

        // The card fallback reads $portfolio->images when there is no cover,
        // and preventLazyLoading() is active outside production, so both
        // relations have to be loaded here.
        $portfolio->load(['images', 'coverImage', 'specs']);

        $portfolio->recordView();

        return view('portfolio.show', [
            'portfolio' => $portfolio,

            // The view renders a "Desain Lainnya" grid of six. Same eager-load
            // caveat as index(): x-portfolio-card reads images when there is
            // no cover.
            'related' => Portfolio::published()
                ->whereKeyNot($portfolio->getKey())
                ->ordered()
                ->with(['coverImage', 'images'])
                ->limit(6)
                ->get(),
        ]);
    }
}
