@props(['event'])

<article class="card-light event-card">
    <a href="{{ $lroute('events.show', ['event' => $event->slug]) }}" class="event-card-cover" style="display: block; text-decoration: none;">
        @if ($event->cover_url)
            <img src="{{ $event->cover_url }}" alt="{{ $event->title }}" loading="lazy">
        @else
            <div style="display: flex; flex-direction: column; height: 100%; width: 100%; align-items: center; justify-content: center; background: linear-gradient(135deg, #111827 0%, #1f2937 100%); color: var(--gold); padding: 20px; text-align: center;">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 8px; opacity: 0.85;">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <span style="font-size: 0.75rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #d1d5db;">3D Expert Event</span>
            </div>
        @endif

        {{-- Badges overlay on top-left of image --}}
        <div style="position: absolute; top: 12px; left: 12px; display: flex; flex-wrap: wrap; gap: 6px; z-index: 2;">
            @if ($event->isFree())
                <span class="badge badge-success" style="box-shadow: 0 2px 6px rgba(0,0,0,0.25);">{{ __('events.free') }}</span>
            @else
                <span class="badge badge-amber" style="box-shadow: 0 2px 6px rgba(0,0,0,0.25);">{{ __('events.paid') }}</span>
            @endif

            @if ($event->location_type === 'online')
                <span class="badge badge-dark" style="background: rgba(17, 24, 39, 0.88); backdrop-filter: blur(4px); color: #ffffff; border: 1px solid rgba(255,255,255,0.2); box-shadow: 0 2px 6px rgba(0,0,0,0.25);">{{ __('events.online') }}</span>
            @elseif ($event->location_type === 'hybrid')
                <span class="badge badge-dark" style="background: rgba(17, 24, 39, 0.88); backdrop-filter: blur(4px); color: #ffffff; border: 1px solid rgba(255,255,255,0.2); box-shadow: 0 2px 6px rgba(0,0,0,0.25);">{{ __('events.hybrid') }}</span>
            @endif
        </div>
    </a>

    <div class="card-body" style="padding: 20px; flex: 1; display: flex; flex-direction: column;">
        <div style="font-size: 0.8rem; font-weight: 600; color: var(--gold-hover); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px;">
            📅 {{ $event->starts_at->translatedFormat('l, j F Y') }} &middot; {{ $event->starts_at->format('H:i') }} WIB
        </div>

        <h3 style="font-size: 1.12rem; font-weight: 600; color: var(--text); line-height: 1.4; margin-bottom: 8px;">
            <a href="{{ $lroute('events.show', ['event' => $event->slug]) }}" style="color: inherit; text-decoration: none;">
                {{ $event->title }}
            </a>
        </h3>

        @if ($event->venue)
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                <span>📍</span> <span>{{ $event->venue }}</span>
            </p>
        @elseif ($event->location_type === 'online')
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                <span>🌐</span> <span>Live Online</span>
            </p>
        @endif

        @if ($event->description)
            <p style="font-size: 0.875rem; color: var(--text-muted); line-height: 1.5; margin-bottom: 0; margin-top: auto; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                {{ Str::limit(strip_tags((string) $event->description), 110) }}
            </p>
        @endif
    </div>

    <div style="border-top: 1px solid var(--line-light); padding: 12px 20px; font-size: 0.85rem; background: #fafafa; display: flex; align-items: center; justify-content: space-between;">
        @if ($event->isUnlimited())
            <span style="color: var(--text-muted);">{{ __('events.seats_unlimited') }}</span>
        @else
            <span style="color: var(--text-muted); font-weight: 500;">
                {{ trans_choice('events.seats_left', $event->seatsLeft() ?? 0, ['count' => $event->seatsLeft() ?? 0]) }}
            </span>
        @endif

        <a href="{{ $lroute('events.show', ['event' => $event->slug]) }}" style="color: var(--gold-hover); font-weight: 600; text-decoration: none;">
            {{ __('events.view_detail') }} &rarr;
        </a>
    </div>
</article>
