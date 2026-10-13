<?php

declare(strict_types=1);

namespace App\Domain\Parties\Policies;

use App\Domain\Parties\Models\Party;
use App\Models\User;

/** Spec §7: party.view, party.create, party.update, party.review, party.merge. */
final class PartyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('party.view');
    }

    public function view(User $user, Party $party): bool
    {
        return $user->can('party.view') && $party->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('party.create');
    }

    public function update(User $user, Party $party): bool
    {
        return $user->can('party.update') && $party->company_id === $user->company_id;
    }

    /** Make inactive or active again (BR-PTY-05). */
    public function changeStatus(User $user, Party $party): bool
    {
        return $this->update($user, $party);
    }

    public function review(User $user, Party $party): bool
    {
        return $user->can('party.review') && $party->company_id === $user->company_id;
    }

    public function merge(User $user): bool
    {
        return $user->can('party.merge');
    }
}
