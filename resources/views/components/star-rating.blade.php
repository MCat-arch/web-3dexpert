@props(['rating' => 0, 'maximum' => 5])

<div style="display: inline-flex; align-items: center; gap: 2px;" aria-label="{{ $rating }} / {{ $maximum }}">
    @for ($star = 1; $star <= $maximum; $star++)
        <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"
             style="color: {{ $star <= (int) $rating ? 'var(--gold)' : 'var(--line-dark)' }};">
            <path d="m10 1.5 2.63 5.33 5.88.85-4.25 4.14 1 5.85L10 14.91l-5.26 2.76 1-5.85L1.5 7.68l5.88-.85L10 1.5Z"/>
        </svg>
    @endfor
    <span class="sr-only">{{ $rating }} / {{ $maximum }}</span>
</div>
