<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Http\Controllers\Controller;
use App\Support\Locale;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Password reset for the `customer` guard.
 *
 * Every call passes through Password::broker('customers') so tokens come from
 * customer_password_reset_tokens. Using the default broker here would issue
 * tokens into the shared table, where an address present in both `users` and
 * `customers` becomes a staff-account takeover (see config/auth.php).
 */
class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('customer.auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::broker('customers')->sendResetLink($request->only('email'));

        // Always the same response, whether or not the address exists.
        // Reporting "no such account" turns this form into a membership
        // oracle -- the same reasoning as P7's uniform negative response.
        return back()->with('status', __('customer.forgot_sent'));
    }

    public function reset(Request $request, string $token): View
    {
        return view('customer.auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::broker('customers')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($customer, string $password) {
                $customer->forceFill(['password' => $password])->save();

                // Invalidates "remember me" cookies issued before the reset,
                // so a stolen cookie does not survive the password change.
                $customer->setRememberToken(Str::random(60));
                $customer->save();

                event(new PasswordReset($customer));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return redirect()
            ->to(Locale::route('customer.login'))
            ->with('status', __('customer.reset_done'));
    }
}
