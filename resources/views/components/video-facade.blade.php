@props([
    'video' => null,      // any model using HasYoutubeVideo
    'id' => null,         // ...or a bare YouTube id, for settings-driven videos
    'title' => null,
    'aspect' => 'aspect-video',
])

@php
    $videoId = $id ?: $video?->youtube_id;
    $thumbnail = $video?->thumbnail_url
        ?: ($videoId ? 'https://i.ytimg.com/vi/'.$videoId.'/hqdefault.jpg' : null);
    $label = $title ?? $video?->title ?? __('home.video');
@endphp

{{--
    Click-to-load YouTube embed.

    Legacy rendered 15 live <iframe> elements on the homepage (10 testimonials,
    3 design reviews, 1 location, 1 "mengapa"), none of them lazy. Each embed is
    a separate browsing context with its own JavaScript runtime, so the page
    arrived with 15 of them competing for the main thread and for the browser's
    ~6-connections-per-host budget -- which is why it froze and why navigating
    away was blocked.

    Here each one is a static thumbnail (~15-30 KB from i.ytimg.com) and the
    iframe is created only when the visitor clicks. The thumbnail is the
    official hqdefault image, so nothing is fabricated.

    CSP NOTE: the only Alpine attribute is x-data="ytFacade"; the video id and
    title are passed as data-* attributes and the click/keyboard listeners are
    attached by the component, because the CSP-safe Alpine build cannot
    evaluate @click="load()" or :aria-label="...".
--}}
@if ($videoId)
    <div class="overflow-hidden rounded-xl border border-white/10 bg-neutral-900">
        <div x-data="ytFacade"
             data-video-id="{{ $videoId }}"
             data-video-title="{{ $label }}"
             class="relative {{ $aspect }} w-full cursor-pointer bg-neutral-950"
             role="button" tabindex="0"
             aria-label="{{ __('home.play_video') }}">
            <img src="{{ $thumbnail }}" alt=""
                 loading="lazy" decoding="async" width="480" height="360"
                 class="h-full w-full object-cover">
            <div class="pointer-events-none absolute inset-0 flex items-center justify-center
                        bg-neutral-950/35 transition hover:bg-neutral-950/20">
                <span class="flex h-16 w-16 items-center justify-center rounded-full
                             bg-neutral-950/70 text-white ring-1 ring-white/20">
                    <svg class="ml-1 h-7 w-7" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M8 5v14l11-7z"/>
                    </svg>
                </span>
            </div>
        </div>

        @if ($video?->title || $slot->isNotEmpty())
            <div class="p-4">
                @if ($video?->title)
                    <h3 class="text-sm font-semibold text-white">{{ $video->title }}</h3>
                @endif
                {{ $slot }}
            </div>
        @endif
    </div>
@endif
