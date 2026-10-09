@props(['service'])

<article class="card-dark" style="padding: 28px 24px;">
    <h3 style="font-size: 1.25rem; font-weight: 600; color: #ffffff; margin-bottom: 12px;">{{ $service->title }}</h3>

    <div style="margin-bottom: 16px;">
        @if ($service->original_price)
            <p style="font-size: 0.875rem; color: var(--on-dark-muted); text-decoration: line-through; margin-bottom: 2px;">
                {{ __('services.currency') }} {{ number_format((float) $service->original_price, 0, ',', '.') }}
            </p>
        @endif

        @if ($service->price)
            <p style="font-size: 1.65rem; font-weight: 700; color: var(--gold); line-height: 1.2;">
                {{ __('services.currency') }} {{ number_format((float) $service->price, 0, ',', '.') }}
            </p>
        @endif

        @if ($percent = $service->discount_percent)
            <span class="badge badge-amber" style="margin-top: 8px;">
                {{ __('services.discount', ['percent' => $percent]) }}
            </span>
        @elseif ($service->discount_text)
            <span class="badge badge-amber" style="margin-top: 8px;">
                {{ $service->discount_text }}
            </span>
        @endif
    </div>

    @if ($service->features->isNotEmpty())
        <ul style="list-style: none; margin-bottom: 24px; flex: 1 1 auto; display: flex; flex-direction: column; gap: 10px; font-size: 0.9rem;">
            @foreach ($service->features as $feature)
                @if ($feature->is_heading)
                    <li style="font-weight: 600; color: var(--on-dark); padding-top: 6px;">{{ $feature->label }}</li>
                @else
                    <li style="display: flex; align-items: flex-start; gap: 8px; color: var(--on-dark-2);">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"
                             style="color: var(--gold); flex-shrink: 0; margin-top: 2px;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>{{ $feature->label }}</span>
                    </li>
                @endif
            @endforeach
        </ul>
    @endif

    <div style="margin-top: auto; display: flex; flex-wrap: wrap; gap: 10px;">
        @if ($service->brochure_path)
            <a href="{{ route('media.show', ['path' => $service->brochure_path]) }}"
               class="btn btn-outline btn-sm">
                {{ __('services.brochure') }}
            </a>
        @endif
    </div>
</article>
