<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Support\Midtrans\MidtransQrisService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Lazily asks Midtrans for a QRIS charge the first time a pending payment page
 * is opened. Keeping this out of checkout means a temporary gateway outage
 * cannot prevent the registration/payment row from being created.
 */
class EnsurePaymentCharge
{
    public function __invoke(Payment $payment): ?string
    {
        if (! $payment->isPending() || $payment->qrUrl() !== null) {
            return $payment->qrUrl();
        }

        if ($payment->expires_at !== null && $payment->expires_at->isPast()) {
            return null;
        }

        $service = app(MidtransQrisService::class);

        if (! $service->isConfigured()) {
            /*
             * Gateway belum bisa diakses. Jangan biarkan halaman pembayaran
             * buntu: alihkan order ini ke transfer bank manual dan beri
             * jendela validasi yang lebih panjang. Hanya dikonversi sekali
             * agar expires_at tidak terus diperpanjang setiap kali halaman
             * dibuka.
             *
             * Kode lama (tetap disimpan sebagai referensi):
             *
             * return null;
             */
            $this->markAsManual($payment);

            return $payment->qrUrl();
        }

        try {
            // Two tabs (or a refresh) must not create two charges for one
            // order id. Midtrans rejects the duplicate, but the UI should not
            // depend on that.
            return Cache::lock('midtrans-charge:'.$payment->getKey(), 20)->block(
                5,
                fn () => $this->charge($payment, $service),
            );
        } catch (Throwable $e) {
            Log::warning('Could not acquire charge lock for payment '.$payment->order_id, [
                'error' => $e->getMessage(),
            ]);

            return $payment->qrUrl();
        }
    }

    private function charge(Payment $payment, MidtransQrisService $service): ?string
    {
        $payment->refresh();

        if (! $payment->isPending() || $payment->qrUrl() !== null) {
            return $payment->qrUrl();
        }

        $payment->loadMissing('payable');
        $payable = $payment->payable;
        $customer = $payable?->payableCustomer();

        $result = $service->createCharge($payment, [
            'name' => $customer?->name,
            'email' => $customer?->email,
            'phone' => $customer?->phone,
        ]);

        if ($result['qr_url'] === null && $result['transaction_id'] === null) {
            // Keep the failure on the row for staff diagnostics, but allow the
            // next page load to retry instead of poisoning the payment.
            $payment->forceFill([
                'raw_response' => array_merge($payment->raw_response ?? [], [
                    'charge_error' => $result['error'] ?? 'Midtrans tidak mengembalikan QR.',
                    'charge_attempted_at' => now()->toIso8601String(),
                ]),
            ])->save();

            return null;
        }

        $payment->forceFill([
            'midtrans_transaction_id' => $result['transaction_id'],
            'raw_response' => array_merge($payment->raw_response ?? [], [
                'charge' => $result['raw'],
                'qr_url' => $result['qr_url'],
                'qr_string' => $result['qr_string'],
                'charged_at' => now()->toIso8601String(),
                'charge_error' => null,
            ]),
        ])->save();

        return $result['qr_url'];
    }

    /**
     * Switch a pending order to manual bank transfer and widen its window.
     *
     * Idempotent: once payment_type is bank_transfer the deadline is left
     * alone, so refreshing the page cannot postpone expiry forever.
     */
    private function markAsManual(Payment $payment): void
    {
        if ($payment->isManual()) {
            return;
        }

        $hours = max(1, (int) config('payments.manual_expiry_hours', 24));

        $payment->forceFill([
            'payment_type' => Payment::TYPE_BANK_TRANSFER,
            'expires_at' => now()->addHours($hours),
        ])->save();
    }
}
