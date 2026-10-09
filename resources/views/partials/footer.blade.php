{{--
3D Expert Web Footer (Semantic Plain CSS per docs/design.md)
--}}
<footer id="kontak" class="site-footer">
    <div class="container footer-grid">
        <div>
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px;">
                <img src="{{ route('media.show', ['path' => 'images/logo.png']) }}" alt="" width="32" height="32"
                    style="height: 32px; width: auto; filter: brightness(1.2);">
            </div>

            @if ($address = $settings('company_address'))
                <p style="color: var(--on-dark-muted); font-size: 0.9rem; max-width: 340px; margin-bottom: 20px;">
                    {{ $address }}
                </p>
            @endif

            <div class="footer-social-row">
                @if ($ig = $settings('company_instagram'))
                    <a href="{{ $ig }}" target="_blank" rel="noopener noreferrer" class="social-icon-box"
                        aria-label="Instagram">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path
                                d="M12 2.2c3.2 0 3.6 0 4.9.07 1.2.06 1.8.25 2.2.42.6.22 1 .5 1.4.9.4.4.7.8.9 1.4.17.4.36 1 .42 2.2.07 1.3.07 1.7.07 4.9s0 3.6-.07 4.9c-.06 1.2-.25 1.8-.42 2.2a3.8 3.8 0 0 1-.9 1.4c-.4.4-.8.7-1.4.9-.4.17-1 .36-2.2.42-1.3.07-1.7.07-4.9.07s-3.6 0-4.9-.07c-1.2-.06-1.8-.25-2.2-.42a3.8 3.8 0 0 1-1.4-.9 3.8 3.8 0 0 1-.9-1.4c-.17-.4-.36-1-.42-2.2C2.2 15.6 2.2 15.2 2.2 12s0-3.6.07-4.9c.06-1.2.25-1.8.42-2.2.22-.6.5-1 .9-1.4.4-.4.8-.7 1.4-.9.4-.17 1-.36 2.2-.42C8.4 2.2 8.8 2.2 12 2.2Zm0 5.3a4.5 4.5 0 1 0 0 9 4.5 4.5 0 0 0 0-9Zm0 7.4a2.9 2.9 0 1 1 0-5.8 2.9 2.9 0 0 1 0 5.8Zm5.7-7.6a1.05 1.05 0 1 1-2.1 0 1.05 1.05 0 0 1 2.1 0Z" />
                        </svg>
                    </a>
                @endif
                @if ($yt = $settings('company_youtube'))
                    <a href="{{ $yt }}" target="_blank" rel="noopener noreferrer" class="social-icon-box"
                        aria-label="YouTube">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path
                                d="M23 12s0-3.9-.5-5.8a3 3 0 0 0-2.1-2.1C18.5 3.6 12 3.6 12 3.6s-6.5 0-8.4.5A3 3 0 0 0 1.5 6.2C1 8.1 1 12 1 12s0 3.9.5 5.8a3 3 0 0 0 2.1 2.1c1.9.5 8.4.5 8.4.5s6.5 0 8.4-.5a3 3 0 0 0 2.1-2.1C23 15.9 23 12 23 12ZM9.8 15.6V8.4l6.2 3.6-6.2 3.6Z" />
                        </svg>
                    </a>
                @endif
            </div>
        </div>

        <div>
            <h2 class="footer-col-title">
                {{ __('nav.navigation') }}
            </h2>
            <ul class="footer-links-list">
                <li><a href="{{ $lroute('home') }}">{{ __('nav.home') }}</a></li>
                <li><a href="{{ $lroute('home') }}#portofolio">{{ __('nav.portfolio') }}</a></li>
                <li><a href="{{ $lroute('home') }}#layanan">{{ __('nav.services') }}</a></li>
                <li><a href="{{ $lroute('courses.index') }}">{{ __('nav.courses') }}</a></li>
                <li><a href="{{ $lroute('careers.index') }}">{{ __('nav.careers') }}</a></li>
            </ul>
        </div>

        <div>
            <h2 class="footer-col-title">
                {{ __('nav.features') ?? 'Fitur & Akun' }}
            </h2>
            <ul class="footer-links-list">
                <li><a href="{{ $lroute('tracker.show') }}">{{ __('tracker.eyebrow') ?? 'Lacak Proyek' }}</a></li>
                <li><a href="{{ $lroute('home') }}#tentang">{{ __('nav.about') }}</a></li>
                @if (auth('customer')->check())
                    <li style="margin-top: 8px; padding-top: 8px; border-top: 1px solid var(--line-dark);">
                        <a href="{{ $lroute('customer.account') }}"
                            style="display: inline-flex; align-items: center; gap: 6px; color: var(--gold); font-weight: 600;">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
                                viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <span>{{ auth('customer')->user()->name }}</span>
                        </a>
                    </li>
                    <li>
                        <form method="POST" action="{{ $lroute('customer.logout') }}" style="display: inline;">
                            @csrf
                            <button type="submit"
                                style="background: none; border: none; padding: 0; color: var(--on-dark-muted); cursor: pointer; font-size: inherit; text-decoration: none; transition: color var(--transition);"
                                onmouseover="this.style.color='var(--danger)'"
                                onmouseout="this.style.color='var(--on-dark-muted)'">
                                {{ __('customer.logout') }} &rarr;
                            </button>
                        </form>
                    </li>
                @else
                    <li><a href="{{ $lroute('customer.login') }}">{{ __('customer.login_title') }}</a></li>
                    <li><a href="{{ $lroute('customer.register') }}">{{ __('customer.register_title') }}</a></li>
                @endif
            </ul>
        </div>

        <div>
            <h2 class="footer-col-title">
                {{ __('nav.contact') }}
            </h2>
            <ul class="footer-links-list">
                @if ($phone = $settings('company_phone'))
                    <li><a href="tel:{{ preg_replace('/\s+/', '', $phone) }}">{{ $phone }}</a></li>
                @endif
                @if ($email = $settings('company_email'))
                    <li><a href="mailto:{{ $email }}">{{ $email }}</a></li>
                @endif
                @if ($wa = $settings('company_whatsapp'))
                    <li><a href="{{ $wa }}" target="_blank" rel="noopener noreferrer">WhatsApp</a></li>
                @endif
            </ul>
        </div>
    </div>

    <div class="container footer-bottom">
        <div>
            &copy; {{ date('Y') }} {{ $settings('company_name', config('app.name')) }}.
            {{ __('nav.rights_reserved') }}
        </div>
        <div>
            <a href="#main" style="color: var(--gold);">{{ __('nav.back_to_top') ?? 'Kembali ke atas &uarr;' }}</a>
        </div>
    </div>
</footer>