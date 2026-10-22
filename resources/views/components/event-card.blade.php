@props(['event'])

<article class="card-light event-card">
    <a href="{{ $lroute('events.show', ['event' => $event->slug]) }}" class="event-card-cover">
        @if ($event->cover_url)
            <img src="{{ $event->cover_url }}" alt="{{ $event->title }}" loading="lazy">
        @else
            <div class="event-card-cover-fallback">
                <x-ui-icon name="calendar" :size="34" />
                <span>3D Expert Event</span>
            </div>
        @endif

        <div class="event-card-badges">
            <span class="badge {{ $event->isFree() ? 'badge-success' : 'badge-amber' }}">
                {{ $event->isFree() ? __('events.free') : __('events.paid') }}
            </span>

            @if ($event->location_type === 'online')
                <span class="badge badge-dark">{{ __('events.online') }}</span>
            @elseif ($event->location_type === 'hybrid')
                <span class="badge badge-dark">{{ __('events.hybrid') }}</span>
            @endif
        </div>
    </a>

    <div class="card-body event-card-body">
        <p class="event-card-date">
            <x-ui-icon name="calendar" :size="15" />
            <span>{{ $event->starts_at->translatedFormat('l, j F Y') }} &middot; {{ $event->starts_at->format('H:i') }} WIB</span>
        </p>

        <h3 class="event-card-title">
            <a href="{{ $lroute('events.show', ['event' => $event->slug]) }}">
                {{ $event->title }}
            </a>
        </h3>

        @if ($event->venue)
            <p class="event-card-meta">
                <x-ui-icon name="map-pin" :size="15" />
                <span>{{ $event->venue }}</span>
            </p>
        @elseif ($event->location_type === 'online')
            <p class="event-card-meta">
                <x-ui-icon name="globe" :size="15" />
                <span>{{ __('events.online_streaming') }}</span>
            </p>
        @endif

        @if ($event->description)
            <p class="event-card-desc">
                {{ Str::limit(strip_tags((string) $event->description), 110) }}
            </p>
        @endif
    </div>

    <div class="event-card-footer">
        <span class="event-card-seats">
            @if ($event->isUnlimited())
                {{ __('events.seats_unlimited') }}
            @else
                {{ trans_choice('events.seats_left', $event->seatsLeft() ?? 0, ['count' => $event->seatsLeft() ?? 0]) }}
            @endif
        </span>

        <a href="{{ $lroute('events.show', ['event' => $event->slug]) }}" class="event-card-link">
            {{ __('events.view_detail') }}
        </a>
    </div>
</article>
