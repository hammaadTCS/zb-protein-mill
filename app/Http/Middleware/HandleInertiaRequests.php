<?php

namespace App\Http\Middleware;

use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            // conventions §10: only what the screens need; never secrets or other users' data.
            'auth' => [
                'user' => $user instanceof User ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames()->values()->all(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
                ] : null,
            ],
            // Printed reports show the company name (card T6.9).
            'company_name' => $user instanceof User ? Settings::get(SettingKeys::COMPANY_NAME) : null,
            'settings' => [
                'formats' => $user instanceof User ? self::formats() : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Staging shows "TEST COPY – entries here are not real." on every page.
            'app' => [
                'env_name' => config('app.env_name'),
            ],
        ];
    }

    /**
     * Display formats (O-14 to O-18). Null values mean setup is not complete yet.
     *
     * @return array<string, string|int|bool|null>
     */
    private static function formats(): array
    {
        return [
            'kg_decimals' => Settings::get(SettingKeys::KG_DECIMALS),
            'rate_decimals' => Settings::get(SettingKeys::RATE_DECIMALS),
            'amount_rounding' => Settings::get(SettingKeys::AMOUNT_ROUNDING),
            'number_format' => Settings::get(SettingKeys::NUMBER_FORMAT),
            'date_format' => Settings::get(SettingKeys::DATE_FORMAT),
        ];
    }
}
