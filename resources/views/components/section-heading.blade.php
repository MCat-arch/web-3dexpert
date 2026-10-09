@props([
    'title' => '',
    'eyebrow' => null,
    'subtitle' => null,
    'align' => 'center',
])

@php
    // Memastikan nilai align valid, default ke 'center' jika input tidak dikenal
    $textAlign = in_array($align, ['left', 'center', 'right', 'justify']) ? $align : 'center';
@endphp

<div {{ $attributes->merge(['class' => 'section-heading', 'style' => "text-align: {$textAlign};"]) }}>
    @if ($eyebrow)
        <span class="section-eyebrow">{{ $eyebrow }}</span>
    @endif

    <h2 class="section-title">{{ $title }}</h2>

    @if ($subtitle)
        <p class="section-subtitle">{{ $subtitle }}</p>
    @endif
</div>