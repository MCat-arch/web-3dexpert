<?php

namespace App\Support\Midtrans;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MidtransQrisService
{
    /**
     * Memeriksa apakah Server Key sudah diisi di .env
     */
    public function isConfigured(): bool
    {
        return filled(config('midtrans.server_key'));
    }

    /**
     * Request generate QRIS ke Midtrans Core API (/v2/charge)
     *
     * @return array{qr_url: ?string, qr_string: ?string, transaction_id: ?string, raw: array}
     */
    public function createCharge(Payment $payment, array $customerData = []): array
    {
        if (! $this->isConfigured()) {
            // Fallback mode jika API Key belum diisi di .env agar aplikasi tidak error
            return [
                'qr_url'         => 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=' . urlencode(url('/kursus/pembayaran/' . $payment->order_id)),
                'qr_string'      => null,
                'transaction_id' => null,
                'raw'            => ['mode' => 'sandbox_simulated'],
            ];
        }

        $serverKey = config('midtrans.server_key');
        $endpoint = rtrim(config('midtrans.api_url'), '/') . '/charge';

        $payload = [
            'payment_type' => 'qris',
            'transaction_details' => [
                'order_id'     => $payment->order_id,
                'gross_amount' => (int) round((float) $payment->gross_amount),
            ],
            'qris' => [
                'acquirer' => 'gopay',
            ],
            'customer_details' => array_filter([
                'first_name' => $customerData['name'] ?? null,
                'email'      => $customerData['email'] ?? null,
                'phone'      => $customerData['phone'] ?? null,
            ]),
        ];

        try {
            $response = Http::withHeaders([
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($serverKey . ':'),
            ])->timeout(15)->post($endpoint, $payload);

            if ($response->successful()) {
                $body = $response->json();
                $qrUrl = null;

                if (! empty($body['actions'])) {
                    foreach ($body['actions'] as $action) {
                        if (($action['name'] ?? '') === 'generate-qr-code') {
                            $qrUrl = $action['url'];
                            break;
                        }
                    }
                }

                return [
                    'qr_url'         => $qrUrl,
                    'qr_string'      => $body['qr_string'] ?? null,
                    'transaction_id' => $body['transaction_id'] ?? null,
                    'raw'            => $body,
                ];
            }

            Log::error('Midtrans QRIS Charge Failed', ['response' => $response->body()]);
        } catch (\Throwable $e) {
            Log::error('Midtrans Connection Error: ' . $e->getMessage());
        }

        return [
            'qr_url'         => null,
            'qr_string'      => null,
            'transaction_id' => null,
            'raw'            => [],
        ];
    }

    /**
     * Verifikasi keamanan webhook notifikasi dari Midtrans (SHA-512)
     */
    public function verifySignature(string $orderId, string $statusCode, string $grossAmount, string $signatureKey): bool
    {
        $serverKey = config('midtrans.server_key');
        $expected = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        return hash_equals($expected, $signatureKey);
    }
}
