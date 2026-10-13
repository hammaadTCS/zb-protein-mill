<?php

declare(strict_types=1);

namespace App\Domain\Items\Policies;

use App\Domain\Items\Models\Item;
use App\Models\User;

/** Spec §7: items are set up by users with settings.manage (page 45). */
final class ItemPolicy
{
    public function manage(User $user): bool
    {
        return $user->can('settings.manage');
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, Item $item): bool
    {
        return $this->manage($user) && $item->company_id === $user->company_id;
    }

    public function deactivate(User $user, Item $item): bool
    {
        return $this->update($user, $item);
    }
}
