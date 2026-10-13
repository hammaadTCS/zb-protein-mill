<?php

declare(strict_types=1);

namespace App\Domain\Alerts;

use App\Domain\Alerts\Providers\BankChangesToReview;
use App\Domain\Alerts\Providers\NewPartiesToReview;
use App\Models\User;

/** The registry of dashboard alerts. Each task adds its providers here. */
final class Alerts
{
    /** @var list<class-string<AlertProvider>> */
    public const PROVIDERS = [
        NewPartiesToReview::class,
        BankChangesToReview::class,
    ];

    /**
     * The alerts this user should act on: only those with something to show.
     *
     * @return list<array{key: string, label: string, count: int, route: string, severity: string}>
     */
    public static function for(User $user): array
    {
        $alerts = [];

        foreach (self::PROVIDERS as $class) {
            $provider = app($class);
            $count = $provider->count($user);

            if ($count > 0) {
                $alerts[] = [
                    'key' => $provider->key(),
                    'label' => $provider->label(),
                    'count' => $count,
                    'route' => $provider->route(),
                    'severity' => $provider->severity(),
                ];
            }
        }

        return $alerts;
    }
}
