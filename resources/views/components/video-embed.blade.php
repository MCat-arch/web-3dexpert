@props(['video' => null, 'youtubeId' => null, 'title' => null, 'eager' => false])

@php
    $ytId = $youtubeId ?? (is_object($video) ? $video?->youtube_id : $video);
    $embedUrl = $ytId ? 'https://www.youtube-nocookie.com/embed/'.$ytId : null;
    $vTitle = $title ?? (is_object($video) ? $video?->title : null) ?? __('home.video');
@endphp

@if ($ytId)
    <div style="border-radius: var(--radius-card); overflow: hidden; border: 1px solid var(--line-dark); background: var(--ink-900);">
        <div style="position: relative; width: 100%; aspect-ratio: 16 / 9;">
            <iframe src="{{ $embedUrl }}"
                    title="{{ $vTitle }}"
                    loading="{{ $eager ? 'eager' : 'lazy' }}"
                    style="position: absolute; inset: 0; width: 100%; height: 100%; border: none;"
                    referrerpolicy="strict-origin-when-cross-origin"
                    allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen></iframe>
        </div>

        @if ((is_object($video) && $video?->title) || $slot->isNotEmpty())
            <div style="padding: 14px 18px; background: var(--ink-800); border-top: 1px solid var(--line-dark);">
                @if (is_object($video) && $video?->title)
                    <h3 style="font-size: 0.95rem; font-weight: 600; color: #ffffff;">{{ $video->title }}</h3>
                @endif
                {{ $slot }}
            </div>
        @endif
    </div>
@endif
