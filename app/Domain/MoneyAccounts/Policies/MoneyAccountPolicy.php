<?php

declare(strict_types=1);

namespace App\Domain\MoneyAccounts\Policies;

use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Models\User;

/** Spec §7: account.view to see balances; settings.manage to set accounts up. */
final class MoneyAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('account.view');
    }

    public function view(User $user, MoneyAccount $account): bool
    {
        return $user->can('account.view') && $account->company_id === $user->company_id;
    }

    /** Page 46, Accounts setup. */
    public function manage(User $user): bool
    {
        return $user->can('settings.manage');
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, MoneyAccount $account): bool
    {
        return $this->manage($user) && $account->company_id === $user->company_id;
    }

    public function deactivate(User $user, MoneyAccount $account): bool
    {
        return $this->update($user, $account);
    }
}
