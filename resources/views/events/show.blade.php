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

            <div class="event-banner-meta">
                <span class="event-banner-meta-item">
                    <x-ui-icon name="calendar" :size="17" />
                    {{ $event->starts_at->translatedFormat('l, j F Y') }} &middot; {{ $event->starts_at->format('H:i') }} WIB
                </span>

                @if ($event->venue)
                    <span class="event-banner-meta-item">
                        <x-ui-icon name="map-pin" :size="17" />
                        {{ $event->venue }}
                    </span>
                @elseif ($event->location_type === 'online')
                    <span class="event-banner-meta-item">
                        <x-ui-icon name="globe" :size="17" />
                        {{ __('events.online_streaming') }}
                    </span>
                @endif

                <span class="event-banner-price">
                    {{ $event->isFree() ? __('events.free') : 'Rp ' . number_format((float) $event->price, 0, ',', '.') }}
                </span>

                <span class="event-banner-meta-item">
                    <x-ui-icon name="users" :size="17" />
                    {{ $event->isUnlimited() ? __('events.seats_unlimited') : trans_choice('events.seats_left', $event->seatsLeft() ?? 0, ['count' => $event->seatsLeft() ?? 0]) }}
                </span>
            </div>
        </div>
    </div>

    <div class="container event-page">
        @if (session('status'))
            <x-alert>{{ session('status') }}</x-alert>
        @endif

        @if (session('error'))
            <x-alert type="error">{{ session('error') }}</x-alert>
        @endif

        @if ($errors->any())
            <x-alert type="error">
                <ul class="event-error-list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        <div class="event-hero-banner">
            <div class="event-hero-image-wrap">
                @if ($event->cover_url)
                    <img src="{{ $event->cover_url }}"
                         alt="{{ $event->title }}"
                         loading="eager">
                @else
                    <div class="event-hero-fallback">
                        <span class="event-hero-fallback-icon">
                            <x-ui-icon name="calendar" :size="30" />
                        </span>
                        <span class="event-hero-fallback-label">3D Expert Workshop &amp; Event</span>
                        <h2 class="event-hero-fallback-title">{{ $event->title }}</h2>
                    </div>
                @endif
            </div>
        </div>

        <div class="content-sidebar-grid">
            <div>
                <div class="card-light event-panel">
                    <h2 class="event-panel-title">{{ __('events.description_heading') }}</h2>

                    @if ($event->description)
                        <div class="event-description">
                            @foreach (preg_split('/\R{2,}/', (string) $event->description) as $paragraph)
                                <p>{{ $paragraph }}</p>
                            @endforeach
                        </div>
                    @else
                        <p class="event-description-fallback">
                            {{ __('events.description_fallback') }}
                        </p>
                    @endif
                </div>
            </div>

            <aside>
                <div class="card-light event-panel event-sidebar">
                    <h2 class="event-panel-title">{{ __('events.info_heading') }}</h2>

                    <dl class="event-info-list">
                        <div class="event-info-row">
                            <dt class="event-info-label">{{ __('events.when') }}</dt>
                            <dd class="event-info-value">
                                {{ $event->starts_at->translatedFormat('l, j F Y') }}
                                <span class="event-info-note">{{ $event->starts_at->format('H:i') }} WIB</span>
                            </dd>
                        </div>

                        @if ($event->ends_at)
                            <div class="event-info-row">
                                <dt class="event-info-label">{{ __('events.ends') }}</dt>
                                <dd class="event-info-value">
                                    {{ $event->ends_at->translatedFormat('l, j F Y') }}
                                    <span class="event-info-note">{{ $event->ends_at->format('H:i') }} WIB</span>
                                </dd>
                            </div>
                        @endif

                        <div class="event-info-row">
                            <dt class="event-info-label">{{ __('events.format') }}</dt>
                            <dd class="event-info-value">
                                @if ($event->location_type === 'online')
                                    <span class="badge badge-info">{{ __('events.online') }}</span>
                                @elseif ($event->location_type === 'hybrid')
                                    <span class="badge badge-amber">{{ __('events.hybrid') }}</span>
                                @else
                                    <span class="badge badge-dark event-badge-light">{{ __('events.offline') }}</span>
                                @endif
                            </dd>
                        </div>

                        <div class="event-info-row">
                            <dt class="event-info-label">{{ __('events.where') }}</dt>
                            <dd class="event-info-value">
                                @if ($event->venue)
                                    {{ $event->venue }}
                                @elseif ($event->location_type === 'online')
                                    {{ __('events.online_streaming') }}
                                @else
                                    {{ __('events.tbd') }}
                                @endif
                            </dd>
                        </div>

                        <div class="event-info-row">
                            <dt class="event-info-label">{{ __('events.investment') }}</dt>
                            <dd class="event-info-value event-info-price">
                                {{ $event->isFree() ? __('events.free') : 'Rp ' . number_format((float) $event->price, 0, ',', '.') }}
                            </dd>
                        </div>

                        <div class="event-info-row">
                            <dt class="event-info-label">{{ __('events.seats_remaining') }}</dt>
                            <dd class="event-info-value">
                                @if ($event->isUnlimited())
                                    {{ __('events.seats_unlimited') }}
                                @else
                                    {{ trans_choice('events.seats_of', $event->seatsLeft() ?? 0, ['left' => $event->seatsLeft() ?? 0, 'capacity' => $event->capacity]) }}
                                @endif
                            </dd>
                        </div>
                    </dl>

                    @if ($registration && $registration->isPending())
                        <div class="event-register-box event-register-pending">
                            <span class="event-register-icon">
                                <x-ui-icon name="clock" :size="24" />
                            </span>
                            <p class="event-register-title">{{ __('events.payment_pending') }}</p>
                            <p class="event-register-note">{{ __('events.payment_pending_note') }}</p>

                            @if ($registration->payment)
                                <p class="event-register-note event-register-deadline">
                                    {{ __('payments.expires_at') }}:
                                    <strong>{{ $registration->payment->expires_at?->translatedFormat('j M Y, H:i') }} WIB</strong>
                                </p>

                                <a href="{{ $lroute('payments.show', ['order_id' => $registration->payment->order_id]) }}"
                                   class="btn btn-primary btn-block">
                                    {{ __('events.continue_payment') }}
                                </a>
                            @endif

                            @if ($customerCan('cancel', $registration))
                                <form method="POST" action="{{ $lroute('events.registration.cancel', ['registration' => $registration->id]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-secondary btn-block btn-sm event-cancel-button">
                                        {{ __('events.cancel') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    @elseif ($registration && $registration->occupiesSeat())
                        <div class="event-register-box event-register-success">
                            <span class="event-register-icon">
                                <x-ui-icon name="check-circle" :size="24" />
                            </span>
                            <p class="event-register-title">{{ __('events.registered') }}</p>
                            <p class="event-register-note">
                                {{ __('events.ticket_code') }}:
                                <span class="event-ticket-code">{{ $registration->ticket_code }}</span>
                            </p>

                            @if ($customerCan('cancel', $registration))
                                <form method="POST" action="{{ $lroute('events.registration.cancel', ['registration' => $registration->id]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-secondary btn-block btn-sm event-cancel-button">
                                        {{ __('events.cancel') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    @elseif ($event->starts_at->isPast())
                        <div class="event-register-box event-register-message">
                            <p>{{ __('events.past') }}</p>
                        </div>
                    @elseif (! $event->registrationIsOpen())
                        <div class="event-register-box event-register-message">
                            <p>{{ __('events.closed') }}</p>
                        </div>
                    @elseif (! $event->hasSeatsLeft())
                        <div class="event-register-box event-register-message event-register-message-danger">
                            <p>{{ __('events.full') }}</p>
                        </div>
                    @elseif (auth('customer')->check())
                        <div class="event-register-box" x-data="eventRegister">
                            <button type="button"
                                    class="btn btn-primary btn-block btn-lg"
                                    data-event-register-open>
                                {{ $event->isFree() ? __('events.register') : __('events.register_and_pay') }}
                            </button>

                            <p class="event-register-note event-register-note-center">
                                @if ($event->isFree())
                                    {{ __('events.register_note') }}
                                @else
                                    {{ __('events.paid_hold_note') }}
                                @endif
                            </p>

                            <dialog class="event-dialog"
                                    data-event-register-dialog
                                    data-open="{{ $errors->any() ? 'true' : 'false' }}"
                                    aria-labelledby="event-dialog-title">
                                <div class="event-dialog-inner">
                                    <div class="event-dialog-header">
                                        <h2 id="event-dialog-title" class="event-dialog-title">{{ __('events.register_dialog_title') }}</h2>
                                        <button type="button"
                                                class="event-dialog-close"
                                                data-event-register-close
                                                aria-label="{{ __('events.close') }}">
                                            <x-ui-icon name="x" :size="20" />
                                        </button>
                                    </div>

                                    <p class="event-dialog-intro">{{ __('events.register_dialog_intro') }}</p>

                                    <div class="event-dialog-summary">
                                        <p class="event-dialog-event-title">{{ $event->title }}</p>
                                        <p class="event-dialog-event-meta">
                                            <x-ui-icon name="calendar" :size="15" />
                                            {{ $event->starts_at->translatedFormat('l, j F Y') }} &middot; {{ $event->starts_at->format('H:i') }} WIB
                                        </p>
                                        <p class="event-dialog-event-meta">
                                            @if ($event->venue)
                                                <x-ui-icon name="map-pin" :size="15" />
                                                {{ $event->venue }}
                                            @elseif ($event->location_type === 'online')
                                                <x-ui-icon name="globe" :size="15" />
                                                {{ __('events.online_streaming') }}
                                            @else
                                                <x-ui-icon name="map-pin" :size="15" />
                                                {{ __('events.tbd') }}
                                            @endif
                                        </p>

                                        @if (! $event->isFree())
                                            <p class="event-dialog-price">
                                                {{ __('events.paid_price', ['price' => number_format((float) $event->price, 0, ',', '.')]) }}
                                            </p>
                                            <p class="event-dialog-payment-note">
                                                {{ __('events.paid_hold_note') }}
                                            </p>
                                        @endif
                                    </div>

                                    <form method="POST" action="{{ $lroute('events.enroll', ['event' => $event->slug]) }}">
                                        @csrf

                                        <x-customer-field
                                            name="attendee_name"
                                            :label="__('events.form_name')"
                                            :value="$customer->name"
                                            required
                                            autocomplete="name" />

                                        <x-customer-field
                                            name="attendee_phone"
                                            type="tel"
                                            :label="__('events.form_phone')"
                                            :value="$customer->phone"
                                            required
                                            autocomplete="tel" />

                                        <div class="form-group">
                                            <label for="attendee_email" class="form-label">
                                                {{ __('events.form_email') }}
                                            </label>
                                            <div class="event-input-locked">
                                                <input id="attendee_email"
                                                       type="email"
                                                       class="form-input"
                                                       value="{{ $customer->email }}"
                                                       readonly
                                                       aria-readonly="true">
                                                <span class="event-input-lock" aria-hidden="true">
                                                    <x-ui-icon name="lock" :size="16" />
                                                </span>
                                            </div>
                                            <p class="form-help">{{ __('events.form_email_locked') }}</p>
                                        </div>

                                        <p class="event-form-note">{{ __('events.form_note') }}</p>

                                        @if (! $event->isFree())
                                            <x-payment-method-picker :selected="old('payment_method', $registration?->payment?->payment_type ?? 'qris')" />
                                        @endif

                                        <div class="event-dialog-actions">
                                            <button type="button" class="btn btn-secondary" data-event-register-close>
                                                {{ __('events.back') }}
                                            </button>
                                            <button type="submit"
                                                    class="btn btn-primary"
                                                    data-loading-label="{{ __('events.submitting') }}">
                                                {{ $event->isFree() ? __('events.confirm_register') : __('events.register_and_pay') }}
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </dialog>

                            <noscript>
                                <form method="POST" action="{{ $lroute('events.enroll', ['event' => $event->slug]) }}" class="event-noscript-form">
                                    @csrf

                                    <div class="form-group">
                                        <label for="fallback_attendee_name" class="form-label">{{ __('events.form_name') }}</label>
                                        <input id="fallback_attendee_name" name="attendee_name" type="text" class="form-input"
                                               value="{{ old('attendee_name', $customer->name) }}" required autocomplete="name">
                                    </div>

                                    <div class="form-group">
                                        <label for="fallback_attendee_phone" class="form-label">{{ __('events.form_phone') }}</label>
                                        <input id="fallback_attendee_phone" name="attendee_phone" type="tel" class="form-input"
                                               value="{{ old('attendee_phone', $customer->phone) }}" required autocomplete="tel">
                                    </div>

                                    <div class="form-group">
                                        <label for="fallback_attendee_email" class="form-label">{{ __('events.form_email') }}</label>
                                        <input id="fallback_attendee_email" type="email" class="form-input"
                                               value="{{ $customer->email }}" readonly aria-readonly="true">
                                    </div>

                                    <button type="submit" class="btn btn-primary btn-block">
                                        {{ __('events.confirm_register') }}
                                    </button>
                                </form>
                            </noscript>
                        </div>
                    @else
                        <div class="event-register-box">
                            <a href="{{ $lroute('customer.login') }}" class="btn btn-primary btn-block btn-lg">
                                {{ __('events.register_login') }}
                            </a>
                        </div>
                    @endif
                </div>

                @if ($wa = $settings('company_whatsapp'))
                    <div class="event-help-link">
                        <a href="{{ $wa }}?text={{ urlencode('Halo 3D Experts, saya ada pertanyaan mengenai acara: ' . $event->title) }}"
                           target="_blank" rel="noopener noreferrer">
                            <x-ui-icon name="message-circle" :size="16" />
                            {{ __('events.ask_question') }}
                        </a>
                    </div>
                @endif
            </aside>
        </div>
    </div>
@endsection
