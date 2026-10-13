<?php

namespace App\Http\Middleware;

use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use App\Models\User;
use Closure;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * BR-SEC-09: when `owner_two_factor_required` is on, an Owner without confirmed
 * two-step login can only use the security page (and what turning it on needs).
 */
class EnsureOwnerTwoFactor
{
    /** The security page, the Fortify routes that turn two-step login on, and logout. */
    private const ALLOWED_ROUTES = [
        'security.edit',
        'user-password.update',
        'password.confirm',
        'password.confirm.store',
        'password.confirmation',
        'two-factor.enable',
        'two-factor.confirm',
        'two-factor.qr-code',
        'two-factor.secret-key',
        'two-factor.recovery-codes',
        'two-factor.regenerate-recovery-codes',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user instanceof User
            && $user->two_factor_confirmed_at === null
            && ! $request->routeIs(self::ALLOWED_ROUTES)
            && $user->hasRole(RolesAndPermissionsSeeder::OWNER)
            && Settings::get(SettingKeys::OWNER_TWO_FACTOR_REQUIRED) === true
        ) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => __('Turn on two-step login to continue. It is required for owners.'),
            ]);

            return redirect()->route('security.edit');
        }

        return $next($request);
    }
}
