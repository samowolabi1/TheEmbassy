<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Someone signed in with a temporary password chooses their own before doing anything else.
 */
class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user?->must_change_password && ! $request->routeIs('account.password.*', 'logout', 'admin.logout', 'vendor.logout')) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Choose a new password first.'], 403)
                : redirect()->route('account.password.edit');
        }

        return $next($request);
    }
}
