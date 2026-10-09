<div style="display: inline-flex; align-items: center; gap: 4px; border: 1px solid var(--line-dark); border-radius: var(--radius-btn); padding: 2px 4px; background: var(--ink-700);">
    @foreach ($availableLocales as $code => $locale)
        <a href="{{ \App\Support\Locale::urlFor($code) }}"
           hreflang="{{ $code }}"
           lang="{{ $code }}"
           class="lang-btn {{ $code === $currentLocale ? 'is-active' : '' }}"
           style="padding: 4px 8px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; border-radius: 3px; text-decoration: none; transition: all var(--transition-fast); {{ $code === $currentLocale ? 'background: #444444; color: var(--gold);' : 'color: var(--on-dark-2);' }}"
           @if ($code === $currentLocale) aria-current="true" @endif
           title="{{ $locale['native'] }}">
            {{ $code }}
        </a>
    @endforeach
</div>
