<?php

declare(strict_types=1);

namespace App\Domain\Partners\Policies;

use App\Domain\Partners\Models\PartnerTransaction;
use App\Models\User;

/** Spec §7, BR-PTN-03: partner.view to see, partner.manage to record (Owner by default, O-19). */
final class PartnerTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('partner.view');
    }

    public function create(User $user): bool
    {
        return $user->can('partner.manage');
    }

    public function update(User $user, PartnerTransaction $entry): bool
    {
        return $user->can('partner.manage') && $entry->company_id === $user->company_id;
    }

    public function cancel(User $user, PartnerTransaction $entry): bool
    {
        return $user->can('partner.manage') && $entry->company_id === $user->company_id;
    }
}
