@props(['event'])

<article class="card-light">
    <div class="card-body">
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 12px;">
            @if ($event->isFree())
                <span class="badge badge-success">{{ __('events.free') }}</span>
            @else
                <span class="badge badge-amber">{{ __('events.paid') }}</span>
            @endif

            @if ($event->location_type === 'online')
                <span class="badge badge-dark" style="background: #e5e5e5; color: var(--text);">{{ __('events.online') }}</span>
            @elseif ($event->location_type === 'hybrid')
                <span class="badge badge-dark" style="background: #e5e5e5; color: var(--text);">{{ __('events.hybrid') }}</span>
            @endif
        </div>

        <h3 style="font-size: 1.15rem; font-weight: 600; color: var(--text); margin-bottom: 8px;">
            <a href="{{ $lroute('events.show', ['event' => $event->slug]) }}" style="color: inherit; text-decoration: none;">
                {{ $event->title }}
            </a>
        </h3>

        <p style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 4px;">
            {{ $event->starts_at->translatedFormat('l, j F Y') }} &middot; {{ $event->starts_at->format('H:i') }}
        </p>

        @if ($event->venue)
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0;">{{ $event->venue }}</p>
        @endif
    </div>

    <div style="border-top: 1px solid var(--line-light); padding: 12px 20px; font-size: 0.85rem; background: #fafafa; display: flex; align-items: center; justify-content: space-between;">
        @if (! $event->registrationIsOpen())
            <span style="color: var(--text-muted);">{{ __('events.closed') }}</span>
        @elseif ($event->isUnlimited())
            <span style="color: var(--text-muted);">{{ __('events.seats_unlimited') }}</span>
        @else
            <span style="color: var(--text-muted);">
                {{ __('events.seats_left', ['count' => $event->seatsLeft() ?? $event->capacity]) }}
            </span>
        @endif

        <a href="{{ $lroute('events.show', ['event' => $event->slug]) }}" style="color: var(--gold-hover); font-weight: 600; text-decoration: none;">
            {{ __('events.details') ?? 'Detail &rarr;' }}
        </a>
    </div>
</article>
