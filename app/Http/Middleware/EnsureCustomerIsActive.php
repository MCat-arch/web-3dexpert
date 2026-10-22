<?php

namespace App\Http\Middleware;

use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deactivation must end an existing customer session, not just block a new
 * login. Runs on the customer guard explicitly so the staff guard is untouched.
 */
class EnsureCustomerIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $customer = $request->user('customer');

        if ($customer && ! $customer->is_active) {
            auth('customer')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return redirect()
                ->route(Locale::name('customer.login'))
                ->withErrors(['email' => __('customer.login_deactivated')]);
        }

        return $next($request);
    }
}
