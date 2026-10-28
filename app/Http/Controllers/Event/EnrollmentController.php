<?php

namespace App\Http\Controllers\Event;

use App\Actions\Events\CancelEventRegistration;
use App\Actions\Events\EnrollInEvent;
use App\Exceptions\EnrollmentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Event\EnrollRequest;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EnrollmentController extends Controller
{
    public function store(EnrollRequest $request, Event $event, EnrollInEvent $enroll): RedirectResponse
    {
        try {
            $registration = $enroll(
                $event,
                $request->user('customer'),
                $request->attendee(),
                $request->input('payment_method', 'qris'),
            );
        } catch (EnrollmentException $e) {
            // Expected business outcomes -- full, closed, already registered --
            // are flashed, not raised as faults.
            return back()->with('error', $e->userMessage());
        }

        if (! $event->isFree() && $registration->payment !== null) {
            return redirect()
                ->to(Locale::route('payments.show', ['order_id' => $registration->payment->order_id]))
                ->with('status', __('events.payment_required', [
                    'minutes' => (int) config('payments.expiry_minutes', 15),
                ]));
        }

        return redirect()
            ->to(Locale::route('events.show', ['event' => $event->slug]))
            ->with('status', __('events.enrolled', ['code' => $registration->ticket_code]));
    }

    public function destroy(
        Request $request,
        EventRegistration $registration,
        CancelEventRegistration $cancel,
    ): RedirectResponse {
        /*
         * Gate::forUser, never Gate::authorize or $this->authorize.
         *
         * Both of those resolve the user from the DEFAULT guard, which is
         * `web` -- so on a customer route the policy would receive a User or
         * nothing at all, and EventRegistrationPolicy::cancel() types its
         * first argument as Customer. The check would fail closed for the real
         * owner, and the bug would present as "cancel does nothing".
         */
        Gate::forUser($request->user('customer'))->authorize('cancel', $registration);

        $cancel($registration);

        return back()->with('status', __('events.cancelled'));
    }
}
