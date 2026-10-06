<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Legacy edit.php and editkarir.php gated on nothing but
 * isset($_SESSION["user_id"]) -- and because reg.php was open to the public,
 * that meant any visitor could grant themselves full write access.
 *
 * Here an authenticated user must additionally be active and hold a role.
 */
class EnsureUserCanManageContent
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->canManageContent()) {
            abort(403);
        }

        return $next($request);
    }
}
