<?php

namespace App\Http\Middleware;

use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The verification gate for the `customer` guard.
 *
 * Laravel's EnsureEmailIsVerified reads $request->user() from the DEFAULT
 * guard, which here is `web` -- so on a customer route it would see no user and
 * behave unpredictably. This reads the customer guard explicitly.
 *
 * This middleware is the single place the A20 decision lands: if verification
 * stops gating free-event enrollment (because it puts a mail provider on the
 * launch critical path), that is removing this middleware from one route group,
 * not reworking the feature.
 */
class EnsureCustomerEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $customer = $request->user('customer');

        if (! $customer) {
            return redirect()->to(Locale::route('customer.login'));
        }

        if (! $customer->hasVerifiedEmail()) {
            return redirect()->to(Locale::route('customer.verification.notice'));
        }

        return $next($request);
    }
}
