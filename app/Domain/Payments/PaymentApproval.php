<?php

declare(strict_types=1);

namespace App\Domain\Payments;

use App\Domain\Parties\Models\Party;
use App\Domain\Payments\Enums\ApprovalStatus;
use App\Domain\Payments\Enums\PaymentDirection;
use App\Domain\Payments\Models\Payment;
use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use App\Models\User;
use App\Support\Decimal;

/**
 * BR-PAY-06, BR-PAY-07, BR-PAY-10, BR-USR-01: whether a new or edited payment waits
 * for the Owner. Only "paid" payments by a user without `payment.approve` can wait.
 */
final class PaymentApproval
{
    /** Glossary §4 "Approval pending". */
    public const PENDING_MESSAGE = 'Saved. Waiting for owner approval. Balances will change after approval.';

    public static function statusFor(User $by, Party $party, PaymentDirection $direction, string $amount, ?int $exceptPaymentId = null): ApprovalStatus
    {
        if ($direction === PaymentDirection::Received || $by->can('payment.approve')) {
            return ApprovalStatus::NotRequired; // BR-PAY-10, BR-USR-01
        }

        $limit = Settings::get(SettingKeys::PAYMENT_APPROVAL_LIMIT);

        if (is_string($limit) && Decimal::compare($amount, $limit) >= 0) {
            return ApprovalStatus::Pending; // BR-PAY-06
        }

        $firstLimit = Settings::get(SettingKeys::NEW_PARTY_FIRST_PAYMENT_LIMIT);

        if (is_string($firstLimit) && Decimal::compare($amount, $firstLimit) >= 0 && ! self::hasPostedPaidPayment($party, $exceptPaymentId)) {
            return ApprovalStatus::Pending; // BR-PAY-07
        }

        return ApprovalStatus::NotRequired;
    }

    /** BR-PAY-07: an earlier "paid" payment to this party that was posted. */
    private static function hasPostedPaidPayment(Party $party, ?int $exceptPaymentId): bool
    {
        return Payment::query()
            ->where('party_id', $party->id)
            ->where('direction', PaymentDirection::Paid->value)
            ->where('status', 'active')
            ->whereIn('approval_status', [ApprovalStatus::NotRequired->value, ApprovalStatus::Approved->value])
            ->whereNotNull('journal_entry_id')
            ->when($exceptPaymentId !== null, fn ($q) => $q->whereKeyNot($exceptPaymentId))
            ->exists();
    }
}
