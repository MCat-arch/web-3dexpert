{{--
    3D Expert Web Homepage (Semantic Plain CSS per docs/design.md)
    PARITY CONTRACT: 14 sections in strict order:
      1 Header (layouts.app)
      2 Hero
      3 Features
      4 Portfolio
      5 Testimonials
      6 Benefits
      7 Review desain
      8 Location
      9 Mengapa Memilih Kami
      10 Services
      11 Order flow
      12 FAQ
      13 Floating WhatsApp
      14 Footer (layouts.app)
--}}
@extends('layouts.app')

@section('title', $settings('hero_heading') ?? $settings('seo_title') ?? config('app.name'))
@section('description', $settings('hero_subheading') ?? $settings('seo_description'))

@section('content')
    @php
        $whatsapp = $settings('company_whatsapp', 'https://wa.me/6282338389488');
        $locationVideoId = \App\Models\DesignReview::extractYoutubeId($settings('location_video_url', 'https://www.youtube.com/watch?v=m7Bc3pLyij0'));
        $whyVideoId = \App\Models\DesignReview::extractYoutubeId($settings('why_video_url', 'https://www.youtube.com/watch?v=m7Bc3pLyij0'));

        // [CHANGED] Portfolio: maksimal 3 kartu tampil (1 featured + 2 grid),
        // sisanya diarahkan ke halaman portfolio via tombol "Lihat Selengkapnya".
        $visiblePortfolios = $portfolios->take(2);
        $hasMorePortfolios = $portfolios->count() > 2;

        // [HOTFIX] Tanpa filter kolom (nama kolom video belum terkonfirmasi).
        // Jendela utama = ID 11-20 (10 item) -> grid-cols-4 -> 3 baris (4+4+2).
        $testimonialWindow = $testimonials->whereIn('id', range(11, 20))->values();
        $initialTestimonials = $testimonialWindow->take(12);
        $initialTestimonialIds = $initialTestimonials->pluck('id')->all();
        $moreTestimonials = $testimonials
            ->reject(fn ($t) => in_array($t->id, $initialTestimonialIds))
            ->values();
    @endphp

    {{-- ========== 2. HERO ========== --}}
    @if ($heroSlides->isNotEmpty())
        <section id="hero-banner"
                 x-data="carousel" data-interval="4000"
                 class="hero-section"
                 aria-roledescription="carousel" aria-label="{{ __('home.hero') }}">
            @foreach ($heroSlides as $i => $slide)
                <div data-slide class="hero-slide {{ $i === 0 ? 'is-active' : '' }}"
                     role="group" aria-hidden="{{ $i === 0 ? 'false' : 'true' }}"
                     aria-label="{{ $i + 1 }} / {{ $heroSlides->count() }}">
                    @if ($slide->url)
                        <img src="{{ $slide->url }}" alt="{{ $slide->alt_text ?? '' }}"
                             loading="{{ $i === 0 ? 'eager' : 'lazy' }}"
                             fetchpriority="{{ $i === 0 ? 'high' : 'auto' }}"
                             decoding="async"
                             class="hero-slide-bg">
                    @endif
                    <div class="hero-slide-overlay"></div>
                </div>
            @endforeach

            <div class="container hero-content">
                <h1 class="hero-title">
                    {{ $settings('hero_heading', 'Wujudkan Rumah Mewah dengan Sentuhan Profesional') }}
                </h1>

                <p class="hero-subtitle">
                    {{ $settings('hero_subheading', 'Perpaduan sempurna antara estetika, fungsi, dan eksklusivitas.') }}
                </p>

                {{-- CTA tunggal: hanya "Konsultasi Gratis". --}}
                <div class="hero-cta-group">
                    <a href="#layanan" class="btn btn-outline">
                        {{ __('home.free_consultation') }}
                    </a>
                </div>
            </div>

            @if ($heroSlides->count() > 1)
                <div class="hero-dots-container">
                    @foreach ($heroSlides as $i => $slide)
                        <button type="button" data-dot
                                class="hero-dot {{ $i === 0 ? 'is-active' : '' }}"
                                aria-current="{{ $i === 0 ? 'true' : 'false' }}"
                                aria-label="{{ __('home.go_to_slide', ['number' => $i + 1]) }}"></button>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    {{-- ========== 3. FEATURES (Background: --ink-950) ========== --}}
    @if ($settings('home_heading') || $settings('stat_projects') || $settings('stat_clients'))
        <section class="section section-dark" style="border-bottom: 1px solid var(--line-dark);">
            <div class="container">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 40px; align-items: center;">
                    <div>
                        @if ($heading = $settings('home_heading'))
                            <h2 class="section-title" style="text-align: left;">{{ $heading }}</h2>
                        @endif

                        @if ($sub = $settings('home_subheading'))
                            <p class="section-subtitle" style="text-align: left; margin-bottom: 24px;">{{ $sub }}</p>
                        @endif

                        <div style="display: flex; gap: 14px; flex-wrap: wrap;">
                            <a href="#portofolio" class="btn btn-white">
                                {{ __('home.all_designs') }}
                            </a>

                            @if ($featured)
                                <a href="{{ $lroute('portfolio.show', ['portfolio' => $featured]) }}" class="btn btn-outline">
                                    {{ __('home.best_design') }}
                                </a>
                            @endif
                        </div>
                    </div>

                    <div class="stats-grid" style="margin-top: 0;">
                        <div class="stat-card">
                            <div class="stat-number">
                                {{ number_format((int) $settings('stat_projects', 0), 0, ',', '.') }}+
                            </div>
                            <div class="stat-label">{{ __('home.stat_projects') }}</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number">
                                {{ number_format((int) $settings('stat_clients', 0), 0, ',', '.') }}+
                            </div>
                            <div class="stat-label">{{ __('home.stat_clients') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- ========== 4. PORTFOLIO (Background: --ink-950) ========== --}}
    @if ($featured || $portfolios->isNotEmpty())
        <section id="portofolio" class="section section-dark" style="scroll-margin-top: 80px;">
            <div class="container">
                <x-section-heading
                    :eyebrow="__('home.portfolio_eyebrow')"
                    :title="__('home.portfolio_title')" />

                <div class="portfolio-bento-grid">
                    @if ($featured)
                        <x-portfolio-card :portfolio="$featured" featured />
                    @endif

                    @foreach ($visiblePortfolios as $portfolio)
                        <x-portfolio-card :portfolio="$portfolio" />
                    @endforeach
                </div>

                @if ($hasMorePortfolios)
                    <div style="margin-top: 40px; display: flex; justify-content: center;">
                        <a href="{{ $lroute('portfolio.index') }}" class="btn btn-outline btn-lg">
                            {{ __('home.view_more') }}
                        </a>
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- ========== 5. TESTIMONIALS (Background: --ink-950) ========== --}}
    @if ($highlightedTestimonials->isNotEmpty() || $testimonials->isNotEmpty())
        <section class="section section-dark" style="border-top: 1px solid var(--line-dark); border-bottom: 1px solid var(--line-dark);">
            <div class="container">
                <x-section-heading
                    :eyebrow="__('home.testimonials_eyebrow')"
                    :title="__('home.testimonials_title')" />

                @if ($highlightedTestimonials->isNotEmpty())
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px; margin-bottom: 24px;">
                        @foreach ($highlightedTestimonials as $testimonial)
                            <x-video-facade :video="$testimonial" />
                        @endforeach
                    </div>
                @endif

                {{-- Grid window ID 11-20 (3 baris). Expand/collapse via komponen
                     CSP-safe "testimonialToggle" (markup zero ekspresi Alpine). --}}
                @if ($initialTestimonials->isNotEmpty())
                    <div x-data="testimonialToggle">
                        <div class="grid grid-cols-4" data-testimonial-grid style="scroll-margin-top: 90px;">
                            @foreach ($initialTestimonials as $testimonial)
                                <x-video-facade :video="$testimonial">
                                    @if ($testimonial->rating)
                                        <div style="margin-top: 8px;">
                                            <x-star-rating :rating="$testimonial->rating" />
                                        </div>
                                    @endif
                                </x-video-facade>
                            @endforeach

                            {{-- Server-rendered = no-JS state: collapsed (is-hidden). --}}
                            @foreach ($moreTestimonials as $testimonial)
                                <div data-reveal-item class="is-hidden">
                                    <x-video-facade :video="$testimonial">
                                        @if ($testimonial->rating)
                                            <div style="margin-top: 8px;">
                                                <x-star-rating :rating="$testimonial->rating" />
                                            </div>
                                        @endif
                                    </x-video-facade>
                                </div>
                            @endforeach
                        </div>

                        @if ($moreTestimonials->isNotEmpty())
                            <div style="margin-top: 24px; display: flex; justify-content: center;">
                                <button type="button"
                                        data-reveal-button
                                        hidden
                                        data-label-expand="{{ __('home.testimonials_show_all') }}"
                                        data-label-collapse="{{ __('home.testimonials_show_less') }}"
                                        class="show-more-arrow"
                                        aria-expanded="false"
                                        aria-label="{{ __('home.testimonials_show_all') }}">
                                    <svg width="26" height="26" fill="none" stroke="currentColor"
                                         stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- ========== 6. BENEFITS (Background: --card white, --text) ========== --}}
    @if ($deliverables->isNotEmpty())
        <section class="section section-card">
            <div class="container">
                <x-section-heading :title="__('home.deliverables_title')" />

                <div class="benefits-grid">
                    @foreach ($deliverables as $item)
                        <x-benefit-tile :item="$item" />
                    @endforeach
                </div>

                @if ($whatsapp)
                    <div style="margin-top: 48px; text-align: center;">
                        <a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-lg">
                            {{ __('home.book_now') }}
                        </a>
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- ========== 7. REVIEW DESAIN (Background: --ink-950) ========== --}}
    @if ($designReviews->isNotEmpty())
        <section class="section section-dark" style="border-top: 1px solid var(--line-dark); border-bottom: 1px solid var(--line-dark);">
            <div class="container">
                <x-section-heading
                    :eyebrow="__('home.reviews_eyebrow')"
                    :title="__('home.reviews_title')" />

                <div class="grid grid-cols-3">
                    @foreach ($designReviews as $review)
                        <x-video-facade :video="$review" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ========== 8. LOCATION (Gradien: --ink-900 to --ink-500) ========== --}}
    @if ($settings('location_office_label') || $settings('company_address') || $locationVideoId)
        <section class="section location-section">
            <div class="container">
                <x-section-heading :title="__('home.location_title')" />

                <div class="location-card">
                    <div>
                        @if ($officeLabel = $settings('location_office_label'))
                            <p style="display: flex; align-items: center; gap: 8px; font-size: 0.9rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; color: var(--gold); margin-bottom: 12px;">
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <span>{{ $officeLabel }}</span>
                            </p>
                        @endif

                        @if ($address = $settings('company_address'))
                            <p style="white-space: pre-line; font-size: 1.05rem; line-height: 1.7; color: var(--on-dark-2); margin-bottom: 24px;">{{ $address }}</p>
                        @endif

                        @if ($mapsUrl = $settings('company_maps_url'))
                            <a href="{{ $mapsUrl }}" target="_blank" rel="noopener noreferrer"
                               class="btn btn-outline btn-sm">
                                <span>{{ __('home.view_on_maps') }}</span>
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                            </a>
                        @endif
                    </div>

                    @if ($locationVideoId)
                        <div>
                            <x-video-facade :id="$locationVideoId" :title="__('home.location_title')" />
                        </div>
                    @endif
                </div>
            </div>
        </section>
    @endif

    {{-- ========== 9. MENGAPA MEMILIH KAMI (Gradien + Content Wrapper Putih) ========== --}}
    @if ($settings('why_description') || $whyVideoId || $benefits->isNotEmpty() || $processes->isNotEmpty())
        <section id="tentang" class="section why-us-section" style="scroll-margin-top: 80px;">
            <div class="container">
                <div class="why-us-wrapper">
                    <x-section-heading :title="__('home.why_title')" />

                    @if ($whyVideoId)
                        <div style="max-width: 800px; margin: 0 auto 36px auto;">
                            <x-video-facade :id="$whyVideoId" :title="__('home.why_title')" />
                        </div>
                    @endif

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px;">
                        @if ($description = $settings('why_description'))
                            <div class="card-light" style="padding: 24px;">
                                <p style="white-space: pre-line; font-size: 0.95rem; line-height: 1.7; color: var(--text);">{{ $description }}</p>
                            </div>
                        @endif

                        @foreach ([$benefits, $processes] as $column)
                            @if ($column->isNotEmpty())
                                <div class="card-light" style="padding: 24px;">
                                    <ul style="list-style: none; display: flex; flex-direction: column; gap: 14px;">
                                        @foreach ($column as $item)
                                            <li style="display: flex; align-items: flex-start; gap: 10px; font-size: 0.95rem; color: var(--text);">
                                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"
                                                     style="color: var(--gold-hover); flex-shrink: 0; margin-top: 2px;">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                <span>{{ $item->label }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- ========== 10. SERVICES (Background: --ink-600) ========== --}}
    @if ($services->isNotEmpty())
        <section id="layanan" class="section services-section" style="scroll-margin-top: 80px;">
            <div class="container">
                <x-section-heading
                    :eyebrow="__('home.services_eyebrow')"
                    :title="__('home.services_legacy_title')" />

                <div class="grid grid-cols-3">
                    @foreach ($services->take(3) as $service)
                        <x-service-card :service="$service" />
                    @endforeach
                </div>

                <div style="margin-top: 48px; text-align: center;">
                    <a href="{{ $lroute('services.index') }}" class="btn btn-primary btn-lg">
                        {{ __('home.consult_now') }}
                    </a>
                </div>
            </div>
        </section>
    @endif

    {{-- ========== 11. ORDER FLOW (Background: --paper) ========== --}}
    {{-- [SIMPLIFIED] Embed Instagram langsung (hardcoded), varian /embed.
         Setting 'process_link_url' tidak lagi dipakai section ini. --}}
    <section id="informasi" class="section order-flow-section" style="scroll-margin-top: 80px;">
        <div class="container">
            <x-section-heading :title="__('home.process_title')" />

            <div class="ig-embed-card">
                <iframe src="https://www.instagram.com/reel/DD4BD7tS71i/embed"
                        loading="lazy"
                        scrolling="no"
                        allowtransparency="true"
                        allowfullscreen
                        title="{{ __('home.process_title') }}"></iframe>
            </div>
        </div>
    </section>

    {{-- ========== 12. FAQ (Background: --ink-800) ========== --}}
    @if ($faqs->isNotEmpty())
        <section class="section faq-section">
            <div class="container">
                <x-section-heading :title="__('home.faq_title')" />

                <div>
                    <x-faq-accordion :faqs="$faqs" />
                </div>
            </div>
        </section>
    @endif
@endsection