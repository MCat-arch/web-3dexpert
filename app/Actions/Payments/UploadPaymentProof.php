<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Support\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Stores a customer's transfer receipt on the private 'proofs' disk.
 *
 * The file never touches the public disk: staff read it through
 * PaymentProofController, which authorises first. Uploading is only allowed
 * while the payment is still pending, and a re-upload replaces the previous
 * path (the old file is removed to avoid unreferenced PII on disk).
 */
class UploadPaymentProof
{
    public function __invoke(Payment $payment, UploadedFile $file, ?string $note = null): Payment
    {
        return DB::transaction(function () use ($payment, $file, $note) {
            $locked = Payment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isPending()) {
                throw new InvalidArgumentException(__('payments.proof_not_pending'));
            }

            $extension = strtolower($file->getClientOriginalExtension() ?: (string) $file->extension());
            $path = $file->storeAs('bukti', Str::uuid()->toString().'.'.$extension, Payment::PROOF_DISK);

            if ($path === false) {
                throw new InvalidArgumentException(__('payments.proof_upload_failed'));
            }

            $previousPath = $locked->payment_proof_path;
            $hours = max(1, (int) config('payments.manual_expiry_hours', 24));

            $locked->forceFill([
                'payment_type' => Payment::TYPE_BANK_TRANSFER,
                'payment_proof_path' => $path,
                'payment_proof_original_name' => $file->getClientOriginalName(),
                'payment_proof_note' => $note,
                'payment_proof_uploaded_at' => now(),
                // Give staff a full validation window from the upload, not
                // from the moment checkout started.
                'expires_at' => now()->addHours($hours),
            ])->save();

            if (filled($previousPath) && $previousPath !== $path) {
                Storage::disk(Payment::PROOF_DISK)->delete($previousPath);
            }

            app(AuditLogger::class)->log(
                'payment.proof_uploaded',
                $locked,
                filled($previousPath) ? ['payment_proof' => 'replaced'] : [],
                [
                    'order_id' => $locked->order_id,
                    'file' => basename($path),
                    'has_note' => filled($note),
                ],
                null,
                'Bukti pembayaran diunggah untuk pesanan '.$locked->order_id.'.',
            );

            return $locked;
        });
    }
}
