<?php

namespace App\Http\Controllers\Payment;

use App\Actions\Payments\UploadPaymentProof;
use App\Contracts\Payable;
use App\Http\Controllers\Controller;
use App\Models\CourseEnrollment;
use App\Models\EventRegistration;
use App\Models\Payment;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Manual bank-transfer proof: upload by the owning customer, download by the
 * owner or by payment staff.
 *
 * The 'proofs' disk lives under storage_path with no public URL, so these
 * methods are the only path to the bytes. Staff access is gated by the same
 * AREA_PAYMENTS check as the Filament Payments resource, and is audited.
 */
class PaymentProofController extends Controller
{
    public function store(Request $request, string $order_id): RedirectResponse
    {
        $payment = Payment::query()->where('order_id', $order_id)->firstOrFail();
        $this->authorizeOwner($this->payableFor($payment));

        $validated = $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            app(UploadPaymentProof::class)(
                $payment,
                $validated['proof'],
                $validated['note'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->to(Locale::route('payments.show', ['order_id' => $payment->order_id]))
            ->with('status', __('payments.proof_uploaded_flash'));
    }

    /** The owning customer downloads the receipt they uploaded. */
    public function file(string $order_id): StreamedResponse
    {
        $payment = Payment::query()->where('order_id', $order_id)->firstOrFail();
        $this->authorizeOwner($this->payableFor($payment));

        return $this->download($payment);
    }

    /** Payment staff (admin/superadmin) downloads any receipt. */
    public function staff(Request $request, Payment $payment): StreamedResponse
    {
        $user = $request->user('web');

        abort_unless(
            $user instanceof User && $user->canAccessArea(User::AREA_PAYMENTS),
            403,
        );

        $response = $this->download($payment);

        // Customer account names and amounts live in these files; record who
        // opened them.
        app(AuditLogger::class)->log(
            'payment.proof_viewed',
            $payment,
            [],
            ['order_id' => $payment->order_id],
            $user,
            'Bukti pembayaran dibuka untuk pesanan '.$payment->order_id.'.',
        );

        return $response;
    }

    private function download(Payment $payment): StreamedResponse
    {
        abort_unless($payment->hasProof(), 404);

        $disk = Storage::disk(Payment::PROOF_DISK);
        $path = (string) $payment->payment_proof_path;

        abort_unless($disk->exists($path), 404);

        $name = $payment->payment_proof_original_name
            ?: 'bukti-'.$payment->order_id.'.'.pathinfo($path, PATHINFO_EXTENSION);

        return $disk->download($path, $name);
    }

    private function payableFor(Payment $payment): Payable
    {
        $payment->loadMissing('payable');
        $payable = $payment->payable;

        if (! $payable instanceof Payable) {
            abort(404);
        }

        if ($payable instanceof EventRegistration) {
            $payable->loadMissing(['event', 'customer']);
        } elseif ($payable instanceof CourseEnrollment) {
            $payable->loadMissing(['course', 'customer']);
        }

        return $payable;
    }

    private function authorizeOwner(Payable $payable): void
    {
        $viewer = auth('customer')->user();
        $owner = $payable->payableCustomer();

        if ($viewer === null || $owner === null || $viewer->getKey() !== $owner->getKey()) {
            abort(403);
        }
    }
}
