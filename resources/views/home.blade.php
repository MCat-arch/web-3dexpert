{{--
    Replaces index.php (928 lines) AND index-en.php (961 lines). One file.

    SECTION ORDER is the parity contract from task.md. Do not reorder:
      1 Header (layouts.app)   8 Location
      2 Hero                   9 Mengapa Memilih Kami
      3 Features              10 Services
      4 Portfolio             11 Order flow
      5 Testimonials          12 FAQ
      6 Benefits              13 Floating WhatsApp
      7 Review desain         14 Footer (layouts.app)

    Design is modernised (Tailwind, dark theme, click-to-load video); content is
    the legacy content. Anything that differs from legacy is commented inline.
--}}
@extends('layouts.app')

@section('title', $settings('hero_heading') ?? $settings('seo_title') ?? config('app.name'))
@section('description', $settings('hero_subheading') ?? $settings('seo_description'))

@section('content')
    @php
        $whatsapp = $settings('company_whatsapp');
        $locationVideoId = \App\Models\DesignReview::extractYoutubeId($settings('location_video_url'));
        $whyVideoId = \App\Models\DesignReview::extractYoutubeId($settings('why_video_url'));
        $visiblePortfolios = $portfolios->take(\App\Http\Controllers\HomeController::PORTFOLIO_VISIBLE);
        $hasMorePortfolios = $portfolios->count() > \App\Http\Controllers\HomeController::PORTFOLIO_VISIBLE;
    @endphp

    {{-- ========== 2. HERO (legacy hero_banner + the hardcoded h1/p/CTA) ========== --}}
    @if ($heroSlides->isNotEmpty())
        <section id="hero-banner"
                 x-data="carousel" data-interval="4000"
                 class="relative flex min-h-[80vh] items-end overflow-hidden bg-neutral-900 sm:min-h-[90vh]"
                 aria-roledescription="carousel" aria-label="{{ __('home.hero') }}">
            {{-- Legacy set the background image from JavaScript, so the browser's
                 preload scanner could not find the LCP image at all. It is a real
                 <img> here, and slide 1 is eager + high priority.

                 Slide 1 ships with .is-active from the server. Everything after
                 it is transparent via CSS ([data-slide] in app.css), so only one
                 hero image downloads up front and a JS failure degrades to
                 "slide 1 only" rather than five images stacked. --}}
            @foreach ($heroSlides as $i => $slide)
                <div data-slide @class(['absolute inset-0', 'is-active' => $i === 0])
                     role="group" aria-hidden="{{ $i === 0 ? 'false' : 'true' }}"
                     aria-label="{{ $i + 1 }} / {{ $heroSlides->count() }}">
                    @if ($slide->url)
                        <img src="{{ $slide->url }}" alt="{{ $slide->alt_text ?? '' }}"
                             loading="{{ $i === 0 ? 'eager' : 'lazy' }}"
                             fetchpriority="{{ $i === 0 ? 'high' : 'auto' }}"
                             decoding="async"
                             class="h-full w-full object-cover">
                    @endif
                    <div class="absolute inset-0 bg-gradient-to-t from-neutral-950 via-neutral-950/55 to-neutral-950/15"></div>
                </div>
            @endforeach

            <div class="relative mx-auto w-full max-w-7xl px-4 pb-16 pt-28 sm:px-6 sm:pb-20">
                @if ($heroHeading = $settings('hero_heading'))
                    <h1 class="max-w-3xl text-3xl font-bold leading-tight tracking-tight text-white sm:text-5xl">
                        {{ $heroHeading }}
                    </h1>
                @endif

                @if ($heroSub = $settings('hero_subheading'))
                    <p class="mt-4 max-w-2xl text-base text-neutral-200 sm:text-lg">{{ $heroSub }}</p>
                @endif

                {{-- Legacy CTA: "Booking Sekarang" -> wa.me, the primary action.
                     The secondary button previously opened the contact form;
                     D12 removed the form, so it now scrolls to the services
                     section rather than duplicating the WhatsApp link beside
                     itself. --}}
                <div class="mt-8 flex flex-wrap gap-3">
                    @if ($whatsapp)
                        <a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer"
                           class="rounded-lg bg-amber-500 px-6 py-3 text-sm font-semibold text-neutral-950
                                  transition hover:bg-amber-400">
                            {{ __('home.book_now') }}
                        </a>
                    @endif
                    <a href="#layanan"
                       class="rounded-lg border border-white/25 px-6 py-3 text-sm font-semibold
                              text-white transition hover:bg-white/10">
                        {{ __('home.free_consultation') }}
                    </a>
                </div>
            </div>

            @if ($heroSlides->count() > 1)
                <div class="absolute bottom-5 right-4 flex gap-2 sm:right-6">
                    @foreach ($heroSlides as $i => $slide)
                        <button type="button" data-dot
                                @class(['h-2 rounded-full', 'is-active' => $i === 0])
                                aria-current="{{ $i === 0 ? 'true' : 'false' }}"
                                aria-label="{{ __('home.go_to_slide', ['number' => $i + 1]) }}"></button>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    {{-- ========== 3. FEATURES (legacy features_section) ========== --}}
    {{-- Legacy: heading + subheading on the left, Projects/Clients counters on
         the right, and two buttons -- "Semua Desain" (#portofolio) and
         "Desain Terbaik" (detail.php of the top-liked property, which is the
         featured portfolio here). --}}
    @if ($settings('home_heading') || $settings('stat_projects') || $settings('stat_clients'))
        <section class="border-b border-white/10 bg-neutral-900/60">
            <div class="mx-auto grid max-w-7xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-[1.4fr_1fr] lg:items-center">
                <div>
                    @if ($heading = $settings('home_heading'))
                        <h2 class="text-2xl font-bold tracking-tight text-white sm:text-4xl">{{ $heading }}</h2>
                    @endif

                    @if ($sub = $settings('home_subheading'))
                        <p class="mt-4 max-w-xl text-base leading-relaxed text-neutral-400">{{ $sub }}</p>
                    @endif

                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="#portofolio"
                           class="rounded-lg bg-white px-8 py-3 text-sm font-medium text-neutral-950
                                  transition hover:bg-neutral-200">
                            {{ __('home.all_designs') }}
                        </a>

                        @if ($featured)
                            <a href="{{ $lroute('portfolio.show', ['portfolio' => $featured]) }}"
                               class="rounded-lg border-2 border-amber-400 px-8 py-3 text-sm font-medium
                                      text-white transition hover:bg-amber-400/10">
                                {{ __('home.best_design') }}
                            </a>
                        @endif
                    </div>
                </div>

                <dl class="grid grid-cols-2 gap-4">
                    <div class="rounded-xl border border-white/10 bg-neutral-950/40 p-6 text-center">
                        <dd class="text-3xl font-bold text-amber-400 sm:text-4xl">
                            {{ number_format((int) $settings('stat_projects', 0), 0, ',', '.') }}+
                        </dd>
                        <dt class="mt-1 text-sm text-neutral-400">{{ __('home.stat_projects') }}</dt>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-neutral-950/40 p-6 text-center">
                        <dd class="text-3xl font-bold text-amber-400 sm:text-4xl">
                            {{ number_format((int) $settings('stat_clients', 0), 0, ',', '.') }}+
                        </dd>
                        <dt class="mt-1 text-sm text-neutral-400">{{ __('home.stat_clients') }}</dt>
                    </div>
                </dl>
            </div>
        </section>
    @endif

    {{-- ========== 4. PORTFOLIO (legacy "Gallery" / #portofolio) ========== --}}
    @if ($featured || $portfolios->isNotEmpty())
        <section id="portofolio" class="mx-auto max-w-7xl scroll-mt-20 px-4 py-16 sm:px-6">
            <x-section-heading
                :eyebrow="__('home.portfolio_eyebrow')"
                :title="__('home.portfolio_title')" />

            {{-- Legacy showed 16 items then revealed the rest with a
                 "Selengkapnya" button that only un-hid already-downloaded
                 markup. Same visible limit, but the extra items are rendered
                 hidden and revealed by Alpine instead of inline JS. --}}
            <div x-data="revealMore" class="mt-12">
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    @if ($featured)
                        <x-portfolio-card :portfolio="$featured" featured />
                    @endif

                    @foreach ($visiblePortfolios as $portfolio)
                        <x-portfolio-card :portfolio="$portfolio" />
                    @endforeach

                    {{-- Rendered but hidden by CSS, revealed by the component on
                         click. A <template x-if> would need an expression the
                         CSP-safe Alpine build cannot evaluate. --}}
                    @if ($hasMorePortfolios)
                        @foreach ($portfolios->slice(\App\Http\Controllers\HomeController::PORTFOLIO_VISIBLE) as $portfolio)
                            <div data-reveal-item class="is-hidden contents">
                                <x-portfolio-card :portfolio="$portfolio" />
                            </div>
                        @endforeach
                    @endif
                </div>

                <div class="mt-10 flex flex-wrap justify-center gap-3">
                    {{-- hidden until the component confirms it can work, so a
                         no-JS visitor is not shown a dead control. --}}
                    @if ($hasMorePortfolios)
                        <button type="button" data-reveal-button hidden
                                class="rounded-lg border border-white/15 px-6 py-3 text-sm font-semibold
                                       text-neutral-200 transition hover:border-amber-500/50 hover:text-amber-400">
                            {{ __('home.load_more') }}
                        </button>
                    @endif

                    <a href="{{ $lroute('portfolio.index') }}"
                       class="rounded-lg border border-white/15 px-6 py-3 text-sm font-semibold
                              text-neutral-200 transition hover:border-amber-500/50 hover:text-amber-400">
                        {{ __('home.view_all_portfolio') }}
                    </a>
                </div>
            </div>
        </section>
    @endif

    {{-- ========== 5. TESTIMONIALS (legacy testimonial_section) ========== --}}
    @if ($highlightedTestimonials->isNotEmpty() || $testimonials->isNotEmpty())
        <section class="border-y border-white/10 bg-neutral-900/50">
            <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6">
                <x-section-heading
                    :eyebrow="__('home.testimonials_eyebrow')"
                    :title="__('home.testimonials_title')" />

                {{-- 2 highlighted across the top, exactly as legacy did. --}}
                @if ($highlightedTestimonials->isNotEmpty())
                    <div class="mt-12 grid gap-6 md:grid-cols-2">
                        @foreach ($highlightedTestimonials as $testimonial)
                            <x-video-facade :video="$testimonial" />
                        @endforeach
                    </div>
                @endif

                {{-- The rest in rows of 4 -- legacy array_chunk($others, 4). --}}
                @if ($testimonials->isNotEmpty())
                    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($testimonials as $testimonial)
                            <x-video-facade :video="$testimonial">
                                @if ($testimonial->rating)
                                    <div class="mt-2">
                                        <x-star-rating :rating="$testimonial->rating" />
                                    </div>
                                @endif
                            </x-video-facade>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- ========== 6. BENEFITS -- "What Do You Get?" 24-tile grid ========== --}}
    @if ($deliverables->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6">
            <x-section-heading :title="__('home.deliverables_title')" />

            <div class="mt-12 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($deliverables as $item)
                    <x-benefit-tile :item="$item" />
                @endforeach
            </div>

            {{-- Legacy: .booking-button-container -> "Booking Sekarang". --}}
            @if ($whatsapp)
                <div class="mt-12 text-center">
                    <a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer"
                       class="inline-block rounded-lg bg-amber-500 px-8 py-3 text-sm font-semibold
                              text-neutral-950 transition hover:bg-amber-400">
                        {{ __('home.book_now') }}
                    </a>
                </div>
            @endif
        </section>
    @endif

    {{-- ========== 7. REVIEW DESAIN (legacy review_design) ========== --}}
    @if ($designReviews->isNotEmpty())
        <section class="border-y border-white/10 bg-neutral-900/50">
            <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6">
                <x-section-heading
                    :eyebrow="__('home.reviews_eyebrow')"
                    :title="__('home.reviews_title')" />

                <div class="mt-12 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($designReviews as $review)
                        <x-video-facade :video="$review" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ========== 8. LOCATION (legacy location_section + office details) ========== --}}
    {{-- One office only, per your decision. Legacy's markup had two slider dots
         and script.js hardcoded three offices, two of them placeholder data
         ("Jl. Example Street", maps.app.goo.gl/example1) -- that slider is gone. --}}
    @if ($settings('location_office_label') || $settings('company_address') || $locationVideoId)
        <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6">
            <x-section-heading :title="__('home.location_title')" />

            <div class="mt-10 grid gap-8 lg:grid-cols-2 lg:items-center">
                <div>
                    @if ($officeLabel = $settings('location_office_label'))
                        <p class="flex items-start gap-2 text-sm font-semibold uppercase tracking-wider text-amber-500">
                            <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor"
                                 stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span>{{ $officeLabel }}</span>
                        </p>
                    @endif

                    @if ($address = $settings('company_address'))
                        <p class="mt-4 whitespace-pre-line text-base leading-relaxed text-neutral-300">{{ $address }}</p>
                    @endif

                    {{-- An anchor, not an iframe. Legacy's value is a goo.gl/maps
                         share link, and Google refuses to be framed from a
                         non-/maps/embed URL -- framing it rendered an empty box. --}}
                    @if ($mapsUrl = $settings('company_maps_url'))
                        <a href="{{ $mapsUrl }}" target="_blank" rel="noopener noreferrer"
                           class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-amber-400
                                  transition hover:text-amber-300">
                            <span>{{ __('home.view_on_maps') }}</span>
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"
                                 viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                        </a>
                    @endif
                </div>

                @if ($locationVideoId)
                    <x-video-facade :id="$locationVideoId" :title="__('home.location_title')" />
                            </template>
                        </div>
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- ========== 9. MENGAPA MEMILIH KAMI (legacy mengapa_section) ========== --}}
    @if ($settings('why_description') || $whyVideoId || $benefits->isNotEmpty() || $processes->isNotEmpty())
        <section id="tentang" class="border-y border-white/10 bg-neutral-900/50">
            <div class="mx-auto max-w-7xl scroll-mt-20 px-4 py-16 sm:px-6">
                <x-section-heading :title="__('home.why_title')" />

                @if ($whyVideoId)
                    <div class="mx-auto mt-10 max-w-4xl">
                        <x-video-facade :id="$whyVideoId" :title="__('home.why_title')" />
                    </div>
                @endif

                {{-- Legacy: description column, then list1, then list2 -- three
                     columns side by side. nl2br on the description is preserved
                     with whitespace-pre-line. --}}
                <div class="mt-12 grid gap-8 lg:grid-cols-3">
                    @if ($description = $settings('why_description'))
                        <div class="rounded-xl border border-white/10 bg-neutral-950/40 p-6">
                            <p class="whitespace-pre-line text-sm leading-relaxed text-neutral-300">{{ $description }}</p>
                        </div>
                    @endif

                    @foreach ([$benefits, $processes] as $column)
                        @if ($column->isNotEmpty())
                            <div class="rounded-xl border border-white/10 bg-neutral-950/40 p-6">
                                <ul class="space-y-3">
                                    @foreach ($column as $item)
                                        <li class="flex gap-3 text-sm text-neutral-300">
                                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" fill="none"
                                                 stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                                                 aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
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
        </section>
    @endif

    {{-- ========== 10. SERVICES (legacy layanan_section / #layanan) ========== --}}
    @if ($services->isNotEmpty())
        <section id="layanan" class="mx-auto max-w-7xl scroll-mt-20 px-4 py-16 sm:px-6">
            <x-section-heading
                :eyebrow="__('home.services_eyebrow')"
                :title="__('home.services_legacy_title')" />

            <div class="mt-12 grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                @foreach ($services as $service)
                    <x-service-card :service="$service" :whatsapp="$whatsapp" />
                @endforeach
            </div>

            {{-- Legacy: .cta-container -> "Konsultasi Sekarang". --}}
            @if ($whatsapp)
                <div class="mt-12 text-center">
                    <a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer"
                       class="inline-block rounded-lg bg-amber-500 px-8 py-3 text-sm font-semibold
                              text-neutral-950 transition hover:bg-amber-400">
                        {{ __('home.consult_now') }}
                    </a>
                </div>
            @endif
        </section>
    @endif

    {{-- ========== 11. ORDER FLOW (legacy alur_section) ========== --}}
    @if ($processLink = $settings('process_link_url'))
        @php
            // Legacy's <img src="path-to-your-thumbnail.jpg"> was an unreplaced
            // placeholder that 404'd. If a real thumbnail has been set, use it;
            // otherwise derive one from the linked YouTube video; otherwise fall
            // back to a plain CTA card rather than a broken image.
            $processThumb = $settings('process_thumbnail');
            $processVideoId = \App\Models\DesignReview::extractYoutubeId($processLink);
            $processThumbUrl = $processThumb
                ? (str_starts_with($processThumb, 'http') ? $processThumb : asset($processThumb))
                : ($processVideoId ? 'https://i.ytimg.com/vi/'.$processVideoId.'/maxresdefault.jpg' : null);
        @endphp

        <section id="informasi" class="mx-auto max-w-7xl scroll-mt-20 px-4 py-16 sm:px-6">
            <x-section-heading :title="__('home.process_title')" />

            <a href="{{ $processLink }}" target="_blank" rel="noopener noreferrer"
               class="group mx-auto mt-10 block max-w-4xl overflow-hidden rounded-xl border border-white/10
                      bg-neutral-900 transition hover:border-amber-500/40">
                @if ($processThumbUrl)
                    <div class="relative aspect-video">
                        <img src="{{ $processThumbUrl }}" alt="{{ __('home.process_title') }}"
                             loading="lazy" decoding="async"
                             class="h-full w-full object-cover">
                        <div class="absolute inset-0 flex items-center justify-center bg-neutral-950/35
                                    transition group-hover:bg-neutral-950/20">
                            <span class="flex h-16 w-16 items-center justify-center rounded-full
                                         bg-neutral-950/70 text-white ring-1 ring-white/20">
                                <svg class="ml-1 h-7 w-7" fill="currentColor" viewBox="0 0 24 24"
                                     aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                            </span>
                        </div>
                    </div>
                @else
                    <div class="p-10 text-center">
                        <span class="inline-block rounded-lg bg-amber-500 px-6 py-3 text-sm font-semibold
                                     text-neutral-950">
                            {{ __('home.view_process') }}
                        </span>
                    </div>
                @endif
            </a>
        </section>
    @endif

    {{-- ========== 12. FAQ (legacy: 10 hardcoded .faq-item blocks) ========== --}}
    @if ($faqs->isNotEmpty())
        <section class="border-t border-white/10 bg-neutral-900/50">
            <div class="mx-auto max-w-4xl px-4 py-16 sm:px-6">
                <x-section-heading :title="__('home.faq_title')" />

                <div class="mt-12">
                    <x-faq-accordion :faqs="$faqs" />
                </div>

                {{-- Legacy: .learn-more-container -> "Konsultasi Sekarang". --}}
                @if ($whatsapp)
                    <div class="mt-10 text-center">
                        <a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer"
                           class="inline-block rounded-lg bg-amber-500 px-8 py-3 text-sm font-semibold
                                  text-neutral-950 transition hover:bg-amber-400">
                            {{ __('home.consult_now') }}
                        </a>
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- ========== 13. FLOATING WHATSAPP ========== --}}
    @if ($whatsapp)
        <x-floating-whatsapp :href="$whatsapp" />
    @endif
@endsection
