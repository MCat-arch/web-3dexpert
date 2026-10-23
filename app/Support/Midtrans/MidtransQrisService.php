<?php

namespace App\Support\Midtrans;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MidtransQrisService
{
    public function isConfigured(): bool
    {
        return filled(config('midtrans.server_key'));
    }

    /**
     * Request a QRIS charge from the Midtrans Core API.
     *
     * @return array{qr_url: ?string, qr_string: ?string, transaction_id: ?string, raw: array<string, mixed>, error: ?string}
     */
    public function createCharge(Payment $payment, array $customerData = []): array
    {
        if (! $this->isConfigured()) {
            return $this->failure('Midtrans belum dikonfigurasi (MIDTRANS_SERVER_KEY kosong).');
        }

        $serverKey = (string) config('midtrans.server_key');
        $endpoint = rtrim((string) config('midtrans.api_url'), '/').'/charge';

        $minutes = $this->remainingMinutes($payment);

        $payload = [
            'payment_type' => 'qris',
            'transaction_details' => [
                'order_id' => $payment->order_id,
                'gross_amount' => (int) round((float) $payment->gross_amount),
            ],
            'qris' => [
                'acquirer' => 'gopay',
            ],
            // Keep Midtrans' own expiry aligned with the local 15-minute
            // window. The local job is still authoritative.
            'custom_expiry' => [
                'expiry_duration' => $minutes,
                'unit' => 'minute',
            ],
            'customer_details' => array_filter([
                'first_name' => $customerData['name'] ?? null,
                'email' => $customerData['email'] ?? null,
                'phone' => $customerData['phone'] ?? null,
            ]),
        ];

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic '.base64_encode($serverKey.':'),
            ])->timeout(15)->post($endpoint, $payload);

            if ($response->successful()) {
                $body = $response->json();
                $qrUrl = null;

                foreach ((array) ($body['actions'] ?? []) as $action) {
                    if (($action['name'] ?? null) === 'generate-qr-code') {
                        $qrUrl = $action['url'] ?? null;
                        break;
                    }
                }

                return [
                    'qr_url' => $qrUrl,
                    'qr_string' => $body['qr_string'] ?? null,
                    'transaction_id' => $body['transaction_id'] ?? null,
                    'raw' => is_array($body) ? $body : [],
                    'error' => null,
                ];
            }

            Log::error('Midtrans QRIS charge failed', [
                'order_id' => $payment->order_id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $this->failure('Midtrans menolak permintaan pembayaran (HTTP '.$response->status().').');
        } catch (\Throwable $e) {
            Log::error('Midtrans connection error', [
                'order_id' => $payment->order_id,
                'error' => $e->getMessage(),
            ]);

            return $this->failure('Tidak dapat terhubung ke Midtrans.');
        }
    }

    /**
     * Midtrans signs webhook notifications with:
     * sha512(order_id + status_code + gross_amount + server_key)
     */
    public function verifySignature(string $orderId, string $statusCode, string $grossAmount, string $signatureKey): bool
    {
        $serverKey = (string) config('midtrans.server_key');

        if ($serverKey === '' || $signatureKey === '') {
            return false;
        }

        $expected = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        return hash_equals($expected, $signatureKey);
    }

    private function remainingMinutes(Payment $payment): int
    {
        if ($payment->expires_at === null) {
            return max(1, (int) config('payments.expiry_minutes', 15));
        }

        $seconds = $payment->expires_at->getTimestamp() - now()->getTimestamp();

        return max(1, (int) ceil($seconds / 60));
    }

    /**
     * @return array{qr_url: null, qr_string: null, transaction_id: null, raw: array<string, mixed>, error: string}
     */
    private function failure(string $message): array
    {
        return [
            'qr_url' => null,
            'qr_string' => null,
            'transaction_id' => null,
            'raw' => [],
            'error' => $message,
        ];
    }
}
