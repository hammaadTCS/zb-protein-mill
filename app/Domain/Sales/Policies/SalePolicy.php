<?php

declare(strict_types=1);

namespace App\Domain\Sales\Policies;

use App\Domain\Sales\Models\Sale;
use App\Models\User;

/** Spec §7, conventions §9: sale.view, .create, .update (edit and add rate), .cancel. */
final class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sale.view');
    }

    public function view(User $user, Sale $sale): bool
    {
        return $user->can('sale.view') && $sale->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('sale.create');
    }

    public function update(User $user, Sale $sale): bool
    {
        return $user->can('sale.update') && $sale->company_id === $user->company_id;
    }

    public function cancel(User $user, Sale $sale): bool
    {
        return $user->can('sale.cancel') && $sale->company_id === $user->company_id;
    }
}
