<?php

declare(strict_types=1);

namespace App\Domain\Purchases\Policies;

use App\Domain\Purchases\Models\Purchase;
use App\Models\User;

/** Spec §7, conventions §9: purchase.view, .create, .update (edit and add rate), .cancel. */
final class PurchasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('purchase.view');
    }

    public function view(User $user, Purchase $purchase): bool
    {
        return $user->can('purchase.view') && $purchase->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('purchase.create');
    }

    public function update(User $user, Purchase $purchase): bool
    {
        return $user->can('purchase.update') && $purchase->company_id === $user->company_id;
    }

    public function cancel(User $user, Purchase $purchase): bool
    {
        return $user->can('purchase.cancel') && $purchase->company_id === $user->company_id;
    }
}
