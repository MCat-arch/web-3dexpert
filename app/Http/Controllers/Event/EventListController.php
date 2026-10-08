<?php

namespace App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class EventListController extends Controller
{
    public const PER_PAGE = 12;

    public function __invoke(): View
    {
        $cachedRows = Cache::remember(
            Event::listingCacheKey(app()->getLocale()),
            now()->addMinutes(5),
            fn (): array => Event::published()
                ->upcoming()
                ->ordered()
                ->get([
                    'id',
                    'title',
                    'slug',
                    'description',
                    'cover_path',
                    'starts_at',
                    'ends_at',
                    'location_type',
                    'venue',
                    'online_url',
                    'price',
                    'registration_opens_at',
                    'registration_closes_at',
                    'is_published',
                ])
                ->map(fn (Event $event): array => $event->getAttributes())
                ->all(),
        );

        // Time-based transitions do not fire model events, so keep the list
        // correct when an event starts while its cached snapshot is alive.
        $upcomingRows = collect($cachedRows)
            ->filter(fn (array $row): bool => ! Carbon::parse($row['starts_at'])->isPast())
            ->values();
        $page = LengthAwarePaginator::resolveCurrentPage();
        $pageRows = $upcomingRows->forPage($page, self::PER_PAGE)->values();
        $eventIds = $pageRows->pluck('id')->all();

        // Cached rows deliberately exclude capacity and seats_taken. These
        // change on every enrollment, so load them live in one query per page.
        $availability = Event::query()
            ->whereKey($eventIds)
            ->get(['id', 'capacity', 'seats_taken'])
            ->keyBy(fn (Event $event): int => (int) $event->getKey());

        $events = Event::hydrate(
            $pageRows
                ->map(function (array $row) use ($availability): ?array {
                    $live = $availability->get((int) $row['id']);

                    if (! $live) {
                        return null;
                    }

                    return $row + [
                        'capacity' => $live->capacity,
                        'seats_taken' => $live->seats_taken,
                    ];
                })
                ->filter()
                ->values()
                ->all(),
        );

        $paginator = new LengthAwarePaginator(
            $events,
            $upcomingRows->count(),
            self::PER_PAGE,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => request()->query(),
            ],
        );

        return view('events.index', [
            'events' => $paginator,
        ]);
    }
}
