@props(['href' => null])
@php
    $targetUrl = $href ?? $settings('company_whatsapp', 'https://wa.me/6282338389488');
@endphp

@if ($targetUrl)
    <a href="{{ $targetUrl }}"
       target="_blank"
       rel="noopener noreferrer"
       aria-label="WhatsApp"
       class="floating-whatsapp">
        <img src="{{ asset('images/icons/ICS.png') }}" alt="WhatsApp" width="36" height="36" style="object-fit: contain;">
    </a>
@endif

