@extends('layouts.app')

@section('title', $portfolio->title . ' | ' . $settings('company_name', config('app.name')))
@section('description', Str::limit(strip_tags((string) $portfolio->description), 155))
@section('og_image', $portfolio->coverImage->first()?->url ?? '')

@php
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
    <div class="page-banner">
        <div class="container">
            <nav aria-label="Breadcrumb" class="breadcrumb-nav">
                <a href="{{ $lroute('home') }}">{{ __('nav.home') }}</a>
                <span>/</span>
                <a href="{{ $lroute('portfolio.index') }}">{{ __('nav.portfolio') }}</a>
                <span>/</span>
                <span style="color: #ffffff;">{{ Str::limit($portfolio->title, 40) }}</span>
            </nav>

            <h1 class="page-banner-title">{{ $portfolio->title }}</h1>

            <div
                style="display: flex; flex-wrap: wrap; align-items: center; gap: 16px; font-size: 0.95rem; color: var(--on-dark-muted); margin-top: 10px;">
                @if ($portfolio->location)
                    <span>📍 {{ $portfolio->location }}</span>
                @endif
                @if ($portfolio->price)
                    <span style="font-weight: 600; color: var(--gold);">
                        {{ __('services.currency') }} {{ number_format((float) $portfolio->price, 0, ',', '.') }}
                    </span>
                @endif
            </div>
        </div>
    </div>

    <div class="container" style="padding-bottom: 70px;">
        {{-- Gallery (CSP Alpine x-data="gallery") --}}
        @if ($portfolio->images->isNotEmpty())
            <div x-data="gallery" style="margin-bottom: 40px;">
                <div class="card-light" style="position: relative; overflow: hidden; background: #000000; padding: 0;">
                    <div style="position: relative; width: 100%; aspect-ratio: 16 / 10; max-height: 600px;">
                        @foreach ($portfolio->images as $i => $image)
                            <img data-gallery-item class="{{ $i === 0 ? 'is-active' : '' }}" src="{{ $image->url }}"
                                alt="{{ $image->alt_text ?? $portfolio->title }}" loading="{{ $i === 0 ? 'eager' : 'lazy' }}"
                                aria-hidden="{{ $i === 0 ? 'false' : 'true' }}"
                                style="width: 100%; height: 100%; object-fit: contain;">
                        @endforeach
                    </div>

                    @if ($portfolio->images->count() > 1)
                        <button type="button" data-gallery-prev class="btn btn-dark btn-sm"
                            style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); border-radius: 50%; width: 44px; height: 44px; padding: 0;"
                            aria-label="{{ __('portfolio.previous_image') }}">
                            &larr;
                        </button>
                        <button type="button" data-gallery-next class="btn btn-dark btn-sm"
                            style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%); border-radius: 50%; width: 44px; height: 44px; padding: 0;"
                            aria-label="{{ __('portfolio.next_image') }}">
                            &rarr;
                        </button>
                    @endif
                </div>

                @if ($portfolio->images->count() > 1)
                    <div style="display: flex; gap: 10px; overflow-x: auto; padding: 12px 0;">
                        @foreach ($portfolio->images as $i => $image)
                            <button type="button" data-gallery-thumb class="{{ $i === 0 ? 'is-active' : '' }}"
                                style="width: 80px; height: 80px; border-radius: var(--radius-btn); overflow: hidden; border: none; padding: 0; flex-shrink: 0;"
                                aria-current="{{ $i === 0 ? 'true' : 'false' }}"
                                aria-label="{{ __('portfolio.show_image', ['number' => $i + 1]) }}">
                                <img src="{{ $image->url }}" alt="" loading="lazy"
                                    style="width: 100%; height: 100%; object-fit: cover;">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        <div class="content-sidebar-grid">
            <div>
                <div class="card-light" style="padding: 28px;">
                    <h2 style="font-size: 1.35rem; font-weight: 600; color: var(--text); margin-bottom: 16px; padding-bottom: 10px; border-bottom: 1px solid var(--line-light);">
                        {{ __('portfolio.about') }}
                    </h2>

                    @if ($portfolio->description)
                        <div style="display: flex; flex-direction: column; gap: 14px; font-size: 1rem; line-height: 1.75; color: var(--text);">
                            @foreach (preg_split('/\R{2,}/', (string) $portfolio->description) as $paragraph)
                                <p>{{ $paragraph }}</p>
                            @endforeach
                        </div>
                    @else
                        <p style="font-size: 0.95rem; color: var(--text-muted); line-height: 1.6; margin-bottom: 0;">
                            Proyek visualisasi dan desain 3D arsitektur berkualitas tinggi oleh 3D Expert. Setiap detail dirender dengan presisi tinggi untuk menghadirkan visualisasi ruang yang realistis, estetis, dan fungsional sesuai kebutuhan klien.
                        </p>
                    @endif
                </div>
            </div>

            {{-- Specifications Sidebar --}}
            <aside>
                <div class="card-light" style="padding: 24px; position: sticky; top: 90px;">
                    <h2 style="font-size: 1.2rem; font-weight: 600; color: var(--text); margin-bottom: 18px; padding-bottom: 8px; border-bottom: 1px solid var(--line-light);">
                        {{ __('portfolio.specifications') }}
                    </h2>

                    <dl style="display: flex; flex-direction: column; gap: 14px; font-size: 0.95rem; margin-bottom: 24px;">
                        <div style="display: flex; justify-content: space-between; gap: 12px; padding-bottom: 8px; border-bottom: 1px solid #f0f0f0;">
                            <dt style="color: var(--text-muted);">Layanan</dt>
                            <dd style="font-weight: 600; color: var(--text); text-align: right;">Desain &amp; 3D Rendering</dd>
                        </div>

                        @if ($portfolio->style)
                            <div style="display: flex; justify-content: space-between; gap: 12px; padding-bottom: 8px; border-bottom: 1px solid #f0f0f0;">
                                <dt style="color: var(--text-muted);">{{ __('portfolio.style') }}</dt>
                                <dd style="font-weight: 600; color: var(--text); text-align: right;">{{ $portfolio->style }}</dd>
                            </div>
                        @endif

                        @if ($portfolio->location)
                            <div style="display: flex; justify-content: space-between; gap: 12px; padding-bottom: 8px; border-bottom: 1px solid #f0f0f0;">
                                <dt style="color: var(--text-muted);">{{ __('portfolio.location') }}</dt>
                                <dd style="font-weight: 600; color: var(--text); text-align: right;">{{ $portfolio->location }}</dd>
                            </div>
                        @endif

                        @if ($portfolio->building_size)
                            <div style="display: flex; justify-content: space-between; gap: 12px; padding-bottom: 8px; border-bottom: 1px solid #f0f0f0;">
                                <dt style="color: var(--text-muted);">Luas Bangunan</dt>
                                <dd style="font-weight: 600; color: var(--text); text-align: right;">{{ $portfolio->building_size }} m²</dd>
                            </div>
                        @endif

                        @if ($portfolio->land_size)
                            <div style="display: flex; justify-content: space-between; gap: 12px; padding-bottom: 8px; border-bottom: 1px solid #f0f0f0;">
                                <dt style="color: var(--text-muted);">Luas Lahan</dt>
                                <dd style="font-weight: 600; color: var(--text); text-align: right;">{{ $portfolio->land_size }} m²</dd>
                            </div>
                        @endif

                        @if ($portfolio->floors)
                            <div style="display: flex; justify-content: space-between; gap: 12px; padding-bottom: 8px; border-bottom: 1px solid #f0f0f0;">
                                <dt style="color: var(--text-muted);">Jumlah Lantai</dt>
                                <dd style="font-weight: 600; color: var(--text); text-align: right;">{{ trans_choice('portfolio.floors', $portfolio->floors) }}</dd>
                            </div>
                        @endif

                        @if ($portfolio->bedrooms)
                            <div style="display: flex; justify-content: space-between; gap: 12px; padding-bottom: 8px; border-bottom: 1px solid #f0f0f0;">
                                <dt style="color: var(--text-muted);">Kamar Tidur</dt>
                                <dd style="font-weight: 600; color: var(--text); text-align: right;">{{ trans_choice('portfolio.bedrooms', $portfolio->bedrooms) }}</dd>
                            </div>
                        @endif

                        @if ($portfolio->price)
                            <div style="display: flex; justify-content: space-between; gap: 12px; padding-bottom: 8px; border-bottom: 1px solid #f0f0f0;">
                                <dt style="color: var(--text-muted);">Estimasi Nilai</dt>
                                <dd style="font-weight: 600; color: var(--gold-hover); text-align: right;">
                                    {{ __('services.currency') }} {{ number_format((float) $portfolio->price, 0, ',', '.') }}
                                </dd>
                            </div>
                        @endif

                        @foreach ($portfolio->specs as $spec)
                            <div style="display: flex; justify-content: space-between; gap: 12px; padding-bottom: 8px; border-bottom: 1px solid #f0f0f0;">
                                <dt style="color: var(--text-muted);">{{ $spec->label }}</dt>
                                <dd style="font-weight: 600; color: var(--text); text-align: right;">{{ $spec->value }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    @if ($wa = $settings('company_whatsapp'))
                        <div>
                            <a href="{{ $wa }}?text={{ urlencode('Halo 3D Experts, saya tertarik dengan proyek portofolio: ' . $portfolio->title) }}"
                               target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-block" style="box-shadow: 0 4px 12px rgba(200, 160, 89, 0.25);">
                                {{ __('portfolio.request_similar') }};
                            </a>
                        </div>
                    @endif
                </div>
            </aside>
        </div>

        @if ($related->isNotEmpty())
            <div style="margin-top: 60px;">
                <h2 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 24px;">{{ __('portfolio.related') }}</h2>
                <div class="grid grid-cols-4">
                    @foreach ($related as $item)
                        <x-portfolio-card :portfolio="$item" light />
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endsection