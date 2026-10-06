@props(['href'])

<a href="{{ $href }}"
   target="_blank"
   rel="noopener noreferrer"
   aria-label="WhatsApp"
   class="fixed bottom-5 right-5 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-500 shadow-lg shadow-black/30 transition hover:bg-emerald-400 focus-visible:outline-emerald-300">
    <img src="{{ route('media.show', ['path' => 'images/icons/ICS.png']) }}"
         alt=""
         width="28"
         height="28"
         class="h-7 w-7 object-contain">
</a>
