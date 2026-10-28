@extends('layouts.app')

@section('title', __('payments.title') . ' | ' . $payment->order_id)
@section('description', __('payments.meta_description'))

@section('content')
    @php
        $statusBadge = match ($payment->status) {
            \App\Models\Payment::STATUS_SETTLEMENT => 'badge-success',
            \App\Models\Payment::STATUS_PENDING => 'badge-amber',
            default => 'badge-danger',
        };

        $statusTranslation = 'payments.status_' . $payment->status;
        $statusLabel = \Illuminate\Support\Facades\Lang::has($statusTranslation)
            ? __($statusTranslation)
            : ucfirst($payment->status);

        $whatsapp = $settings('company_whatsapp', 'https://wa.me/6282338389488');

        // Manual bank transfer details (Filament > Site Settings, group
        // "payments"). Empty values hide the account card instead of showing
        // a half-filled instruction.
        $bankName = $settings('payment_bank_name');
        $bankAccount = $settings('payment_bank_account');
        $bankHolder = $settings('payment_bank_holder');
        $hasBankInfo = filled($bankName) && filled($bankAccount) && filled($bankHolder);
    @endphp

    <div class="page-banner">
        <div class="container">
            <nav aria-label="Breadcrumb" class="breadcrumb-nav">
                <a href="{{ $lroute('home') }}">{{ __('nav.home') }}</a>
                <span>/</span>
                <span style="color: #ffffff;">{{ __('payments.breadcrumb') }}</span>
            </nav>
            <h1 class="page-banner-title">{{ __('payments.title') }}</h1>
        </div>
    </div>

    <div class="container payment-page">
        @if (session('status'))
            <x-alert>{{ session('status') }}</x-alert>
        @endif

        @if (session('error'))
            <x-alert type="error">{{ session('error') }}</x-alert>
        @endif

        <div class="payment-grid">
            <section class="card-light payment-summary" aria-labelledby="payment-summary-heading">
                <span class="payment-eyebrow">{{ $kindLabel }}</span>
                <h2 id="payment-summary-heading" class="payment-heading">{{ $title }}</h2>
                <p class="payment-description">{{ $description }}</p>

                <dl class="payment-details">
                    <div class="payment-detail">
                        <dt>{{ __('payments.order_id') }}</dt>
                        <dd><span class="payment-mono">{{ $payment->order_id }}</span></dd>
                    </div>

                    <div class="payment-detail">
                        <dt>{{ __('payments.amount') }}</dt>
                        <dd class="payment-amount">
                            Rp {{ number_format((float) $payment->gross_amount, 0, ',', '.') }}
                        </dd>
                    </div>

                    <div class="payment-detail">
                        <dt>{{ __('payments.status') }}</dt>
                        <dd><span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span></dd>
                    </div>

                    <div class="payment-detail">
                        <dt>{{ __('payments.method') }}</dt>
                        <dd>{{ $isManual ? __('payments.method_bank_transfer') : __('payments.method_qris') }}</dd>
                    </div>

                    @if ($payment->expires_at)
                        <div class="payment-detail">
                            <dt>{{ __('payments.expires_at') }}</dt>
                            <dd>{{ $payment->expires_at->translatedFormat('j M Y, H:i') }} WIB</dd>
                        </div>
                    @endif

                    @if ($reference)
                        <div class="payment-detail">
                            <dt>{{ __('payments.reference') }}</dt>
                            <dd><span class="payment-mono">{{ $reference }}</span></dd>
                        </div>
                    @endif

                    @if ($customer)
                        <div class="payment-detail">
                            <dt>{{ __('payments.customer') }}</dt>
                            <dd>{{ $customer->name }}</dd>
                        </div>
                    @endif

                    @if ($event)
                        <div class="payment-detail">
                            <dt>{{ __('payments.event_when') }}</dt>
                            <dd>{{ $event->starts_at?->translatedFormat('j M Y, H:i') }} WIB</dd>
                        </div>

                        <div class="payment-detail">
                            <dt>{{ __('payments.event_where') }}</dt>
                            <dd>{{ $event->venue ?: __('events.online_streaming') }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            <section class="card-light payment-action" aria-labelledby="payment-action-heading">
                @if ($payment->isPending())
                    @if ($isManual)
                        <h2 id="payment-action-heading" class="payment-heading">{{ __('payments.manual_heading') }}</h2>
                        <p class="payment-instruction">{{ __('payments.manual_instruction') }}</p>

                        @if ($hasBankInfo)
                            <dl class="payment-details payment-bank">
                                <div class="payment-detail">
                                    <dt>{{ __('payments.bank_name') }}</dt>
                                    <dd>{{ $bankName }}</dd>
                                </div>
                                <div class="payment-detail">
                                    <dt>{{ __('payments.bank_account') }}</dt>
                                    <dd><span class="payment-mono">{{ $bankAccount }}</span></dd>
                                </div>
                                <div class="payment-detail">
                                    <dt>{{ __('payments.bank_holder') }}</dt>
                                    <dd>{{ $bankHolder }}</dd>
                                </div>
                                <div class="payment-detail">
                                    <dt>{{ __('payments.bank_amount') }}</dt>
                                    <dd class="payment-amount">
                                        Rp {{ number_format((float) $payment->gross_amount, 0, ',', '.') }}
                                    </dd>
                                </div>
                            </dl>

                            @if ($payment->hasProof())
                                <div class="payment-alert" role="status">
                                    <h3>{{ __('payments.proof_waiting_title') }}</h3>
                                    <p>{{ __('payments.proof_waiting_body') }}</p>

                                    @if ($payment->payment_proof_uploaded_at)
                                        <p class="payment-alert-detail">
                                            {{ __('payments.proof_uploaded_at', ['time' => $payment->payment_proof_uploaded_at->translatedFormat('j M Y, H:i').' WIB']) }}
                                        </p>
                                    @endif

                                    <div class="payment-alert-actions">
                                        <a href="{{ $lroute('payments.proof.file', ['order_id' => $payment->order_id]) }}"
                                           class="btn btn-outline">
                                            {{ __('payments.proof_view_mine') }}
                                        </a>

                                        @if ($whatsapp)
                                            <a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer"
                                               class="btn btn-outline">
                                                {{ __('payments.contact_admin') }}
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <form method="POST"
                                      action="{{ $lroute('payments.proof.store', ['order_id' => $payment->order_id]) }}"
                                      enctype="multipart/form-data"
                                      class="payment-proof-form">
                                    @csrf

                                    <div class="form-group">
                                        <label class="form-label" for="payment-proof">{{ __('payments.proof_label') }}</label>
                                        <input id="payment-proof" type="file" name="proof"
                                               class="form-input" accept=".jpg,.jpeg,.png,.pdf" required>
                                        <p class="form-help">{{ __('payments.proof_help') }}</p>
                                        @error('proof')<p class="form-error">{{ $message }}</p>@enderror
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label" for="payment-proof-note">{{ __('payments.proof_note_label') }}</label>
                                        <textarea id="payment-proof-note" name="note" rows="3"
                                                  class="form-textarea"
                                                  placeholder="{{ __('payments.proof_note_placeholder') }}">{{ old('note') }}</textarea>
                                        @error('note')<p class="form-error">{{ $message }}</p>@enderror
                                    </div>

                                    <button type="submit" class="btn btn-primary btn-block">
                                        {{ __('payments.proof_submit') }}
                                    </button>
                                </form>
                            @endif
                        @else
                            <div class="payment-alert" role="status">
                                <h3>{{ __('payments.bank_missing_title') }}</h3>
                                <p>{{ __('payments.bank_missing_body') }}</p>

                                @if ($whatsapp)
                                    <div class="payment-alert-actions">
                                        <a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer"
                                           class="btn btn-primary">
                                            {{ __('payments.contact_admin') }}
                                        </a>
                                    </div>
                                @endif
                            </div>
                        @endif
                    @else
                    <h2 id="payment-action-heading" class="payment-heading">{{ __('payments.qr_heading') }}</h2>
                    <p class="payment-instruction">{{ __('payments.qr_instruction') }}</p>

                    @if ($qrUrl)
                        <div class="payment-qr-frame">
                            <img src="{{ $qrUrl }}"
                                 alt="{{ __('payments.qr_alt') }}"
                                 width="264" height="264"
                                 loading="eager"
                                 referrerpolicy="no-referrer">
                        </div>
                    @else
                        <div class="payment-alert" role="status">
                            <h3>{{ __('payments.qr_unavailable_title') }}</h3>
                            <p>{{ __('payments.qr_unavailable_body') }}</p>

                            @if ($chargeError)
                                <p class="payment-alert-detail">{{ $chargeError }}</p>
                            @endif

                            <div class="payment-alert-actions">
                                @if ($whatsapp)
                                    <a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer"
                                       class="btn btn-primary">
                                        {{ __('payments.contact_admin') }}
                                    </a>
                                @endif
                                <a href="{{ url()->current() }}" class="btn btn-outline">
                                    {{ __('payments.retry_charge') }}
                                </a>
                            </div>
                        </div>
                    @endif
                    @endif

                    @if ($payment->expires_at)
                        <div class="payment-countdown"
                             x-data="paymentCountdown"
                             data-expires-at="{{ $payment->expires_at->toIso8601String() }}"
                             data-expired-label="{{ __('payments.countdown_expired') }}">
                            <x-ui-icon name="clock" :size="20" />
                            <span>
                                {{ __('payments.countdown_label') }}
                                <strong data-payment-countdown>{{ $payment->expires_at->format('H:i') }} WIB</strong>
                            </span>
                        </div>

                        <p class="payment-countdown-note">
                            {{ $isManual
                                ? __('payments.countdown_note_manual', ['hours' => $expiryHours])
                                : __('payments.countdown_note', ['minutes' => $expiryMinutes]) }}
                        </p>
                    @endif
                @elseif ($payment->isSettled())
                    <div class="payment-state payment-state-success">
                        <x-ui-icon name="check-circle" :size="40" />
                        <h2 class="payment-heading">{{ __('payments.settled_title') }}</h2>
                        <p>{{ __('payments.settled_body') }}</p>
                        <p class="payment-state-note">{{ __('payments.settled_note') }}</p>
                        <a href="{{ $successUrl }}" class="btn btn-primary btn-block">
                            {{ __('payments.success_action') }}
                        </a>
                    </div>
                @else
                    <div class="payment-state">
                        <x-ui-icon name="x" :size="40" />
                        <h2 class="payment-heading">
                            @if ($payment->status === \App\Models\Payment::STATUS_EXPIRED)
                                {{ __('payments.expired_title') }}
                            @elseif ($payment->status === \App\Models\Payment::STATUS_CANCELLED)
                                {{ __('payments.cancelled_title') }}
                            @elseif ($payment->status === \App\Models\Payment::STATUS_REFUNDED)
                                {{ __('payments.refunded_title') }}
                            @else
                                {{ __('payments.failed_title') }}
                            @endif
                        </h2>

                        <p>
                            @if ($payment->status === \App\Models\Payment::STATUS_EXPIRED)
                                {{ __('payments.expired_body') }}
                            @elseif ($payment->status === \App\Models\Payment::STATUS_CANCELLED)
                                {{ __('payments.cancelled_body') }}
                            @elseif ($payment->status === \App\Models\Payment::STATUS_REFUNDED)
                                {{ __('payments.refunded_body') }}
                            @else
                                {{ __('payments.failed_body') }}
                            @endif
                        </p>

                        <a href="{{ $retryUrl }}" class="btn btn-primary btn-block">
                            {{ __('payments.retry_action') }}
                        </a>
                    </div>
                @endif
            </section>
        </div>

        <p class="payment-secure">
            <x-ui-icon name="lock" :size="16" />
            {{ $isManual ? __('payments.secure_note_manual') : __('payments.secure_note') }}
        </p>
    </div>
@endsection
