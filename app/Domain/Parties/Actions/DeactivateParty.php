<?php

declare(strict_types=1);

namespace App\Domain\Parties\Actions;

use App\Domain\Parties\Enums\PartyStatus;
use App\Domain\Parties\Models\Party;
use App\Domain\Parties\SetPartyStatus;
use App\Models\User;

/**
 * BR-PTY-05: a party is never deleted, only made inactive. An inactive party cannot
 * be picked for new entries but stays in history and reports (BR-GEN-08).
 */
final class DeactivateParty
{
    public function handle(User $by, Party $party): Party
    {
        return SetPartyStatus::to(PartyStatus::Inactive, $by, $party);
    }
}
