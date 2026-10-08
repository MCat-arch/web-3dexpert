@props(['event'])

<article class="flex flex-col overflow-hidden rounded-xl border border-white/10 bg-neutral-900">
    <div class="flex-1 p-5">
        <div class="flex flex-wrap items-center gap-2 text-xs">
            @if ($event->isFree())
                <span class="rounded-full bg-emerald-500/15 px-2.5 py-1 font-semibold text-emerald-400">
                    {{ __('events.free') }}
                </span>
            @else
                <span class="rounded-full bg-amber-500/15 px-2.5 py-1 font-semibold text-amber-300">
                    {{ __('events.paid') }}
                </span>
            @endif

            @if ($event->location_type === 'online')
                <span class="rounded-full bg-white/10 px-2.5 py-1 text-neutral-300">{{ __('events.online') }}</span>
            @elseif ($event->location_type === 'hybrid')
                <span class="rounded-full bg-white/10 px-2.5 py-1 text-neutral-300">{{ __('events.hybrid') }}</span>
            @endif
        </div>

        <h3 class="mt-3 text-base font-semibold text-white">
            <a href="{{ $lroute('events.show', ['event' => $event->slug]) }}" class="hover:text-amber-400">
                {{ $event->title }}
            </a>
        </h3>

        <p class="mt-2 text-sm text-neutral-400">
            {{ $event->starts_at->translatedFormat('l, j F Y') }} &middot; {{ $event->starts_at->format('H:i') }}
        </p>

        @if ($event->venue)
            <p class="mt-1 text-sm text-neutral-500">{{ $event->venue }}</p>
        @endif
    </div>

    {{-- Availability is read live, never cached: a stale seat count either
         oversells the event or hides seats that are actually free (O5). --}}
    <div class="border-t border-white/10 px-5 py-3 text-xs">
        @if (! $event->registrationIsOpen())
            <span class="text-neutral-500">{{ __('events.closed') }}</span>
        @elseif ($event->isUnlimited())
            <span class="text-neutral-400">{{ __('events.seats_unlimited') }}</span>
        @else
            <span @class(['text-neutral-400', 'text-red-400' => ! $event->hasSeatsLeft()])>
                {{ trans_choice('events.seats_left', $event->seatsLeft(), ['count' => $event->seatsLeft()]) }}
            </span>
        @endif
    </div>
</article>
