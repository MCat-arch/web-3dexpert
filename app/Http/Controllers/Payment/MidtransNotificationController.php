<?php

namespace App\Http\Controllers\Payment;

use App\Actions\Payments\ExpirePayment;
use App\Actions\Payments\RefundPayment;
use App\Actions\Payments\SettlePayment;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Support\Midtrans\MidtransQrisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Signed Midtrans HTTP notification endpoint.
 *
 * This is deliberately NOT under the locale groups and NOT CSRF-protected:
 * Midtrans posts here from its own servers. The SHA-512 signature is the
 * authentication mechanism.
 */
class MidtransNotificationController extends Controller
{
    public function __invoke(
        Request $request,
        MidtransQrisService $midtrans,
        SettlePayment $settle,
        ExpirePayment $expire,
    ): JsonResponse {
        $orderId = (string) $request->input('order_id', '');
        $statusCode = (string) $request->input('status_code', '');
        $grossAmount = (string) $request->input('gross_amount', '');
        $signature = (string) $request->input('signature_key', '');
        $transactionStatus = (string) $request->input('transaction_status', '');
        $fraudStatus = (string) $request->input('fraud_status', '');

        if ($orderId === '' || $statusCode === '' || $grossAmount === '' || $signature === '') {
            return response()->json(['message' => 'Payload notifikasi tidak lengkap.'], 400);
        }

        if (! $midtrans->isConfigured()) {
            return response()->json(['message' => 'Midtrans belum dikonfigurasi.'], 503);
        }

        if (! $midtrans->verifySignature($orderId, $statusCode, $grossAmount, $signature)) {
            Log::warning('Midtrans notification signature rejected', ['order_id' => $orderId]);

            return response()->json(['message' => 'Signature tidak valid.'], 403);
        }

        $payment = Payment::query()->where('order_id', $orderId)->first();

        if ($payment === null) {
            Log::warning('Midtrans notification for unknown order', ['order_id' => $orderId]);

            return response()->json(['message' => 'Pesanan tidak ditemukan.'], 404);
        }

        if (abs((float) $payment->gross_amount - (float) $grossAmount) > 0.01) {
            Log::warning('Midtrans notification amount mismatch', [
                'order_id' => $orderId,
                'expected' => (string) $payment->gross_amount,
                'received' => $grossAmount,
            ]);

            return response()->json(['message' => 'Nominal tidak sesuai.'], 403);
        }

        $payload = $request->only([
            'transaction_id', 'transaction_status', 'status_code', 'status_message',
            'fraud_status', 'payment_type', 'gross_amount', 'order_id', 'transaction_time',
            'settlement_time', 'expiry_time', 'signature_key', 'va_numbers', 'actions',
        ]);

        switch ($transactionStatus) {
            case 'settlement':
            case 'capture':
                if ($transactionStatus === 'capture' && ! in_array($fraudStatus, ['accept', ''], true)) {
                    $this->storePayload($payment, $payload);
                    break;
                }

                /*
                 * If our 15-minute sweep already expired the order, the seat
                 * is gone. Do not throw a 500 (Midtrans would retry forever);
                 * keep the gateway payload for staff to reconcile/refund.
                 */
                if (! $payment->isPending() && ! $payment->isSettled()) {
                    $this->storePayload($payment, array_merge($payload, [
                        'late_settlement' => true,
                        'received_after_status' => $payment->status,
                    ]));

                    Log::warning('Midtrans settlement arrived after local expiry/cancel', [
                        'order_id' => $orderId,
                        'status' => $payment->status,
                    ]);
                    break;
                }

                $settle($payment, $payload);
                break;

            case 'pending':
                $this->storePayload($payment, $payload);
                break;

            case 'expire':
                $expire($payment, true, Payment::STATUS_EXPIRED);
                break;

            case 'cancel':
                $expire($payment, true, Payment::STATUS_CANCELLED);
                break;

            case 'deny':
            case 'failure':
                $expire($payment, true, Payment::STATUS_FAILED);
                break;

            case 'refund':
                if ($payment->isSettled()) {
                    app(RefundPayment::class)($payment);
                } else {
                    $this->storePayload($payment, $payload);
                }
                break;

            // A partial refund does not cancel the entitlement. Keep the
            // gateway payload for staff reconciliation and leave access as-is.
            case 'partial_refund':
                $this->storePayload($payment, $payload);
                break;

            default:
                $this->storePayload($payment, $payload);
                break;
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storePayload(Payment $payment, array $payload): void
    {
        $payment->forceFill([
            'raw_response' => array_merge($payment->raw_response ?? [], $payload),
        ])->save();
    }
}
