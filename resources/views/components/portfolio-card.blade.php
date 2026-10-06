@props(['portfolio', 'featured' => false])

@php
    $cover = $portfolio->coverImage->first() ?? $portfolio->images->first();
@endphp

<article @class([
    'group relative overflow-hidden rounded-xl border border-white/10 bg-neutral-900',
    'sm:col-span-2 sm:row-span-2' => $featured,
])>
    <div>
        <div @class(['relative overflow-hidden bg-neutral-800', 'aspect-[4/3]' => ! $featured, 'aspect-[16/10]' => $featured])>
            @if ($cover?->url)
                <img src="{{ $cover->url }}"
                     alt="{{ $cover->alt_text ?? $portfolio->title }}"
                     loading="{{ $featured ? 'eager' : 'lazy' }}"
                     decoding="async"
                     class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
            @else
                {{-- Legacy would render an empty box here; at least say so. --}}
                <div class="flex h-full w-full items-center justify-center text-sm text-neutral-600">
                    {{ __('portfolio.no_image') }}
                </div>
            @endif

            <div class="absolute inset-0 bg-gradient-to-t from-neutral-950/90 via-neutral-950/20 to-transparent"></div>
        </div>

        <div class="absolute inset-x-0 bottom-0 p-4">
            <h3 @class(['font-semibold text-white', 'text-lg sm:text-2xl' => $featured, 'text-sm sm:text-base' => ! $featured])>
                {{ $portfolio->title }}
            </h3>

            <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-neutral-300">
                @if ($portfolio->location)
                    <span>{{ $portfolio->location }}</span>
                @endif
                @if ($portfolio->bedrooms)
                    <span>{{ trans_choice('portfolio.bedrooms', $portfolio->bedrooms) }}</span>
                @endif
                @if ($portfolio->floors)
                    <span>{{ trans_choice('portfolio.floors', $portfolio->floors) }}</span>
                @endif
            </div>
        </div>
    </div>
</article>
