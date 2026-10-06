{{-- Replaces legacy's hardcoded index.php <-> index-en.php link, which only
     existed on the homepage. This works on every page. --}}
<div class="flex items-center gap-1 rounded-lg border border-white/10 p-0.5">
    @foreach ($availableLocales as $code => $locale)
        <a href="{{ \App\Support\Locale::urlFor($code) }}"
           hreflang="{{ $code }}"
           lang="{{ $code }}"
           @class([
               'rounded-md px-2 py-1 text-xs font-semibold uppercase transition',
               'bg-amber-500 text-neutral-950' => $code === $currentLocale,
               'text-neutral-400 hover:text-white' => $code !== $currentLocale,
           ])
           @if ($code === $currentLocale) aria-current="true" @endif
           title="{{ $locale['native'] }}">
            {{ $code }}
        </a>
    @endforeach
</div>
