<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Providers;

use App\Domain\Alerts\AlertProvider;
use App\Domain\Reports\Queries\TrialBalance;
use App\Models\User;
use App\Support\Today;
use Database\Seeders\RolesAndPermissionsSeeder;

/** BR-LED-10: total debits differ from total credits, a software error (owner only). */
final class TrialBalanceError implements AlertProvider
{
    public function key(): string
    {
        return 'trial_balance_error';
    }

    public function count(User $user): int
    {
        if (! $user->hasRole(RolesAndPermissionsSeeder::OWNER)) {
            return 0;
        }

        return TrialBalance::asOf(Today::date())->balanced ? 0 : 1;
    }

    public function route(): string
    {
        return route('reports.trial-balance', [], false);
    }

    public function label(): string
    {
        return __('Trial balance error');
    }

    public function severity(): string
    {
        return 'danger';
    }
}
