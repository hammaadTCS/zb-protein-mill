<?php

declare(strict_types=1);

namespace App\Domain\Expenses\Policies;

use App\Domain\Expenses\Models\ExpenseCategory;
use App\Models\User;

/** Spec §7: expense categories are set up with settings.manage (page 44). */
final class ExpenseCategoryPolicy
{
    public function manage(User $user): bool
    {
        return $user->can('settings.manage');
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, ExpenseCategory $category): bool
    {
        return $this->manage($user) && $category->company_id === $user->company_id;
    }

    public function deactivate(User $user, ExpenseCategory $category): bool
    {
        return $this->update($user, $category);
    }
}
