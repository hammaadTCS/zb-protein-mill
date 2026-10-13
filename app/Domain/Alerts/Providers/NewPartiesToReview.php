<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Providers;

use App\Domain\Alerts\AlertProvider;
use App\Domain\Parties\Models\Party;
use App\Models\User;

/** BR-PTY-06: parties the Accountant added, until the Owner marks them reviewed (owner only). */
final class NewPartiesToReview implements AlertProvider
{
    public function key(): string
    {
        return 'new_parties_to_review';
    }

    public function count(User $user): int
    {
        return $user->can('party.review') ? Party::query()->where('needs_review', true)->count() : 0;
    }

    public function route(): string
    {
        return route('parties.index', ['review' => 'new'], false);
    }

    public function label(): string
    {
        return __('New parties to review');
    }

    public function severity(): string
    {
        return 'warning';
    }
}
