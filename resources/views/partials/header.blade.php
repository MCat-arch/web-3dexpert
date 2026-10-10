{{--
    3D Expert Web Header (Semantic Plain CSS per docs/design.md)
    CSP NOTE: bare x-data="navMenu". Open/close states are CSS classes toggled by app.js.
--}}
@php
    $home = $lroute('home');
    $whatsapp = $settings('company_whatsapp', 'https://wa.me/6282338389488');
    $sections = [
        ['label' => __('nav.portfolio'),        'href' => $home.'#portofolio', 'active' => request()->routeIs('*portfolio*')],
        ['label' => __('nav.services_prices'),  'href' => $lroute('services.index'), 'active' => request()->routeIs('*services*')],
    ];
@endphp

<header x-data="navMenu" class="site-header">
    <div class="container header-container">
        <a href="{{ $home }}" class="site-logo">
            <img src="{{ asset('images/logo.png') }}"
                 alt="{{ $settings('company_name', config('app.name')) }}"
                 width="113" height="36" fetchpriority="high">
        </a>

        <nav class="nav-desktop" aria-label="{{ __('nav.main') }}">
            @foreach ($sections as $section)
                <x-nav-link :href="$section['href']" :active="$section['active']">{{ $section['label'] }}</x-nav-link>
            @endforeach

            <x-nav-link :href="$lroute('events.index')" :active="request()->routeIs('*events*')">
                {{ __('nav.events') }}
            </x-nav-link>

            <x-nav-link :href="$lroute('courses.index')" :active="request()->routeIs('*courses*')">
                {{ __('nav.courses') }}
            </x-nav-link>

            <x-nav-link :href="$lroute('careers.index')" :active="request()->routeIs('*careers*')">
                {{ __('nav.careers') }}
            </x-nav-link>

        </nav>

        <div class="header-actions">
            <x-language-switcher />

            @if ($whatsapp)
                <a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-sm">
                    {{ __('nav.consultation') }}
                </a>
            @endif

            <button type="button" data-nav-toggle
                    class="mobile-toggle"
                    aria-expanded="false" aria-controls="mobile-nav"
                    aria-label="{{ __('nav.toggle_menu') }}">
                <svg class="icon-open" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"
                     viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
                <svg class="icon-close" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"
                     viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Mobile Dropdown Accordion Panel --}}
    <nav id="mobile-nav" data-nav-panel aria-label="{{ __('nav.main') }}">
        <div style="padding: 16px 20px; display: flex; flex-direction: column; gap: 8px; background: var(--ink-900); border-top: 1px solid var(--line-dark);">
            @foreach ($sections as $section)
                <x-nav-link :href="$section['href']" :active="$section['active']" mobile>{{ $section['label'] }}</x-nav-link>
            @endforeach

            <x-nav-link :href="$lroute('events.index')" :active="request()->routeIs('*events*')" mobile>
                {{ __('nav.events') }}
            </x-nav-link>

            <x-nav-link :href="$lroute('courses.index')" :active="request()->routeIs('*courses*')" mobile>
                {{ __('nav.courses') }}
            </x-nav-link>

            <x-nav-link :href="$lroute('careers.index')" :active="request()->routeIs('*careers*')" mobile>
                {{ __('nav.careers') }}
            </x-nav-link>

            @if ($whatsapp)
                <a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer"
                   class="btn btn-primary btn-block" style="margin-top: 10px;">
                    {{ __('nav.consultation') }}
                </a>
            @endif
        </div>
    </nav>
</header>