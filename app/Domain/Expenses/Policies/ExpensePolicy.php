<?php

declare(strict_types=1);

namespace App\Domain\Expenses\Policies;

use App\Domain\Expenses\Models\Expense;
use App\Models\User;

/** Spec §7: expense.view, .create, .update, .cancel. */
final class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('expense.view');
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->can('expense.view') && $expense->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('expense.create');
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->can('expense.update') && $expense->company_id === $user->company_id;
    }

    public function cancel(User $user, Expense $expense): bool
    {
        return $user->can('expense.cancel') && $expense->company_id === $user->company_id;
    }
}
