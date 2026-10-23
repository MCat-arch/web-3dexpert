@extends('layouts.app')

@section('title', __('events.mine_title') . ' | ' . $settings('company_name', config('app.name')))

@section('content')
    <div class="page-banner">
        <div class="container">
            <h1 class="page-banner-title">{{ __('events.mine_title') }}</h1>
        </div>
    </div>

    <div class="container" style="max-width: 860px; padding-bottom: 70px;">
        @if ($registrations->isEmpty())
            <x-empty-state :title="__('events.mine_empty')" />
        @else
            <div style="display: flex; flex-direction: column; gap: 16px;">
                @foreach ($registrations as $registration)
                    <div class="card-light" style="padding: 24px;">
                        <div
                            style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap;">
                            <div>
                                <h2 style="font-size: 1.15rem; font-weight: 600; margin-bottom: 6px;">
                                    <a href="{{ $lroute('events.show', ['event' => $registration->event->slug]) }}"
                                        style="color: inherit; text-decoration: none;">
                                        {{ $registration->event->title }}
                                    </a>
                                </h2>
                                <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 8px;">
                                    {{ $registration->event->starts_at->translatedFormat('l, j F Y') }} &middot;
                                    {{ $registration->event->starts_at->format('H:i') }}
                                </p>
                                <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0;">
                                    {{ __('events.ticket_code') }}:
                                    <span
                                        style="font-family: monospace; background: #e5e5e5; padding: 2px 6px; border-radius: 4px; font-weight: 600; color: #000;">{{ $registration->ticket_code }}</span>
                                </p>
                            </div>

                            @php
                                $statusClass = match ($registration->status) {
                                    'confirmed', 'attended' => 'badge-success',
                                    'pending' => 'badge-amber',
                                    default => 'badge-dark',
                                };
                            @endphp
                            <span class="badge {{ $statusClass }}">
                                {{ __('events.status_' . $registration->status) }}
                            </span>
                        </div>

                        @if ($registration->isPending() && $registration->payment)
                            <div class="event-mine-payment">
                                <p class="event-register-note">
                                    {{ __('events.payment_pending_note') }}
                                    @if ($registration->payment->expires_at)
                                        <strong>{{ $registration->payment->expires_at->translatedFormat('j M Y, H:i') }} WIB</strong>
                                    @endif
                                </p>
                                <a href="{{ $lroute('payments.show', ['order_id' => $registration->payment->order_id]) }}"
                                   class="btn btn-primary btn-sm">
                                    {{ __('events.continue_payment') }}
                                </a>
                            </div>
                        @endif

                        @if ($customerCan('cancel', $registration))
                            <form method="POST"
                                action="{{ $lroute('events.registration.cancel', ['registration' => $registration->id]) }}"
                                style="margin-top: 16px; padding-top: 12px; border-top: 1px solid var(--line-light);">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--danger);">
                                    {{ __('events.cancel') }}
                                </button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection