<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Providers;

use App\Domain\Alerts\AlertProvider;
use App\Domain\Ledger\LedgerBalance;
use App\Domain\Parties\Enums\PartyType;
use App\Domain\Parties\Models\Party;
use App\Models\User;
use App\Support\Decimal;

/** Buyers who owe us more than their credit limit (spec §11.3). */
final class BuyersOverCreditLimit implements AlertProvider
{
    public function key(): string
    {
        return 'buyers_over_credit_limit';
    }

    public function count(User $user): int
    {
        if (! $user->can('party.view')) {
            return 0;
        }

        $buyers = Party::query()->where('type', PartyType::Buyer->value)->where('status', 'active')->whereNotNull('credit_limit')->get(['id', 'credit_limit']);
        $nets = LedgerBalance::partyNets(array_values($buyers->map(fn (Party $buyer): int => $buyer->id)->all()));

        return $buyers->filter(function (Party $buyer) use ($nets): bool {
            $owes = $nets[$buyer->id]['1200'] ?? '0.00'; // debit minus credit: what the buyer owes us

            return Decimal::compare((string) $owes, (string) $buyer->credit_limit) > 0;
        })->count();
    }

    public function route(): string
    {
        return route('parties.index', ['type' => 'buyer'], false);
    }

    public function label(): string
    {
        return __('Buyers over credit limit');
    }

    public function severity(): string
    {
        return 'danger';
    }
}
