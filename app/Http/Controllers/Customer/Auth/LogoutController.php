<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Http\Controllers\Controller;
use App\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    /**
     * POST only. Legacy logout.php was a GET, which meant any page could log a
     * visitor out with an <img src>, and a prefetching browser could do it by
     * accident (guide.md section 10.4: no state-changing GET routes).
     */
    public function __invoke(Request $request): RedirectResponse
    {
        auth('customer')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to(Locale::route('home'));
    }
}
