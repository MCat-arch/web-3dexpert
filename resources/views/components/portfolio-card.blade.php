@props(['portfolio', 'featured' => false, 'light' => false])

@php
    $cover = $portfolio->coverImage->first() ?? $portfolio->images->first();
@endphp

<article class="{{ $light ? 'card-light' : 'card-dark' }}" style="{{ $featured ? 'grid-column: span 2; grid-row: span 2;' : '' }}">
    <div style="position: relative; width: 100%; aspect-ratio: {{ $featured ? '16 / 10' : '4 / 3' }}; overflow: hidden; background: var(--ink-800);">
        @if ($cover?->url)
            <img src="{{ $cover->url }}"
                 alt="{{ $cover->alt_text ?? $portfolio->title }}"
                 loading="{{ $featured ? 'eager' : 'lazy' }}"
                 decoding="async"
                 class="card-thumb"
                 style="width: 100%; height: 100%; object-fit: cover;">
        @else
            <div style="display: flex; height: 100%; width: 100%; align-items: center; justify-content: center; font-size: 0.875rem; color: var(--text-muted);">
                {{ __('portfolio.no_image') }}
            </div>
        @endif

        <div style="position: absolute; inset: 0; background: linear-gradient(to top, rgba(17,17,17,0.92) 0%, rgba(17,17,17,0.3) 50%, transparent 100%); pointer-events: none;"></div>

        <div style="position: absolute; inset-inline: 0; bottom: 0; padding: 16px; color: #ffffff;">
            <h3 style="font-size: {{ $featured ? '1.35rem' : '1.05rem' }}; font-weight: 600; color: #ffffff; line-height: 1.3;">
                <a href="{{ $lroute('portfolio.show', ['portfolio' => $portfolio->slug]) }}" style="color: #ffffff; text-decoration: none;">
                    {{ $portfolio->title }}
                </a>
            </h3>

            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 12px; font-size: 0.8rem; color: var(--on-dark-2); margin-top: 6px;">
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
