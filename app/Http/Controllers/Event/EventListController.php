<?php

namespace App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\View\View;

class EventListController extends Controller
{
    public const PER_PAGE = 12;

    /**
     * The public event list is intentionally NOT cached.
     *
     * A five-minute snapshot meant an editor could publish an event and still
     * see the old list on the public site until the TTL expired. Event volume
     * is small, and the list already carries live seat availability, so the
     * simplest correct behaviour is one paginated read (count + page queries)
     * per request. If this ever needs a cache again, use a versioned key that
     * is bumped from an event observer and assert the publish -> visible
     * contract in tests.
     */
    public function __invoke(): View
    {
        $events = Event::published()
            ->publiclyListed()
            ->ordered()
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('events.index', [
            'events' => $events,
        ]);
    }
}
