<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Providers;

use App\Domain\Alerts\AlertProvider;
use App\Domain\Parties\Enums\PartyType;
use App\Domain\Parties\Models\Party;
use App\Domain\Reports\Ageing;
use App\Models\User;
use App\Support\Decimal;
use App\Support\Today;

/** Spec §12.6: buyers with sales unpaid past their payment terms. */
final class OverdueBuyers implements AlertProvider
{
    public function key(): string
    {
        return 'overdue_buyers';
    }

    public function count(User $user): int
    {
        if (! $user->can('party.view')) {
            return 0;
        }

        $today = Today::date();

        return Party::query()->where('type', PartyType::Buyer->value)->where('status', 'active')->whereNotNull('payment_terms_days')->get()
            ->filter(fn (Party $buyer): bool => Decimal::isPositive(Ageing::overdueFor($buyer, $today)))
            ->count();
    }

    public function route(): string
    {
        return route('parties.index', ['type' => 'buyer'], false);
    }

    public function label(): string
    {
        return __('Overdue buyers');
    }

    public function severity(): string
    {
        return 'danger';
    }
}
