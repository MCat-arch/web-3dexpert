{{--
    Legacy hardcoded every contact detail into partials/footer.php, so changing a
    phone number meant editing PHP. These now come from site_settings.
--}}
<footer id="kontak" class="mt-20 border-t border-white/10 bg-neutral-900">
    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-4">
        <div class="lg:col-span-2">
            <div class="flex items-center gap-2">
                <img src="{{ route('media.show', ['path' => 'images/logo.png']) }}" alt="" class="h-8 w-auto" width="32" height="32">
                <span class="text-lg font-semibold">{{ $settings('company_name', config('app.name')) }}</span>
            </div>

            @if ($address = $settings('company_address'))
                <p class="mt-4 max-w-sm text-sm leading-relaxed text-neutral-400">{{ $address }}</p>
            @endif

            <div class="mt-5 flex gap-3">
                @if ($ig = $settings('company_instagram'))
                    <a href="{{ $ig }}" target="_blank" rel="noopener noreferrer"
                       class="rounded-lg border border-white/10 p-2 text-neutral-400 transition
                              hover:border-amber-500/50 hover:text-amber-400" aria-label="Instagram">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 2.2c3.2 0 3.6 0 4.9.07 1.2.06 1.8.25 2.2.42.6.22 1 .5 1.4.9.4.4.7.8.9 1.4.17.4.36 1 .42 2.2.07 1.3.07 1.7.07 4.9s0 3.6-.07 4.9c-.06 1.2-.25 1.8-.42 2.2a3.8 3.8 0 0 1-.9 1.4c-.4.4-.8.7-1.4.9-.4.17-1 .36-2.2.42-1.3.07-1.7.07-4.9.07s-3.6 0-4.9-.07c-1.2-.06-1.8-.25-2.2-.42a3.8 3.8 0 0 1-1.4-.9 3.8 3.8 0 0 1-.9-1.4c-.17-.4-.36-1-.42-2.2C2.2 15.6 2.2 15.2 2.2 12s0-3.6.07-4.9c.06-1.2.25-1.8.42-2.2.22-.6.5-1 .9-1.4.4-.4.8-.7 1.4-.9.4-.17 1-.36 2.2-.42C8.4 2.2 8.8 2.2 12 2.2Zm0 5.3a4.5 4.5 0 1 0 0 9 4.5 4.5 0 0 0 0-9Zm0 7.4a2.9 2.9 0 1 1 0-5.8 2.9 2.9 0 0 1 0 5.8Zm5.7-7.6a1.05 1.05 0 1 1-2.1 0 1.05 1.05 0 0 1 2.1 0Z"/>
                        </svg>
                    </a>
                @endif
                @if ($yt = $settings('company_youtube'))
                    <a href="{{ $yt }}" target="_blank" rel="noopener noreferrer"
                       class="rounded-lg border border-white/10 p-2 text-neutral-400 transition
                              hover:border-amber-500/50 hover:text-amber-400" aria-label="YouTube">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M23 12s0-3.9-.5-5.8a3 3 0 0 0-2.1-2.1C18.5 3.6 12 3.6 12 3.6s-6.5 0-8.4.5A3 3 0 0 0 1.5 6.2C1 8.1 1 12 1 12s0 3.9.5 5.8a3 3 0 0 0 2.1 2.1c1.9.5 8.4.5 8.4.5s6.5 0 8.4-.5a3 3 0 0 0 2.1-2.1C23 15.9 23 12 23 12ZM9.8 15.6V8.4l6.2 3.6-6.2 3.6Z"/>
                        </svg>
                    </a>
                @endif
            </div>
        </div>

        <div>
            <h2 class="text-sm font-semibold uppercase tracking-wider text-neutral-500">
                {{ __('nav.navigation') }}
            </h2>
            <ul class="mt-4 space-y-2 text-sm">
                <li><a href="{{ route('home') }}" class="text-neutral-400 hover:text-amber-400">{{ __('nav.home') }}</a></li>
                <li><a href="{{ route('home') }}#portofolio" class="text-neutral-400 hover:text-amber-400">{{ __('nav.portfolio') }}</a></li>
                <li><a href="{{ route('home') }}#layanan" class="text-neutral-400 hover:text-amber-400">{{ __('nav.services') }}</a></li>
                <li><a href="{{ route('home') }}#kontak" class="text-neutral-400 hover:text-amber-400">{{ __('nav.contact') }}</a></li>
            </ul>
        </div>

        <div>
            <h2 class="text-sm font-semibold uppercase tracking-wider text-neutral-500">
                {{ __('nav.contact') }}
            </h2>
            <ul class="mt-4 space-y-2 text-sm text-neutral-400">
                @if ($phone = $settings('company_phone'))
                    <li><a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="hover:text-amber-400">{{ $phone }}</a></li>
                @endif
                @if ($email = $settings('company_email'))
                    <li><a href="mailto:{{ $email }}" class="hover:text-amber-400">{{ $email }}</a></li>
                @endif
                @if ($wa = $settings('company_whatsapp'))
                    <li><a href="{{ $wa }}" target="_blank" rel="noopener noreferrer" class="hover:text-amber-400">WhatsApp</a></li>
                @endif
            </ul>
        </div>
    </div>

    <div class="border-t border-white/10 px-4 py-5 text-center text-xs text-neutral-500 sm:px-6">
        &copy; {{ date('Y') }} {{ $settings('company_name', config('app.name')) }}.
        {{ __('nav.rights_reserved') }}
    </div>
</footer>
