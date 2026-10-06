@props(['eyebrow' => null, 'title', 'subtitle' => null, 'align' => 'center'])

<div @class([
    'max-w-3xl',
    'mx-auto text-center' => $align === 'center',
])>
    @if ($eyebrow)
        <p class="text-sm font-semibold uppercase tracking-widest text-amber-500">{{ $eyebrow }}</p>
    @endif

    <h2 class="mt-2 text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ $title }}</h2>

    @if ($subtitle)
        <p class="mt-4 text-base leading-relaxed text-neutral-400">{{ $subtitle }}</p>
    @endif
</div>
