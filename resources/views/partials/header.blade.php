{{--
    Public navigation stays within the landing page. Each section link works
    from either supported locale without introducing additional public pages.

    CSP NOTE: the only Alpine attribute here is x-data="navMenu". The menu's
    open/close state is a class the component toggles (see resources/js/app.js
    and the [data-nav-panel] rules in app.css), because the CSP-safe Alpine
    build cannot evaluate expressions like @click="open = !open".
--}}
@php
    $home = route('home');
    $sections = [
        ['label' => __('nav.portfolio'),        'href' => $home.'#portofolio'],
        ['label' => __('nav.services_prices'),  'href' => $home.'#layanan'],
        ['label' => __('nav.about'),            'href' => $home.'#tentang'],
        ['label' => __('nav.information'),      'href' => $home.'#informasi'],
    ];
@endphp

<header x-data="navMenu"
        class="sticky top-0 z-40 border-b border-white/10 bg-neutral-950/90 backdrop-blur">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
        <a href="{{ $home }}" class="flex shrink-0 items-center gap-2">
            {{-- Legacy logo.png is 2893x915 (458 KB) rendered 36px tall. The
                 explicit dimensions stop it shifting the layout; replacing the
                 file with a right-sized one is a separate asset task. --}}
            <img src="{{ route('media.show', ['path' => 'images/logo.png']) }}"
                 alt="{{ $settings('company_name', config('app.name')) }}"
                 class="h-9 w-auto" width="113" height="36" fetchpriority="high">
            <span class="text-base font-semibold tracking-tight">
                {{ $settings('company_name', config('app.name')) }}
            </span>
        </a>

        <nav class="hidden items-center gap-1 lg:flex" aria-label="{{ __('nav.main') }}">
            @foreach ($sections as $section)
                <x-nav-link :href="$section['href']">{{ $section['label'] }}</x-nav-link>
            @endforeach

        </nav>

        <div class="flex items-center gap-2">
            <x-language-switcher />

            {{-- Legacy .contact-button -> "Konsultasi" -> wa.me --}}
            @if ($whatsapp = $settings('company_whatsapp'))
                <a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer"
                   class="hidden rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold
                          text-neutral-950 transition hover:bg-amber-400 sm:inline-block">
                    {{ __('nav.consultation') }}
                </a>
            @endif

            <button type="button" data-nav-toggle
                    class="rounded-lg p-2 text-neutral-300 hover:bg-white/10 lg:hidden"
                    aria-expanded="false" aria-controls="mobile-nav"
                    aria-label="{{ __('nav.toggle_menu') }}">
                <svg class="icon-open h-6 w-6" fill="none" stroke="currentColor" stroke-width="2"
                     viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
                <svg class="icon-close h-6 w-6" fill="none" stroke="currentColor" stroke-width="2"
                     viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Collapsed by default via CSS (grid-template-rows: 0fr), so it is shut
         before Alpine boots and stays shut if JS never runs. --}}
    <nav id="mobile-nav" data-nav-panel class="border-t border-white/10 lg:hidden"
         aria-label="{{ __('nav.main') }}">
        <div class="space-y-1 px-4 py-3">
            @foreach ($sections as $section)
                <x-nav-link :href="$section['href']" mobile>{{ $section['label'] }}</x-nav-link>
            @endforeach

            @if ($whatsapp = $settings('company_whatsapp'))
                <a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer"
                   class="mt-2 block rounded-lg bg-amber-500 px-4 py-2 text-center text-sm
                          font-semibold text-neutral-950">
                    {{ __('nav.consultation') }}
                </a>
            @endif
        </div>
    </nav>
</header>
