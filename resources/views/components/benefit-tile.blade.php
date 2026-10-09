@props(['item'])

@php
    $iconUrl = null;
    if ($item->icon_path) {
        if (str_starts_with($item->icon_path, 'http://') || str_starts_with($item->icon_path, 'https://')) {
            $iconUrl = $item->icon_path;
        } elseif (file_exists(public_path($item->icon_path))) {
            $iconUrl = asset($item->icon_path);
        } elseif (file_exists(public_path('images/icons/' . basename($item->icon_path)))) {
            $iconUrl = asset('images/icons/' . basename($item->icon_path));
        } else {
            $iconUrl = route('media.show', ['path' => $item->icon_path]);
        }
    }
@endphp

<article class="benefit-tile">
    @if ($iconUrl)
        <div class="benefit-tile-icon">
            <img src="{{ $iconUrl }}"
                 alt="{{ $item->label }}"
                 loading="lazy"
                 width="40"
                 height="40"
                 style="width: 40px; height: 40px; object-fit: contain;">
        </div>
    @endif
    <h3 class="benefit-tile-label">{{ $item->label }}</h3>
</article>
