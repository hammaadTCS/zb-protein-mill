<?php

declare(strict_types=1);

namespace App\Domain\Alerts;

use App\Domain\Alerts\Providers\BankChangesToReview;
use App\Domain\Alerts\Providers\BuyersOverCreditLimit;
use App\Domain\Alerts\Providers\NegativeCashBalance;
use App\Domain\Alerts\Providers\NewPartiesToReview;
use App\Domain\Alerts\Providers\PaymentsAwaitingApproval;
use App\Domain\Alerts\Providers\RatePendingAged;
use App\Domain\Alerts\Providers\TrialBalanceError;
use App\Models\User;

/** The registry of dashboard alerts. Each task adds its providers here. */
final class Alerts
{
    /** @var list<class-string<AlertProvider>> */
    public const PROVIDERS = [
        // In the order of spec §11.3.
        RatePendingAged::class,
        PaymentsAwaitingApproval::class,
        NewPartiesToReview::class,
        BankChangesToReview::class,
        BuyersOverCreditLimit::class,
        NegativeCashBalance::class,
        TrialBalanceError::class,
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
