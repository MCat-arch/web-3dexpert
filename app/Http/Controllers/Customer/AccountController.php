<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The customer's account page.
 *
 * Intentionally close to empty: it is the mount point later phases hang their
 * panels on -- "my events" (P5), "my applications" (P6), "my courses" (P4).
 * Shipping the shell now means each of those adds a section rather than
 * inventing its own navigation.
 */
class AccountController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('customer.account', [
            'customer' => $request->user('customer'),
        ]);
    }
}
