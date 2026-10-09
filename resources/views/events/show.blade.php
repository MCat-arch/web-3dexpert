@extends('layouts.app')

@section('title', $event->title . ' | ' . $settings('company_name', config('app.name')))
@section('description', Str::limit(strip_tags((string) $event->description), 155))

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
        </div>
    </div>

    <div class="container" style="max-width: 860px; padding-bottom: 70px;">
        <div class="card-light" style="padding: 28px; margin-bottom: 30px;">
            <div
                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--line-light);">
                <div>
                    <span
                        style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-muted); display: block; margin-bottom: 4px;">
                        {{ __('events.when') }}
                    </span>
                    <p style="font-size: 1rem; font-weight: 500; color: var(--text); margin-bottom: 0;">
                        {{ $event->starts_at->translatedFormat('l, j F Y') }} &middot;
                        {{ $event->starts_at->format('H:i') }}
                    </p>
                </div>

                @if ($event->venue)
                    <div>
                        <span
                            style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-muted); display: block; margin-bottom: 4px;">
                            {{ __('events.where') }}
                        </span>
                        <p style="font-size: 1rem; font-weight: 500; color: var(--text); margin-bottom: 0;">
                            {{ $event->venue }}
                        </p>
                    </div>
                @endif
            </div>

            @if ($event->description)
                <div
                    style="margin-top: 24px; display: flex; flex-direction: column; gap: 14px; font-size: 1rem; line-height: 1.7; color: var(--text);">
                    @foreach (preg_split('/\R{2,}/', (string) $event->description) as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Enrollment Card --}}
        <div class="card-light" style="padding: 28px; background: #fafafa;">
            @if ($registration && $registration->occupiesSeat())
                <p style="font-size: 1.05rem; font-weight: 600; color: var(--success); margin-bottom: 8px;">
                    ✓ {{ __('events.registered') }}
                </p>
                <p style="font-size: 0.95rem; color: var(--text); margin-bottom: 16px;">
                    {{ __('events.ticket_code') }}:
                    <strong
                        style="font-family: monospace; background: #e5e5e5; padding: 3px 8px; border-radius: 4px;">{{ $registration->ticket_code }}</strong>
                </p>

                @if ($customerCan('cancel', $registration))
                    <form method="POST" action="{{ $lroute('events.registration.cancel', ['registration' => $registration->id]) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--danger);">
                            {{ __('events.cancel') }}
                        </button>
                    </form>
                @endif

            @elseif ($event->starts_at->isPast())
                <p style="color: var(--text-muted); margin: 0;">{{ __('events.past') }}</p>

            @elseif (!$event->registrationIsOpen())
                <p style="color: var(--text-muted); margin: 0;">{{ __('events.closed') }}</p>

            @elseif (!$event->hasSeatsLeft())
                <p style="color: var(--danger); font-weight: 600; margin: 0;">{{ __('events.full') }}</p>

            @elseif (!$event->isFree())
                <p style="font-weight: 600; color: var(--gold-hover); margin-bottom: 4px;">
                    {{ __('events.paid_unavailable') }}
                </p>
                <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0;">
                    {{ __('events.paid_price', ['price' => number_format((float) $event->price, 0, ',', '.')]) }}
                </p>

            @elseif (auth('customer')->check())
                <form method="POST" action="{{ $lroute('events.enroll', ['event' => $event->slug]) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-lg">
                        {{ __('events.register') }}
                    </button>
                </form>

            @else
                <a href="{{ $lroute('customer.login') }}" class="btn btn-primary btn-lg">
                    {{ __('events.register_login') }}
                </a>
            @endif

            @if ($event->registrationIsOpen() && !$event->isUnlimited())
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 14px; margin-bottom: 0;">
                    {{ trans_choice('events.seats_left', $event->seatsLeft(), ['count' => $event->seatsLeft()]) }}
                </p>
            @endif
        </div>
    </div>
@endsection