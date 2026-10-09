@extends('layouts.app')

@section('title', __('courses.payment_instruction_title') . ' | ' . $payment->order_id)

@section('content')
    <div class="page-banner">
        <div class="container">
            <h1 class="page-banner-title">{{ __('courses.payment_instruction_title') }}</h1>
        </div>
    </div>

    <div class="container" style="max-width: 780px; padding-bottom: 70px;">
        <div class="card-light" style="padding: 32px;">
            <div
                style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; padding-bottom: 20px; border-bottom: 1px solid var(--line-light);">
                <div>
                    <span
                        style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); display: block;">{{ __('courses.order_id') }}</span>
                    <strong style="font-size: 1.25rem; font-family: monospace;">{{ $payment->order_id }}</strong>
                </div>

                <div>
                    @if ($payment->isSettled())
                        <span class="badge badge-success">✓ Lunas (Berhasil)</span>
                    @else
                        <span class="badge badge-amber">⏳ {{ __('courses.pending_payment') }}</span>
                    @endif
                </div>
            </div>

            {{-- Summary amount --}}
            <div
                style="margin: 24px 0; background: #fafafa; border: 1px solid var(--line-light); border-radius: var(--radius-card); padding: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <span
                        style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); display: block;">{{ __('courses.total_payment') }}</span>
                    <div style="font-size: 1.85rem; font-weight: 700; color: var(--text);">
                        Rp {{ number_format((float) $payment->gross_amount, 0, ',', '.') }}
                    </div>
                </div>

                <div>
                    <span
                        style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); display: block;">{{ __('courses.payment_due') }}</span>
                    <div style="font-size: 0.95rem; font-weight: 600; color: var(--text); margin-top: 6px;">
                        {{ $payment->expires_at ? $payment->expires_at->translatedFormat('l, j F Y H:i') : '24 Jam' }} WIB
                    </div>
                </div>
            </div>

            @if ($payment->isSettled())
                <div
                    style="background: var(--success-bg); border: 1px solid rgba(16,185,129,0.3); border-radius: var(--radius-card); padding: 24px; text-align: center; margin-bottom: 24px;">
                    <div style="font-size: 2rem; margin-bottom: 8px;">🎉</div>
                    <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--success); margin-bottom: 6px;">
                        {{ __('courses.payment_success_message') }}</h3>
                    <p style="font-size: 0.95rem; color: var(--text); margin-bottom: 20px;">Selamat! Akses kursus Anda telah
                        aktif.</p>

                    @if ($enrollment)
                        <a href="{{ $lroute('courses.player.show', ['course' => $enrollment->course->slug]) }}"
                            class="btn btn-primary btn-lg">
                            {{ __('courses.start_learning') }} &rarr;
                        </a>
                    @endif
                </div>
            @else
                <div style="margin-bottom: 30px;">
                    <h3 style="font-size: 1.15rem; font-weight: 600; margin-bottom: 14px;">Petunjuk Pembayaran Transfer / QRIS:
                    </h3>
                    <div
                        style="background: #ffffff; border: 1px solid var(--line-light); border-radius: var(--radius-card); padding: 16px; margin-bottom: 12px;">
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px;">Bank Transfer Virtual
                            Account (BCA / Mandiri / BNI):</p>
                        <p style="font-family: monospace; font-size: 1.15rem; font-weight: 700; margin: 0;">8801 2893 0192 8301
                        </p>
                    </div>
                    <ul
                        style="list-style: decimal; padding-left: 20px; display: flex; flex-direction: column; gap: 8px; font-size: 0.9rem; color: var(--text-muted);">
                        <li>Buka aplikasi m-Banking atau e-Wallet favorit Anda.</li>
                        <li>Pilih menu Transfer Virtual Account atau Scan QRIS.</li>
                        <li>Masukkan nominal tepat <strong>Rp
                                {{ number_format((float) $payment->gross_amount, 0, ',', '.') }}</strong>.</li>
                        <li>Konfirmasi pembayaran dan simpan bukti transaksi Anda.</li>
                    </ul>
                </div>

                {{-- Simulation Button (development sandbox) --}}
                <div style="padding-top: 20px; border-top: 1px solid var(--line-light); text-align: center;">
                    <form method="POST" action="{{ $lroute('courses.payment.simulate', ['orderId' => $payment->order_id]) }}">
                        @csrf
                        <button type="submit" class="btn btn-outline btn-block"
                            style="color: var(--text); border-color: var(--gold);">
                            ⚡ Simulasi Pembayaran Berhasil (Test Sandbox)
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
@endsection