@props(['faqs'])

{{--
    Legacy FAQ accordion (index.php:755-865 + the script at 873-913).

    Behaviour preserved:
      - only one item open at a time
      - clicking inside the section but outside an item closes all of them

    What changed, and why it is better than legacy AND better than the Alpine
    version this replaces:

    This is now native <details name="faq">. The browser gives us exclusive
    opening (that is what the shared name does), full keyboard support, and
    correct screen-reader semantics -- and it all works with JavaScript
    disabled, which neither legacy's onclick="toggleFAQ(this)" nor an
    x-show accordion could manage.

    JavaScript is only needed for legacy's extra touch (an outside click closes
    everything) and as an exclusivity fallback for browsers that do not yet
    support the name attribute. That lives in the faqAccordion component, which
    needs no Alpine expressions -- required, because SecurityHeaders.php sends
    script-src without 'unsafe-eval' and the CSP-safe Alpine build cannot
    evaluate them.
--}}
<div x-data="faqAccordion" class="space-y-3">
    @foreach ($faqs as $faq)
        <details name="faq" data-faq
                 class="overflow-hidden rounded-xl border border-white/10 bg-neutral-900">
            <summary class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left">
                <span class="text-sm font-semibold text-white sm:text-base">
                    {{ $faq->question }}
                </span>
                <svg class="faq-chevron h-5 w-5 shrink-0 text-amber-500"
                     fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                     aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </summary>

            <div class="px-5 pb-5 text-sm leading-relaxed text-neutral-400">
                {{ $faq->answer }}
            </div>
        </details>
    @endforeach
</div>
