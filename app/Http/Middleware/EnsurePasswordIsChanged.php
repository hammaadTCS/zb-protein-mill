<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * BR-USR-08: a user with a temporary password (set by the Owner or by a server
 * command) must choose a new one before any other page opens.
 */
class EnsurePasswordIsChanged
{
    /** The security page, what it needs to change the password, and logout. */
    private const ALLOWED_ROUTES = [
        'security.edit',
        'user-password.update',
        'password.confirm',
        'password.confirm.store',
        'password.confirmation',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->must_change_password && ! $request->routeIs(self::ALLOWED_ROUTES)) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => __('Choose a new password to continue. The one you were given was temporary.'),
            ]);

            return redirect()->route('security.edit');
        }

        return $next($request);
    }
}
