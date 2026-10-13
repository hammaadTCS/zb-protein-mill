<?php

declare(strict_types=1);

namespace App\Domain\OpeningBalances\Policies;

use App\Models\User;

/** Spec §7, BR-OPB-01: opening balances are entered only with opening.manage (Owner). */
final class OpeningBalancePolicy
{
    public function manage(User $user): bool
    {
        return $user->can('opening.manage');
    }
}
