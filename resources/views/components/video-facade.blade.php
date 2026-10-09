@props([
    'video' => null,      // any model using HasYoutubeVideo
    'id' => null,         // ...or a bare YouTube id, for settings-driven videos
    'title' => null,
    'aspect' => null,
])

@php
    $videoId = $id ?: $video?->youtube_id;
    $thumbnail = $video?->thumbnail_url
        ?: ($videoId ? 'https://i.ytimg.com/vi/'.$videoId.'/hqdefault.jpg' : null);
    $label = $title ?? $video?->title ?? __('home.video');
@endphp

@if ($videoId)
    <div class="video-facade-card">
        <div x-data="ytFacade"
             data-video-id="{{ $videoId }}"
             data-video-title="{{ $label }}"
             class="relative w-full h-full"
             style="cursor: pointer; position: relative; width: 100%; height: 100%; min-height: 200px;"
             role="button" tabindex="0"
             aria-label="{{ __('home.play_video') }}">
            <img src="{{ $thumbnail }}" alt=""
                 loading="lazy" decoding="async" width="480" height="360"
                 class="w-full h-full"
                 style="object-fit: cover; width: 100%; height: 100%; display: block;">
            <div class="video-facade-play">
                <span class="video-play-btn">
                    <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true" style="margin-left: 2px;">
                        <path d="M8 5v14l11-7z"/>
                    </svg>
                </span>
            </div>
        </div>

        @if ($video?->title || $slot->isNotEmpty())
            <div style="padding: 14px 18px; background: var(--ink-800); border-top: 1px solid var(--line-dark);">
                @if ($video?->title)
                    <h3 style="font-size: 0.95rem; font-weight: 600; color: #ffffff;">{{ $video->title }}</h3>
                @endif
                {{ $slot }}
            </div>
        @endif
    </div>
@endif
