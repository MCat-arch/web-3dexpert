@props(['item'])

<article class="flex min-h-40 flex-col items-center justify-center rounded-xl border border-white/10 bg-neutral-900 p-5 text-center">
    @if ($item->icon_path)
        <img src="{{ route('media.show', ['path' => $item->icon_path]) }}"
             alt="{{ $item->label }}"
             loading="lazy"
             class="h-14 w-14 object-contain"
             width="56"
             height="56">
    @endif
    <h3 class="mt-4 text-sm font-semibold text-neutral-200">{{ $item->label }}</h3>
</article>
