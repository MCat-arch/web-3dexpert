@props(['service'])

<article class="flex flex-col rounded-xl border border-white/10 bg-neutral-900 p-6">
    <h3 class="text-lg font-semibold text-white">{{ $service->title }}</h3>

    <div class="mt-4">
        @if ($service->original_price)
            <p class="text-sm text-neutral-500 line-through">
                {{ __('services.currency') }} {{ number_format((float) $service->original_price, 0, ',', '.') }}
            </p>
        @endif

        @if ($service->price)
            <p class="text-2xl font-bold text-amber-400">
                {{ __('services.currency') }} {{ number_format((float) $service->price, 0, ',', '.') }}
            </p>
        @endif

        @if ($percent = $service->discount_percent)
            <span class="mt-2 inline-block rounded-full bg-amber-500/15 px-3 py-1 text-xs
                         font-semibold text-amber-400">
                {{ __('services.discount', ['percent' => $percent]) }}
            </span>
        @elseif ($service->discount_text)
            <span class="mt-2 inline-block rounded-full bg-amber-500/15 px-3 py-1 text-xs
                         font-semibold text-amber-400">
                {{ $service->discount_text }}
            </span>
        @endif
    </div>

    @if ($service->features->isNotEmpty())
        <ul class="mt-5 flex-1 space-y-2 text-sm">
            @foreach ($service->features as $feature)
                @if ($feature->is_heading)
                    <li class="pt-2 font-semibold text-neutral-200">{{ $feature->label }}</li>
                @else
                    <li class="flex gap-2 text-neutral-400">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" fill="none"
                             stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>{{ $feature->label }}</span>
                    </li>
                @endif
            @endforeach
        </ul>
    @endif

    <div class="mt-6 flex flex-wrap gap-2">
        @if ($service->brochure_path)
            <a href="{{ route('media.show', ['path' => $service->brochure_path]) }}"
               class="rounded-lg border border-white/15 px-4 py-2 text-sm font-medium
                      text-neutral-300 transition hover:border-amber-500/50 hover:text-amber-400">
                {{ __('services.brochure') }}
            </a>
        @endif
    </div>
</article>
