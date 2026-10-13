<?php

declare(strict_types=1);

namespace App\Domain\Adjustments\Policies;

use App\Domain\Adjustments\Models\Adjustment;
use App\Models\User;

/** Spec §7: adjustment.view, .create, .cancel (no edit, BR-ADJ-06). */
final class AdjustmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('adjustment.view');
    }

    public function create(User $user): bool
    {
        return $user->can('adjustment.create');
    }

    public function cancel(User $user, Adjustment $adjustment): bool
    {
        return $user->can('adjustment.cancel') && $adjustment->company_id === $user->company_id;
    }
}
