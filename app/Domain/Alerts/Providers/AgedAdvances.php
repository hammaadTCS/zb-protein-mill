<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Providers;

use App\Domain\Alerts\AlertProvider;
use App\Domain\Ledger\LedgerBalance;
use App\Domain\Parties\Enums\PartyType;
use App\Domain\Parties\Models\Party;
use App\Domain\Reports\Ageing;
use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use App\Models\User;
use App\Support\Today;

/** BR-CHK-06: advances older than `advance_age_days` (red). */
final class AgedAdvances implements AlertProvider
{
    public function key(): string
    {
        return 'aged_advances';
    }

    public function count(User $user): int
    {
        $limit = Settings::get(SettingKeys::ADVANCE_AGE_DAYS);

        if (! $user->can('party.view') || ! is_int($limit)) {
            return 0;
        }

        $today = Today::date();

        $parties = Party::query()->whereIn('type', [PartyType::Supplier->value, PartyType::Buyer->value])->where('status', 'active')->get();
        $nets = LedgerBalance::partyNets(array_values($parties->modelKeys()));

        // Only a party whose balance is an advance has an open advance to age (spec §12.6), so
        // the ageing runs for those alone.
        return $parties
            ->filter(fn (Party $party): bool => $party->balanceFrom($nets[$party->id] ?? [], '0.000')->words === 'Advance')
            ->filter(fn (Party $party): bool => (Ageing::advanceAgeDays($party, $today) ?? 0) > $limit)
            ->count();
    }

    public function route(): string
    {
        return route('parties.index', [], false);
    }

    public function label(): string
    {
        return __('Aged advances');
    }

    public function severity(): string
    {
        return 'danger';
    }
}
