@props(['title', 'description' => null])

<div class="rounded-xl border border-dashed border-white/15 px-6 py-16 text-center">
    <h3 class="text-base font-semibold text-neutral-300">{{ $title }}</h3>
    @if ($description)
        <p class="mx-auto mt-2 max-w-md text-sm text-neutral-500">{{ $description }}</p>
    @endif
    {{ $slot }}
</div>
