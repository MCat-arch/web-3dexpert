<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\RegisterRequest;
use App\Models\Customer;
use App\Support\Locale;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Public registration for the `customer` guard.
 *
 * Legacy reg.php was open self-registration into a table whose accounts were
 * all effectively superadmin. This writes to `customers`, which has no path to
 * the admin panel at all (decision D1).
 */
class RegisterController extends Controller
{
    public function create(): View
    {
        return view('customer.auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $customer = DB::transaction(fn () => Customer::create($request->validated()));

        // Dispatched after commit: the listener queues the verification mail,
        // and a queued job must never be able to pick up a row that a rollback
        // is about to remove.
        event(new Registered($customer));

        auth('customer')->login($customer);

        $request->session()->regenerate();

        return redirect()->to(Locale::route('customer.verification.notice'));
    }
}
