<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
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
        $customer = Customer::find($request->route('id'));

        if (! $customer) {
            abort(404);
        }

        // The signed URL proves the link came from us and has not expired;
        // this check proves it was issued for THIS account.
        $currentCustomer = $request->user('customer');
        if ($currentCustomer && (int) $currentCustomer->getKey() !== (int) $customer->getKey()) {
            abort(403);
        }

        if (! hash_equals((string) $request->route('hash'), sha1($customer->getEmailForVerification()))) {
            abort(403);
        }

        if (! $customer->hasVerifiedEmail()) {
            $customer->markEmailAsVerified();
            event(new Verified($customer));
        }

        // Auto-login customer if not already logged in
        if (! $currentCustomer) {
            auth('customer')->login($customer);
        }

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
