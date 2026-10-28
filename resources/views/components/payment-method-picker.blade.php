@props(['selected' => 'qris'])

@php
    /*
     * Two payment routes, chosen by the customer instead of by an env flag:
     * 'qris' is charged through Midtrans, 'bank_transfer' creates the order as
     * manual from the start (see StartCourseRegistration / EnrollInEvent).
     *
     * Bank details are read live from Sistem > Pengaturan Pembayaran. When they
     * are incomplete the manual option is disabled rather than letting the
     * customer pick a method with nothing to transfer to.
     */
    $selected = old('payment_method', $selected) === 'bank_transfer' ? 'bank_transfer' : 'qris';

    $bankName = $settings('payment_bank_name');
    $bankAccount = $settings('payment_bank_account');
    $bankHolder = $settings('payment_bank_holder');
    $hasBank = filled($bankName) && filled($bankAccount) && filled($bankHolder);
@endphp

<fieldset class="pm-picker {{ $selected === 'bank_transfer' ? 'is-bank-active' : '' }}"
          x-data="paymentMethodPicker">
    <legend class="pm-picker-legend">{{ __('payments.choose_method') }}</legend>

    {{-- Without JavaScript the reveal never runs, so keep the account number
         visible instead of hiding the only way to pay. --}}
    <noscript>
        <style>.pm-bank-card { display: flex; }</style>
    </noscript>

    <label class="pm-option {{ $selected === 'qris' ? 'is-selected' : '' }}">
        <input type="radio" name="payment_method" value="qris"
               @checked($selected === 'qris') required>

        <span class="pm-option-body">
            <span class="pm-option-head">
                <strong>{{ __('payments.method_qris') }}</strong>
                <span class="badge badge-gold pm-option-badge">{{ __('payments.method_qris_badge') }}</span>
            </span>
            <span class="pm-option-desc">{{ __('payments.method_qris_desc') }}</span>
        </span>
    </label>

    <label class="pm-option {{ $selected === 'bank_transfer' ? 'is-selected' : '' }} {{ $hasBank ? '' : 'is-disabled' }}">
        <input type="radio" name="payment_method" value="bank_transfer"
               @checked($selected === 'bank_transfer') @disabled(! $hasBank)>

        <span class="pm-option-body">
            <span class="pm-option-head">
                <strong>{{ __('payments.method_bank') }}</strong>
                <span class="badge badge-dark pm-option-badge">{{ __('payments.method_bank_badge') }}</span>
            </span>
            <span class="pm-option-desc">{{ __('payments.method_bank_desc') }}</span>

            @if ($hasBank)
                <span class="pm-bank-card">
                    <span class="pm-bank-row">
                        <span class="pm-bank-label">{{ __('payments.bank_name') }}</span>
                        <span class="pm-bank-value">{{ $bankName }}</span>
                    </span>
                    <span class="pm-bank-row">
                        <span class="pm-bank-label">{{ __('payments.bank_account') }}</span>
                        <span class="pm-bank-value pm-bank-mono">{{ $bankAccount }}</span>
                    </span>
                    <span class="pm-bank-row">
                        <span class="pm-bank-label">{{ __('payments.bank_holder') }}</span>
                        <span class="pm-bank-value">{{ $bankHolder }}</span>
                    </span>
                </span>
            @else
                <span class="pm-option-desc pm-option-muted">{{ __('payments.bank_missing_body') }}</span>
            @endif
        </span>
    </label>

    @error('payment_method')
        <p class="form-error">{{ $message }}</p>
    @enderror
</fieldset>
