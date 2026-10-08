<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\LoginRequest;
use App\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('customer.auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');

        if (! auth('customer')->attempt($credentials, $request->boolean('remember'))) {
            // One message for a wrong email and a wrong password alike --
            // telling them which was wrong confirms whether an address is
            // registered.
            throw ValidationException::withMessages([
                'email' => __('customer.login_failed'),
            ]);
        }

        // Deactivation is checked after authentication so the message is only
        // shown to someone who proved they own the account.
        if (! auth('customer')->user()->is_active) {
            auth('customer')->logout();
            $request->session()->invalidate();

            throw ValidationException::withMessages([
                'email' => __('customer.login_deactivated'),
            ]);
        }

        // Stops session fixation: an attacker who planted a session id cannot
        // ride it through the login.
        $request->session()->regenerate();

        auth('customer')->user()->forceFill(['last_login_at' => now()])->saveQuietly();

        return redirect()->intended(Locale::route('customer.account'));
    }
}
