
@extends('layouts.app')

@section('title', $portfolio->title . ' | ' . $settings('company_name', config('app.name')))
@section('description', Str::limit(strip_tags((string) $portfolio->description), 155))
@section('og_image', $portfolio->coverImage->first()?->url ?? '')

@php
    // Built here rather than inline in @json(...): Blade parses a directive's
    // arguments by matching brackets, and this literal defeated it -- a
    // multi-line array holding both a nested array and '@'-prefixed keys
    // ('@context', '@type') compiled to a PHP parse error. The view was
    // unreachable until the portfolio routes were registered, so the error had
    // never surfaced.
    $structuredData = [
        '@context' => 'https://schema.org',
        '@type' => 'CreativeWork',
        'name' => $portfolio->title,
        'description' => Str::limit(strip_tags((string) $portfolio->description), 300),
        'url' => $lroute('portfolio.show', ['portfolio' => $portfolio]),
        'image' => $portfolio->images->map->url->filter()->values()->all(),
    ];
@endphp

@push('head')
    <script type="application/ld+json">
        {!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}
    </script>
@endpush

@section('content')
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6">
        <nav aria-label="Breadcrumb" class="text-sm text-neutral-500">
            <ol class="flex flex-wrap items-center gap-2">
                <li><a href="{{ $lroute('home') }}" class="hover:text-amber-400">{{ __('nav.home') }}</a></li>
                <li aria-hidden="true">/</li>
                <li><a href="{{ $lroute('portfolio.index') }}" class="hover:text-amber-400">{{ __('nav.portfolio') }}</a></li>
                <li aria-hidden="true">/</li>
                <li class="text-neutral-300" aria-current="page">{{ Str::limit($portfolio->title, 40) }}</li>
            </ol>
        </nav>

        <h1 class="mt-5 text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ $portfolio->title }}</h1>

        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-neutral-400">
            @if ($portfolio->location)
                <span>{{ $portfolio->location }}</span>
            @endif
            @if ($portfolio->price)
                <span class="font-semibold text-amber-400">
                    {{ __('services.currency') }} {{ number_format((float) $portfolio->price, 0, ',', '.') }}
                </span>
            @endif
            <span class="text-neutral-600">
                {{ trans_choice('portfolio.view_count', $portfolio->view_count, ['count' => number_format($portfolio->view_count, 0, ',', '.')]) }}
            </span>
        </div>

        {{-- Gallery. Legacy rendered a fixed set of divs with inline styles; this
             is one Alpine component with keyboard support. --}}
        @if ($portfolio->images->isNotEmpty())
            {{-- CSP NOTE: only x-data="gallery" here. The component wires its own
                 prev/next/thumbnail listeners and toggles .is-active, because
                 SecurityHeaders.php sends script-src without 'unsafe-eval' and
                 the CSP-safe Alpine build cannot evaluate @click="next()" or
                 :class="current === 0 ? ... : ...". --}}
            <div x-data="gallery" class="mt-8">
                <div class="relative overflow-hidden rounded-xl border border-white/10 bg-neutral-900">
                    <div class="aspect-[16/10]">
                        @foreach ($portfolio->images as $i => $image)
                            <img data-gallery-item
                                 @class(['h-full w-full object-cover', 'is-active' => $i === 0])
                                 src="{{ $image->url }}"
                                 alt="{{ $image->alt_text ?? $portfolio->title }}"
                                 loading="{{ $i === 0 ? 'eager' : 'lazy' }}"
                                 aria-hidden="{{ $i === 0 ? 'false' : 'true' }}">
                        @endforeach
                    </div>

                    @if ($portfolio->images->count() > 1)
                        <button type="button" data-gallery-prev
                                class="absolute left-3 top-1/2 -translate-y-1/2 rounded-full bg-neutral-950/70 p-2
                                       text-white transition hover:bg-neutral-950"
                                aria-label="{{ __('portfolio.previous_image') }}">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </button>
                        <button type="button" data-gallery-next
                                class="absolute right-3 top-1/2 -translate-y-1/2 rounded-full bg-neutral-950/70 p-2
                                       text-white transition hover:bg-neutral-950"
                                aria-label="{{ __('portfolio.next_image') }}">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    @endif
                </div>

                @if ($portfolio->images->count() > 1)
                    <div class="mt-3 grid grid-cols-4 gap-2 sm:grid-cols-6 lg:grid-cols-8">
                        @foreach ($portfolio->images as $i => $image)
                            <button type="button" data-gallery-thumb
                                    @class(['aspect-square overflow-hidden rounded-lg transition', 'is-active' => $i === 0])
                                    aria-current="{{ $i === 0 ? 'true' : 'false' }}"
                                    aria-label="{{ __('portfolio.show_image', ['number' => $i + 1]) }}">
                                <img src="{{ $image->url }}" alt="" loading="lazy" class="h-full w-full object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        <div class="mt-12 grid gap-10 lg:grid-cols-3">
            <div class="lg:col-span-2">
                @if ($portfolio->description)
                    <h2 class="text-xl font-semibold text-white">{{ __('portfolio.about') }}</h2>
                    <div class="mt-3 space-y-4 text-sm leading-relaxed text-neutral-300">
                        @foreach (preg_split('/\R{2,}/', (string) $portfolio->description) as $paragraph)
                            <p>{{ $paragraph }}</p>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Specs. Legacy property_details was already key/value; desain_rumah's
                 fixed columns were folded into the same shape during migration. --}}
            @if ($portfolio->specs->isNotEmpty())
                <aside class="rounded-xl border border-white/10 bg-neutral-900 p-6">
                    <h2 class="text-base font-semibold text-white">{{ __('portfolio.specifications') }}</h2>
                    <dl class="mt-4 divide-y divide-white/5 text-sm">
                        @foreach ($portfolio->specs as $spec)
                            <div class="flex justify-between gap-4 py-2.5">
                                <dt class="text-neutral-500">{{ $spec->label }}</dt>
                                <dd class="text-right font-medium text-neutral-200">{{ $spec->value }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    {{-- D12: contact is a WhatsApp link, never a form. Prefilling
                         the message with the project title would be better, but
                         company_whatsapp is a full URL that may already carry a
                         query string -- that needs a helper, not string
                         concatenation. Tracked for after T0. --}}
                    @if ($wa = $settings('company_whatsapp'))
                        <a href="{{ $wa }}" target="_blank" rel="noopener noreferrer"
                           class="mt-6 block rounded-lg bg-amber-500 px-4 py-2.5 text-center text-sm
                                  font-semibold text-neutral-950 transition hover:bg-amber-400">
                            {{ __('portfolio.request_similar') }}
                        </a>
                    @endif
                </aside>
            @endif
        </div>

        @if ($related->isNotEmpty())
            <section class="mt-16">
                <h2 class="text-xl font-semibold text-white">{{ __('portfolio.related') }}</h2>
                <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                    @foreach ($related as $item)
                        <x-portfolio-card :portfolio="$item" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
