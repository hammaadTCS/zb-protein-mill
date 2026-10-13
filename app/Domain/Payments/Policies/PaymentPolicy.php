<?php

declare(strict_types=1);

namespace App\Domain\Payments\Policies;

use App\Domain\Payments\Models\Payment;
use App\Models\User;

/** Spec §7: payment.view, .create, .update, .cancel and payment.approve. */
final class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payment.view');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->can('payment.view') && $payment->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('payment.create');
    }

    public function update(User $user, Payment $payment): bool
    {
        return $user->can('payment.update') && $payment->company_id === $user->company_id;
    }

    public function cancel(User $user, Payment $payment): bool
    {
        return $user->can('payment.cancel') && $payment->company_id === $user->company_id;
    }

    public function approve(User $user, Payment $payment): bool
    {
        return $user->can('payment.approve') && $payment->company_id === $user->company_id;
    }
}
