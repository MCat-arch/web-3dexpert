@props(['title', 'description' => null])

<div class="empty-state">
    <div class="empty-state-icon">
        <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true" style="margin: 0 auto; display: block;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0l-3-3m3 3l3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
        </svg>
    </div>
    <h3 class="empty-state-title">{{ $title }}</h3>
    @if ($description)
        <p class="empty-state-desc">{{ $description }}</p>
    @endif
    {{ $slot }}
</div>
