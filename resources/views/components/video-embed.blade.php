@props(['video', 'title' => null, 'eager' => false])

@if ($video->youtube_id)
    {{--
        Legacy built these iframes by string concatenation inside an echo, parsing
        the YouTube URL on every loop iteration. The id is now resolved once on
        save. youtube-nocookie.com is used so an embed does not set tracking
        cookies before the visitor interacts.
    --}}
    <div class="overflow-hidden rounded-xl border border-white/10 bg-neutral-900">
        <div class="relative aspect-video">
            <iframe src="{{ $video->embed_url }}"
                    title="{{ $title ?? $video->title ?? __('home.video') }}"
                    loading="{{ $eager ? 'eager' : 'lazy' }}"
                    class="absolute inset-0 h-full w-full"
                    referrerpolicy="strict-origin-when-cross-origin"
                    allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen></iframe>
        </div>

        @if ($video->title || $slot->isNotEmpty())
            <div class="p-4">
                @if ($video->title)
                    <h3 class="text-sm font-semibold text-white">{{ $video->title }}</h3>
                @endif
                {{ $slot }}
            </div>
        @endif
    </div>
@endif
