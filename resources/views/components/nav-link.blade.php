@props(['active' => false, 'mobile' => false])

<a {{ $attributes->merge([
    'class' => $mobile
        ? 'block rounded-lg px-3 py-2 text-base font-medium transition '
            . ($active ? 'bg-amber-500/10 text-amber-400' : 'text-neutral-300 hover:bg-white/5')
        : 'rounded-lg px-3 py-2 text-sm font-medium transition '
            . ($active ? 'text-amber-400' : 'text-neutral-300 hover:text-white'),
]) }}
   @if ($active) aria-current="page" @endif>
    {{ $slot }}
</a>
