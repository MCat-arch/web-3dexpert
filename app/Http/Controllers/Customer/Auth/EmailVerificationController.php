<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Http\Controllers\Controller;
use App\Support\Locale;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Email verification for the `customer` guard.
 *
 * Why it matters beyond hygiene: event capacity and job-application pipelines
 * are both pollutable by unverified addresses, and receipts sent to a typo'd
 * address are support load nobody can resolve.
 *
 * Note for O6: requiring verification *before* enrollment puts an external
 * mail provider on the launch critical path. Assumption A20 proposes relaxing
 * it for free events. The gate lives in the `verified.customer` middleware, so
 * relaxing it is a routing change, not a rewrite.
 */
class EmailVerificationController extends Controller
{
    public function notice(Request $request): RedirectResponse|View
    {
        if ($request->user('customer')->hasVerifiedEmail()) {
            return redirect()->to(Locale::route('customer.account'));
        }

        return view('customer.auth.verify-email');
    }

    public function verify(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');

        // The signed URL proves the link came from us and has not expired;
        // this check proves it was issued for THIS account, so a valid link
        // cannot be replayed against a different signed-in customer.
        if (! hash_equals((string) $request->route('id'), (string) $customer->getKey())) {
            abort(403);
        }

        if (! hash_equals((string) $request->route('hash'), sha1($customer->getEmailForVerification()))) {
            abort(403);
        }

        if ($customer->hasVerifiedEmail()) {
            return redirect()
                ->to(Locale::route('customer.account'))
                ->with('status', __('customer.verify_done'));
        }

        $customer->markEmailAsVerified();

        event(new Verified($customer));

        return redirect()
            ->to(Locale::route('customer.account'))
            ->with('status', __('customer.verify_done'));
    }

    public function resend(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');

        if ($customer->hasVerifiedEmail()) {
            return redirect()->to(Locale::route('customer.account'));
        }

        $customer->sendEmailVerificationNotification();

        return back()->with('status', __('customer.verify_resent'));
    }
}
