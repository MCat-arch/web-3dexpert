@props(['faqs'])

{{--
    3D Expert Web FAQ Accordion (Semantic Plain CSS per docs/design.md §4)
    CSP NOTE: bare x-data="faqAccordion"
--}}
<div x-data="faqAccordion" class="faq-list">
    @foreach ($faqs as $faq)
        <details name="faq" data-faq class="faq-item">
            <summary class="faq-summary">
                <span>{{ $faq->question }}</span>
                <svg class="faq-chevron" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </summary>

            <div class="faq-content">
                {{ $faq->answer }}
            </div>
        </details>
    @endforeach
</div>
