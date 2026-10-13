<?php

declare(strict_types=1);

namespace App\Domain\Transfers\Policies;

use App\Domain\Transfers\Models\Transfer;
use App\Models\User;

/** Spec §7: transfer.view, .create, .update, .cancel. */
final class TransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('transfer.view');
    }

    public function create(User $user): bool
    {
        return $user->can('transfer.create');
    }

    public function update(User $user, Transfer $transfer): bool
    {
        return $user->can('transfer.update') && $transfer->company_id === $user->company_id;
    }

    public function cancel(User $user, Transfer $transfer): bool
    {
        return $user->can('transfer.cancel') && $transfer->company_id === $user->company_id;
    }
}
