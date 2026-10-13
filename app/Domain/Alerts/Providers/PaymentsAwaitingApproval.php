<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Providers;

use App\Domain\Alerts\AlertProvider;
use App\Domain\Payments\Enums\ApprovalStatus;
use App\Domain\Payments\Models\Payment;
use App\Models\User;

/** BR-PAY-08: payments waiting for the Owner's approval (owner only). */
final class PaymentsAwaitingApproval implements AlertProvider
{
    public function key(): string
    {
        return 'payments_awaiting_approval';
    }

    public function count(User $user): int
    {
        return $user->can('payment.approve')
            ? Payment::query()->where('status', 'active')->where('approval_status', ApprovalStatus::Pending->value)->count()
            : 0;
    }

    public function route(): string
    {
        return route('approvals.index', [], false);
    }

    public function label(): string
    {
        return __('Payments awaiting approval');
    }

    public function severity(): string
    {
        return 'warning';
    }
}
