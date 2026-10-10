@extends('layouts.app')

@section('title', $event->title . ' | ' . $settings('company_name', config('app.name')))
@section('description', Str::limit(strip_tags((string) $event->description), 155))
@section('og_image', $event->cover_url ?? '')

@section('content')
    <div class="page-banner">
        <div class="container">
            <nav aria-label="Breadcrumb" class="breadcrumb-nav">
                <a href="{{ $lroute('home') }}">{{ __('nav.home') }}</a>
                <span>/</span>
                <a href="{{ $lroute('events.index') }}">{{ __('events.title') }}</a>
                <span>/</span>
                <span style="color: #ffffff;">{{ Str::limit($event->title, 40) }}</span>
            </nav>

            <h1 class="page-banner-title">{{ $event->title }}</h1>

            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 16px; font-size: 0.95rem; color: var(--on-dark-muted); margin-top: 10px;">
                <span>📅 {{ $event->starts_at->translatedFormat('l, j F Y') }} &middot; {{ $event->starts_at->format('H:i') }} WIB</span>
                @if ($event->venue)
                    <span>📍 {{ $event->venue }}</span>
                @elseif ($event->location_type === 'online')
                    <span>🌐 Online Streaming</span>
                @endif
                <span style="font-weight: 600; color: var(--gold);">
                    {{ $event->isFree() ? __('events.free') : 'Rp ' . number_format((float) $event->price, 0, ',', '.') }}
                </span>
                <span>
                    👥 {{ $event->isUnlimited() ? __('events.seats_unlimited') : trans_choice('events.seats_left', $event->seatsLeft() ?? 0, ['count' => $event->seatsLeft() ?? 0]) }}
                </span>
            </div>
        </div>
    </div>

    <div class="container" style="padding-bottom: 70px;">
        {{-- Large Hero Banner / Pamphlet at Top --}}
        <div class="event-hero-banner">
            <div class="event-hero-image-wrap">
                @if ($event->cover_url)
                    <img src="{{ $event->cover_url }}"
                         alt="{{ $event->title }}"
                         loading="eager"
                         style="width: 100%; height: 100%; object-fit: contain;">
                @else
                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 48px 24px; text-align: center; color: #ffffff;">
                        <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(200, 160, 89, 0.15); display: flex; align-items: center; justify-content: center; margin-bottom: 16px; color: var(--gold);">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                        </div>
                        <span style="font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; color: var(--gold); margin-bottom: 6px;">
                            3D Expert Workshop & Event
                        </span>
                        <h2 style="font-size: 1.5rem; font-weight: 700; color: #ffffff; max-width: 650px; margin: 0; line-height: 1.4;">
                            {{ $event->title }}
                        </h2>
                    </div>
                @endif
            </div>
        </div>

        {{-- 2-Column Responsive Layout: Description (Left) & Info / Registration Card (Right) --}}
        <div class="content-sidebar-grid">
            {{-- Left Column: Description --}}
            <div>
                <div class="card-light" style="padding: 28px;">
                    <h2 style="font-size: 1.35rem; font-weight: 600; color: var(--text); margin-bottom: 18px; padding-bottom: 10px; border-bottom: 1px solid var(--line-light);">
                        Deskripsi Acara
                    </h2>

                    @if ($event->description)
                        <div style="display: flex; flex-direction: column; gap: 14px; font-size: 1rem; line-height: 1.75; color: var(--text);">
                            @foreach (preg_split('/\R{2,}/', (string) $event->description) as $paragraph)
                                <p>{{ $paragraph }}</p>
                            @endforeach
                        </div>
                    @else
                        <p style="font-size: 0.95rem; color: var(--text-muted); line-height: 1.6; margin-bottom: 0;">
                            Ikuti workshop dan sesi berbagi wawasan eksklusif bersama praktisi industri 3D Expert. Dapatkan pengalaman berharga, materi terkini, dan perluas relasi profesional Anda.
                        </p>
                    @endif
                </div>
            </div>

            {{-- Right Column: Information & Registration Card --}}
            <aside>
                <div class="card-light" style="padding: 24px; position: sticky; top: 90px;">
                    <h2 style="font-size: 1.2rem; font-weight: 600; color: var(--text); margin-bottom: 18px; padding-bottom: 8px; border-bottom: 1px solid var(--line-light);">
                        Informasi Acara
                    </h2>

                    <dl style="display: flex; flex-direction: column; gap: 14px; font-size: 0.95rem; margin-bottom: 24px;">
                        <div style="display: flex; justify-content: space-between; gap: 12px; padding-bottom: 8px; border-bottom: 1px solid #f0f0f0;">
                            <dt style="color: var(--text-muted);">{{ __('events.when') }}</dt>
                            <dd style="font-weight: 600; color: var(--text); text-align: right;">
                                {{ $event->starts_at->translatedFormat('l, j F Y') }}<br>
                                <span style="font-size: 0.85rem; color: var(--text-muted);">{{ $event->starts_at->format('H:i') }} WIB</span>
                            </dd>
                        </div>

                        @if ($event->ends_at)
                            <div style="display: flex; justify-content: space-between; gap: 12px; padding-bottom: 8px; border-bottom: 1px solid #f0f0f0;">
                                <dt style="color: var(--text-muted);">Selesai</dt>
                                <dd style="font-weight: 600; color: var(--text); text-align: right;">
                                    {{ $event->ends_at->translatedFormat('j F Y') }}<br>
                                    <span style="font-size: 0.85rem; color: var(--text-muted);">{{ $event->ends_at->format('H:i') }} WIB</span>
                                </dd>
                            </div>
                        @endif

                        <div style="display: flex; justify-content: space-between; gap: 12px; padding-bottom: 8px; border-bottom: 1px solid #f0f0f0;">
                            <dt style="color: var(--text-muted);">Format</dt>
                            <dd style="font-weight: 600; color: var(--text); text-align: right;">
                                @if ($event->location_type === 'online')
                                    <span class="badge badge-dark" style="background: #eef2ff; color: #4338ca;">Online</span>
                                @elseif ($event->location_type === 'hybrid')
                                    <span class="badge badge-dark" style="background: #fef3c7; color: #b45309;">Hybrid</span>
                                @else
                                    <span class="badge badge-dark" style="background: #f3f4f6; color: #374151;">Offline</span>
                                @endif
                            </dd>
                        </div>

                        <div style="display: flex; justify-content: space-between; gap: 12px; padding-bottom: 8px; border-bottom: 1px solid #f0f0f0;">
                            <dt style="color: var(--text-muted);">{{ __('events.where') }}</dt>
                            <dd style="font-weight: 600; color: var(--text); text-align: right;">
                                @if ($event->venue)
                                    {{ $event->venue }}
                                @elseif ($event->location_type === 'online')
                                    Online Streaming
                                @else
                                    Akan Diinformasikan
                                @endif
                            </dd>
                        </div>

                        <div style="display: flex; justify-content: space-between; gap: 12px; padding-bottom: 8px; border-bottom: 1px solid #f0f0f0;">
                            <dt style="color: var(--text-muted);">Investasi</dt>
                            <dd style="font-weight: 600; color: var(--gold-hover); text-align: right;">
                                {{ $event->isFree() ? __('events.free') : 'Rp ' . number_format((float) $event->price, 0, ',', '.') }}
                            </dd>
                        </div>

                        <div style="display: flex; justify-content: space-between; gap: 12px; padding-bottom: 8px; border-bottom: 1px solid #f0f0f0;">
                            <dt style="color: var(--text-muted);">Sisa Kursi</dt>
                            <dd style="font-weight: 600; color: {{ $event->hasSeatsLeft() ? 'var(--success)' : 'var(--danger)' }}; text-align: right;">
                                @if ($event->isUnlimited())
                                    {{ __('events.seats_unlimited') }}
                                @else
                                    {{ $event->seatsLeft() }} / {{ $event->capacity }} Kursi
                                @endif
                            </dd>
                        </div>
                    </dl>

                    {{-- Registration Action Box --}}
                    <div style="padding: 16px; border-radius: var(--radius-btn, 8px); background: #fafafa; border: 1px solid var(--line-light);">
                        @if ($registration && $registration->occupiesSeat())
                            <div style="text-align: center; margin-bottom: 12px;">
                                <p style="font-size: 1rem; font-weight: 600; color: var(--success); margin-bottom: 4px;">
                                    ✓ {{ __('events.registered') }}
                                </p>
                                <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 10px;">
                                    {{ __('events.ticket_code') }}:
                                    <strong style="font-family: monospace; background: #e5e5e5; padding: 2px 6px; border-radius: 4px; color: var(--text);">{{ $registration->ticket_code }}</strong>
                                </p>
                            </div>

                            @if ($customerCan('cancel', $registration))
                                <form method="POST" action="{{ $lroute('events.registration.cancel', ['registration' => $registration->id]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-secondary btn-block btn-sm" style="color: var(--danger);">
                                        {{ __('events.cancel') }}
                                    </button>
                                </form>
                            @endif

                        @elseif ($event->starts_at->isPast())
                            <div style="text-align: center; padding: 8px 0;">
                                <p style="color: var(--text-muted); margin: 0; font-weight: 500;">{{ __('events.past') }}</p>
                            </div>

                        @elseif (! $event->registrationIsOpen())
                            <div style="text-align: center; padding: 8px 0;">
                                <p style="color: var(--text-muted); margin: 0; font-weight: 500;">{{ __('events.closed') }}</p>
                            </div>

                        @elseif (! $event->hasSeatsLeft())
                            <div style="text-align: center; padding: 8px 0;">
                                <p style="color: var(--danger); font-weight: 600; margin: 0;">{{ __('events.full') }}</p>
                            </div>

                        @elseif (! $event->isFree())
                            <div style="text-align: center; margin-bottom: 12px;">
                                <p style="font-weight: 600; color: var(--gold-hover); margin-bottom: 4px;">
                                    {{ __('events.paid_unavailable') }}
                                </p>
                                <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0;">
                                    {{ __('events.paid_price', ['price' => number_format((float) $event->price, 0, ',', '.')]) }}
                                </p>
                            </div>

                            @if ($wa = $settings('company_whatsapp'))
                                <a href="{{ $wa }}?text={{ urlencode('Halo 3D Experts, saya ingin mendaftar acara: ' . $event->title) }}"
                                   target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-block">
                                    Daftar via WhatsApp &rarr;
                                </a>
                            @endif

                        @elseif (auth('customer')->check())
                            <form method="POST" action="{{ $lroute('events.enroll', ['event' => $event->slug]) }}">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-block btn-lg" style="box-shadow: 0 4px 12px rgba(200, 160, 89, 0.25);">
                                    {{ __('events.register') }}
                                </button>
                            </form>

                        @else
                            <a href="{{ $lroute('customer.login') }}" class="btn btn-primary btn-block btn-lg" style="box-shadow: 0 4px 12px rgba(200, 160, 89, 0.25);">
                                {{ __('events.register_login') }}
                            </a>
                        @endif
                    </div>

                    @if ($wa = $settings('company_whatsapp'))
                        <div style="margin-top: 16px; text-align: center;">
                            <a href="{{ $wa }}?text={{ urlencode('Halo 3D Experts, saya ada pertanyaan mengenai acara: ' . $event->title) }}"
                               target="_blank" rel="noopener noreferrer" style="font-size: 0.85rem; color: var(--text-muted); text-decoration: none;">
                                💬 Punya pertanyaan seputar acara? Hubungi kami
                            </a>
                        </div>
                    @endif
                </div>
            </aside>
        </div>
    </div>
@endsection