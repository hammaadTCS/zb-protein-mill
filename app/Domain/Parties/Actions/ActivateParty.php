<?php

declare(strict_types=1);

namespace App\Domain\Parties\Actions;

use App\Domain\Parties\Enums\PartyStatus;
use App\Domain\Parties\Models\Party;
use App\Domain\Parties\SetPartyStatus;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** BR-PTY-05: an inactive party can be made active again, unless it was merged. */
final class ActivateParty
{
    public function handle(User $by, Party $party): Party
    {
        if ($party->merged_into_id !== null) {
            throw ValidationException::withMessages(['status' => __('This party was merged into another party.')]);
        }

        return SetPartyStatus::to(PartyStatus::Active, $by, $party);
    }
}
