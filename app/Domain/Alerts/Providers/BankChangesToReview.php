<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Providers;

use App\Domain\Alerts\AlertProvider;
use App\Domain\Parties\Models\Party;
use App\Models\User;

/** BR-PTY-07: bank details the Accountant changed, until the Owner marks them reviewed (owner only). */
final class BankChangesToReview implements AlertProvider
{
    public function key(): string
    {
        return 'bank_changes_to_review';
    }

    public function count(User $user): int
    {
        return $user->can('party.review') ? Party::query()->where('bank_change_needs_review', true)->count() : 0;
    }

    public function route(): string
    {
        return route('parties.index', ['review' => 'bank'], false);
    }

    public function label(): string
    {
        return __('Bank detail changes to review');
    }

    public function severity(): string
    {
        return 'warning';
    }
}
