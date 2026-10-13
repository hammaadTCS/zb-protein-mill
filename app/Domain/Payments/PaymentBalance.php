<?php

declare(strict_types=1);

namespace App\Domain\Payments;

use App\Domain\Ledger\LedgerBalance;
use App\Domain\Parties\Models\Party;
use App\Domain\Parties\PartyBalance;
use App\Domain\Payments\Enums\PaymentDirection;
use App\Support\Decimal;
use App\Support\NumberFormat;

/**
 * BR-PAY-05 and BR-PAY-11: the party's balance now and after a payment or receipt,
 * and the information message when it is more than what is owed in that direction.
 */
final class PaymentBalance
{
    /**
     * Net (debit minus credit) on the party's control account.
     *
     * @param  int|null  $exceptJournalId  leave out this journal (the payment being edited)
     */
    public static function net(Party $party, ?int $exceptJournalId = null): string
    {
        $code = (string) $party->type->controlAccountCode();
        $net = LedgerBalance::byCode($code, null, $party->id);

        if ($exceptJournalId !== null) {
            $net = Decimal::sub($net, LedgerBalance::journalNet($exceptJournalId, $code, $party->id));
        }

        return $net;
    }

    /** A payment (paid) raises the net by the amount; a receipt lowers it. */
    public static function after(string $net, PaymentDirection $direction, string $amount): string
    {
        return $direction === PaymentDirection::Paid ? Decimal::add($net, $amount) : Decimal::sub($net, $amount);
    }

    /**
     * @return array{current: PartyBalance, after: PartyBalance, info: string|null}
     */
    public static function preview(Party $party, PaymentDirection $direction, string $amount, ?int $exceptJournalId = null): array
    {
        $net = self::net($party, $exceptJournalId);

        return [
            'current' => $party->balanceOfNet($net),
            'after' => $party->balanceOfNet(self::after($net, $direction, $amount)),
            'info' => self::overpayment($net, $direction, $amount),
        ];
    }

    /**
     * BR-PAY-11: "This is more than the balance. The extra Rs {amount} will show as an
     * advance." when paying more than we owe, or receiving more than they owe us.
     */
    public static function overpayment(string $net, PaymentDirection $direction, string $amount): ?string
    {
        // What is owed in this direction: we owe on a credit net; they owe us on a debit net.
        $owed = $direction === PaymentDirection::Paid ? Decimal::negate($net) : $net;
        $owed = Decimal::isPositive($owed) ? $owed : '0.00';
        $extra = Decimal::sub($amount, $owed);

        if (! Decimal::isPositive($extra)) {
            return null;
        }

        return __('This is more than the balance. The extra :amount will show as an advance.', ['amount' => NumberFormat::money($extra)]);
    }
}
