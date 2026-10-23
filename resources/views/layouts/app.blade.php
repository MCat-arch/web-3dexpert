{{--
    Replaces partials/header.php + partials/footer.php (655 lines combined).

    Legacy duplicated the entire homepage into index-en.php for English. Here
    the layout is shared and the language comes from the locale, so there is one
    set of views.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', $settings('seo_title') ?? config('app.name'))</title>
    <meta name="description" content="@yield('description', $settings('seo_description'))">

    <link rel="canonical" href="{{ url()->current() }}">

    {{-- Tells Google these are translations of one page, rather than
         duplicate content -- which is how legacy's index.php and
         index-en.php would have looked to a crawler. --}}
    @foreach ($availableLocales as $code => $locale)
        <link rel="alternate" hreflang="{{ $code }}"
              href="{{ \App\Support\Locale::urlFor($code) }}">
    @endforeach
    <link rel="alternate" hreflang="x-default"
          href="{{ \App\Support\Locale::urlFor(config('site.default_locale')) }}">

    {{-- Open Graph --}}
    <meta property="og:type" content="website">
    <meta property="og:title" content="@yield('title', $settings('seo_title') ?? config('app.name'))">
    <meta property="og:description" content="@yield('description', $settings('seo_description'))">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:locale" content="{{ app()->getLocale() }}">
    @hasSection('og_image')
        <meta property="og:image" content="@yield('og_image')">
    @endif
    <meta name="twitter:card" content="summary_large_image">

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <meta name="theme-color" content="#111111">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400;1,600&display=swap"
          rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Structured data, so the firm can appear as a business rather than a
         generic page. Legacy had none. --}}
    <!-- @php
        $structuredData = [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => $settings('company_name', config('app.name')),
            'url' => url('/'),
            'email' => $settings('company_email'),
            'telephone' => $settings('company_phone'),
            'address' => $settings('company_address'),
            'sameAs' => array_values(array_filter([
                $settings('company_instagram'),
                $settings('company_youtube'),
            ])),
        ];
    @endphp
    <script type="application/ld+json">
        @json($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG)
    </script> -->

    @stack('head')
</head>
<body class="site-body">
    <!-- <a href="#main" class="skip-link">
        {{ __('nav.skip_to_content') }}
    </a> -->

    @include('partials.header')

    <main id="main">
        @yield('content')
    </main>

    @include('partials.footer')

    @stack('scripts')
</body>
</html>
