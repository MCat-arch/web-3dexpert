@props(['rating' => 0, 'maximum' => 5])

<div class="flex items-center gap-0.5" aria-label="{{ $rating }} / {{ $maximum }}">
    @for ($star = 1; $star <= $maximum; $star++)
        <svg class="h-4 w-4 {{ $star <= (int) $rating ? 'text-amber-400' : 'text-neutral-700' }}"
             viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="m10 1.5 2.63 5.33 5.88.85-4.25 4.14 1 5.85L10 14.91l-5.26 2.76 1-5.85L1.5 7.68l5.88-.85L10 1.5Z"/>
        </svg>
    @endfor
    <span class="sr-only">{{ $rating }} / {{ $maximum }}</span>
</div>
