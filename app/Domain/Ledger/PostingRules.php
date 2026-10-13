<?php

declare(strict_types=1);

namespace App\Domain\Ledger;

use App\Domain\Adjustments\Models\Adjustment;
use App\Domain\Expenses\Models\Expense;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Domain\OpeningBalances\Models\OpeningBalance;
use App\Domain\Parties\Enums\PartyType;
use App\Domain\Parties\Models\Party;
use App\Domain\Partners\Models\PartnerTransaction;
use App\Domain\Payments\Models\Payment;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Sales\Models\Sale;
use App\Domain\Transfers\Models\Transfer;
use InvalidArgumentException;
use LogicException;

/**
 * Spec §8.3 posting rules PR-01 to PR-27: the only ways money is posted.
 * One named builder per rule; each returns the PostingRequest that PostingService posts.
 * M = the ledger account of the money account, (P) = the line carries the party,
 * (I) = the line carries the item. A "control account (P)" is chosen by party type:
 * supplier 2000, buyer 1200, employee 1300, other 2100.
 *
 * Each task that builds an entry type fills in its rules (T1.7 creates the stubs).
 */
final class PostingRules
{
    /**
     * PR-01: Purchase, rate fixed.
     * Debit: Item purchase account (I).
     * Credit: 2000 Suppliers (P).
     */
    public static function pr01PurchaseFixedRate(Purchase $purchase, ?string $postingDate = null): PostingRequest
    {
        $amount = $purchase->amount ?? throw new InvalidArgumentException('PR-01 needs a fixed rate.');

        return new PostingRequest(
            postingDate: $postingDate ?? $purchase->entry_date, // BR-LED-07, BR-LED-08
            sourceType: 'purchase',
            sourceId: $purchase->id,
            description: "Purchase {$purchase->voucher_no}",
            lines: [
                PostingLine::debit($purchase->item->ledgerAccountId(), $amount, itemId: $purchase->item_id),
                PostingLine::credit('2000', $amount, $purchase->party_id),
            ],
        );
    }

    /**
     * PR-02: Payment to supplier (including advance).
     * Debit: 2000 Suppliers (P).
     * Credit: M.
     */
    public static function pr02PaymentToSupplier(Payment $payment, ?string $postingDate = null): PostingRequest
    {
        return self::payment($payment, $postingDate, debit: '2000', credit: $payment->moneyAccount->ledger_account_id);
    }

    /**
     * PR-03: Money received back from supplier (advance returned).
     * Debit: M.
     * Credit: 2000 Suppliers (P).
     */
    public static function pr03MoneyBackFromSupplier(Payment $payment, ?string $postingDate = null): PostingRequest
    {
        return self::payment($payment, $postingDate, debit: $payment->moneyAccount->ledger_account_id, credit: '2000');
    }

    /**
     * PR-04: Sale, rate fixed.
     * Debit: 1200 Buyers (P).
     * Credit: Item sales account (I).
     */
    public static function pr04SaleFixedRate(Sale $sale, ?string $postingDate = null): PostingRequest
    {
        $amount = $sale->amount ?? throw new InvalidArgumentException('PR-04 needs a fixed rate.');

        return new PostingRequest(
            postingDate: $postingDate ?? $sale->entry_date, // BR-LED-07, BR-LED-08
            sourceType: 'sale',
            sourceId: $sale->id,
            description: "Sale {$sale->voucher_no}",
            lines: [
                PostingLine::debit('1200', $amount, $sale->party_id),
                PostingLine::credit($sale->item->ledgerAccountId(), $amount, itemId: $sale->item_id),
            ],
        );
    }

    /**
     * PR-05: Receipt from buyer (including advance).
     * Debit: M.
     * Credit: 1200 Buyers (P).
     */
    public static function pr05ReceiptFromBuyer(Payment $payment, ?string $postingDate = null): PostingRequest
    {
        return self::payment($payment, $postingDate, debit: $payment->moneyAccount->ledger_account_id, credit: '1200');
    }

    /**
     * PR-06: Money paid back to buyer (advance returned).
     * Debit: 1200 Buyers (P).
     * Credit: M.
     */
    public static function pr06MoneyBackToBuyer(Payment $payment, ?string $postingDate = null): PostingRequest
    {
        return self::payment($payment, $postingDate, debit: '1200', credit: $payment->moneyAccount->ledger_account_id);
    }

    /**
     * PR-07: Agreed adjustment on a sale.
     * Debit: 4900 Sales Adjustments.
     * Credit: 1200 Buyers (P).
     */
    public static function pr07SaleAdjustment(Adjustment $adjustment, ?string $postingDate = null): PostingRequest
    {
        return self::adjustment($adjustment, $postingDate, PostingLine::debit('4900', $adjustment->amount), PostingLine::credit('1200', $adjustment->amount, $adjustment->party_id));
    }

    /**
     * PR-08: Agreed adjustment on a purchase.
     * Debit: 2000 Suppliers (P).
     * Credit: 5900 Purchase Adjustments.
     */
    public static function pr08PurchaseAdjustment(Adjustment $adjustment, ?string $postingDate = null): PostingRequest
    {
        return self::adjustment($adjustment, $postingDate, PostingLine::debit('2000', $adjustment->amount, $adjustment->party_id), PostingLine::credit('5900', $adjustment->amount));
    }

    /**
     * PR-09: Expense paid from cash or bank.
     * Debit: Category account.
     * Credit: M.
     */
    public static function pr09ExpensePaid(Expense $expense, ?string $postingDate = null): PostingRequest
    {
        $account = $expense->moneyAccount ?? throw new InvalidArgumentException('PR-09 needs a money account.');

        return self::expense($expense, $postingDate, PostingLine::credit($account->ledger_account_id, $expense->amount));
    }

    /**
     * PR-10: Expense unpaid (owed to a party).
     * Debit: Category account.
     * Credit: Party's control account (P).
     */
    public static function pr10ExpenseUnpaid(Expense $expense, ?string $postingDate = null): PostingRequest
    {
        $party = $expense->party ?? throw new InvalidArgumentException('PR-10 needs a party.');
        self::assertPartyType($party, [PartyType::Supplier, PartyType::Other, PartyType::Employee], 'PR-10');

        return self::expense($expense, $postingDate, PostingLine::credit(self::controlAccount($party), $expense->amount, $party->id));
    }

    /**
     * PR-11: Expense paid by an employee from cash he holds.
     * Debit: Category account.
     * Credit: 1300 Employees (P).
     */
    public static function pr11ExpensePaidByEmployee(Expense $expense, ?string $postingDate = null): PostingRequest
    {
        $party = $expense->party ?? throw new InvalidArgumentException('PR-11 needs an employee.');
        self::assertPartyType($party, [PartyType::Employee], 'PR-11');

        return self::expense($expense, $postingDate, PostingLine::credit('1300', $expense->amount, $party->id));
    }

    /**
     * PR-12: Payment to an other party or employee.
     * Debit: Party's control account (P).
     * Credit: M.
     */
    public static function pr12PaymentToOtherOrEmployee(Payment $payment, ?string $postingDate = null): PostingRequest
    {
        self::assertPartyType($payment->party, [PartyType::Other, PartyType::Employee], 'PR-12');

        return self::payment($payment, $postingDate, debit: self::controlAccount($payment->party), credit: $payment->moneyAccount->ledger_account_id);
    }

    /**
     * PR-13: Money received from an other party or employee (cash returned).
     * Debit: M.
     * Credit: Party's control account (P).
     */
    public static function pr13MoneyFromOtherOrEmployee(Payment $payment, ?string $postingDate = null): PostingRequest
    {
        self::assertPartyType($payment->party, [PartyType::Other, PartyType::Employee], 'PR-13');

        return self::payment($payment, $postingDate, debit: $payment->moneyAccount->ledger_account_id, credit: self::controlAccount($payment->party));
    }

    /**
     * PR-14: Partner investment.
     * Debit: M.
     * Credit: 3000 Partner Capital (P).
     */
    public static function pr14PartnerInvestment(PartnerTransaction $entry, ?string $postingDate = null): PostingRequest
    {
        return self::partner($entry, $postingDate, PostingLine::debit($entry->moneyAccount->ledger_account_id, $entry->amount), PostingLine::credit('3000', $entry->amount, $entry->party_id));
    }

    /**
     * PR-15: Partner withdrawal.
     * Debit: 3100 Partner Withdrawals (P).
     * Credit: M.
     */
    public static function pr15PartnerWithdrawal(PartnerTransaction $entry, ?string $postingDate = null): PostingRequest
    {
        return self::partner($entry, $postingDate, PostingLine::debit('3100', $entry->amount, $entry->party_id), PostingLine::credit($entry->moneyAccount->ledger_account_id, $entry->amount));
    }

    /**
     * PR-16: Transfer.
     * Debit: M of "to" account.
     * Credit: M of "from" account.
     */
    public static function pr16Transfer(Transfer $transfer, ?string $postingDate = null): PostingRequest
    {
        return new PostingRequest(
            postingDate: $postingDate ?? $transfer->entry_date,
            sourceType: 'transfer',
            sourceId: $transfer->id,
            description: "Transfer {$transfer->voucher_no}",
            lines: [
                PostingLine::debit($transfer->toAccount->ledger_account_id, $transfer->amount),
                PostingLine::credit($transfer->fromAccount->ledger_account_id, $transfer->amount),
            ],
        );
    }

    /**
     * PR-17: Opening balance of a cash or bank account.
     * Debit: M.
     * Credit: 3900.
     */
    public static function pr17OpeningMoneyAccount(OpeningBalance $opening, MoneyAccount $account): PostingRequest
    {
        return self::opening($opening, [
            PostingLine::debit($account->ledger_account_id, $opening->amount),
            PostingLine::credit('3900', $opening->amount),
        ]);
    }

    /**
     * PR-18: Opening: we owe a supplier or other party.
     * Debit: 3900.
     * Credit: Control account (P).
     */
    public static function pr18OpeningWeOweSupplierOrOther(OpeningBalance $opening, Party $party): PostingRequest
    {
        self::assertPartyType($party, [PartyType::Supplier, PartyType::Other], 'PR-18');

        return self::opening($opening, [
            PostingLine::debit('3900', $opening->amount),
            PostingLine::credit(self::controlAccount($party), $opening->amount, $party->id),
        ]);
    }

    /**
     * PR-19: Opening: advance with a supplier or other party.
     * Debit: Control account (P).
     * Credit: 3900.
     */
    public static function pr19OpeningAdvanceWithSupplierOrOther(OpeningBalance $opening, Party $party): PostingRequest
    {
        self::assertPartyType($party, [PartyType::Supplier, PartyType::Other], 'PR-19');

        return self::opening($opening, [
            PostingLine::debit(self::controlAccount($party), $opening->amount, $party->id),
            PostingLine::credit('3900', $opening->amount),
        ]);
    }

    /**
     * PR-20: Opening: buyer or employee owes us.
     * Debit: Control account (P).
     * Credit: 3900.
     */
    public static function pr20OpeningBuyerOrEmployeeOwesUs(OpeningBalance $opening, Party $party): PostingRequest
    {
        self::assertPartyType($party, [PartyType::Buyer, PartyType::Employee], 'PR-20');

        return self::opening($opening, [
            PostingLine::debit(self::controlAccount($party), $opening->amount, $party->id),
            PostingLine::credit('3900', $opening->amount),
        ]);
    }

    /**
     * PR-21: Opening: advance from a buyer, or we owe an employee.
     * Debit: 3900.
     * Credit: Control account (P).
     */
    public static function pr21OpeningAdvanceFromBuyerOrWeOweEmployee(OpeningBalance $opening, Party $party): PostingRequest
    {
        self::assertPartyType($party, [PartyType::Buyer, PartyType::Employee], 'PR-21');

        return self::opening($opening, [
            PostingLine::debit('3900', $opening->amount),
            PostingLine::credit(self::controlAccount($party), $opening->amount, $party->id),
        ]);
    }

    /**
     * PR-22: Opening: partner capital.
     * Debit: 3900.
     * Credit: 3000 Partner Capital (P).
     */
    public static function pr22OpeningPartnerCapital(OpeningBalance $opening, Party $party): PostingRequest
    {
        self::assertPartyType($party, [PartyType::Partner], 'PR-22');

        return self::opening($opening, [
            PostingLine::debit('3900', $opening->amount),
            PostingLine::credit('3000', $opening->amount, $party->id),
        ]);
    }

    /**
     * PR-23: Month-end cash shortage.
     * Debit: 6900.
     * Credit: M (cash).
     */
    public static function pr23CashShortage(): PostingRequest
    {
        throw new LogicException('Not built yet: PR-23');
    }

    /**
     * PR-24: Month-end cash excess.
     * Debit: M (cash).
     * Credit: 6900.
     */
    public static function pr24CashExcess(): PostingRequest
    {
        throw new LogicException('Not built yet: PR-24');
    }

    /**
     * PR-25: Reversal (edit or cancel).
     * Debit: Same lines as the original, sides swapped.
     *
     * Built as PostingService::reverse() in T1.7; there is nothing to build here.
     */
    public static function pr25Reversal(): PostingRequest
    {
        throw new LogicException('PR-25 is posted by PostingService::reverse().');
    }

    /**
     * PR-26: Rate correction of an entry in a closed month, purchase.
     * Debit: Increase: item purchase account / Decrease: 2000 (P).
     * Credit: Increase: 2000 (P) / Decrease: item purchase account.
     */
    public static function pr26RateCorrectionPurchase(): PostingRequest
    {
        throw new LogicException('Not built yet: PR-26');
    }

    /**
     * PR-27: Rate correction of an entry in a closed month, sale.
     * Debit: Increase: 1200 (P) / Decrease: item sales account.
     * Credit: Increase: item sales account / Decrease: 1200 (P).
     */
    public static function pr27RateCorrectionSale(): PostingRequest
    {
        throw new LogicException('Not built yet: PR-27');
    }

    /** Spec §8.3: supplier 2000, buyer 1200, employee 1300, other 2100. */
    public static function controlAccount(Party $party): string
    {
        return $party->type->controlAccountCode()
            ?? throw new InvalidArgumentException("Party {$party->code} has no control account.");
    }

    /**
     * @param  list<PartyType>  $allowed
     */
    private static function assertPartyType(Party $party, array $allowed, string $rule): void
    {
        if (! in_array($party->type, $allowed, true)) {
            throw new InvalidArgumentException("{$rule} does not apply to a {$party->type->value}.");
        }
    }

    /**
     * Openings are posted on the system start date (BR-OPB-01).
     *
     * @param  list<PostingLine>  $lines
     */
    private static function opening(OpeningBalance $opening, array $lines): PostingRequest
    {
        return new PostingRequest(
            postingDate: $opening->as_of_date,
            sourceType: 'opening_balance',
            sourceId: $opening->id,
            description: "Opening balance {$opening->voucher_no}",
            lines: $lines,
        );
    }

    /**
     * A payment or receipt between a money account (given by ledger account id) and a
     * party control account (given by code); the party goes on the control line.
     */
    private static function payment(Payment $payment, ?string $postingDate, string|int $debit, string|int $credit): PostingRequest
    {
        $label = $payment->direction->value === 'paid' ? 'Payment' : 'Receipt';

        return new PostingRequest(
            postingDate: $postingDate ?? $payment->entry_date,
            sourceType: 'payment',
            sourceId: $payment->id,
            description: "{$label} {$payment->voucher_no}",
            lines: [
                PostingLine::debit($debit, $payment->amount, is_string($debit) ? $payment->party_id : null),
                PostingLine::credit($credit, $payment->amount, is_string($credit) ? $payment->party_id : null),
            ],
        );
    }

    /** An agreed adjustment (BR-ADJ-02: on the linked entry's party). */
    private static function adjustment(Adjustment $adjustment, ?string $postingDate, PostingLine $debit, PostingLine $credit): PostingRequest
    {
        return new PostingRequest(
            postingDate: $postingDate ?? $adjustment->entry_date,
            sourceType: 'adjustment',
            sourceId: $adjustment->id,
            description: "Agreed adjustment {$adjustment->voucher_no}",
            lines: [$debit, $credit],
        );
    }

    /** A partner entry (BR-PTN-01: the party must be a partner). */
    private static function partner(PartnerTransaction $entry, ?string $postingDate, PostingLine $debit, PostingLine $credit): PostingRequest
    {
        self::assertPartyType($entry->party, [PartyType::Partner], $entry->type->value === 'investment' ? 'PR-14' : 'PR-15');

        return new PostingRequest(
            postingDate: $postingDate ?? $entry->entry_date,
            sourceType: 'partner_transaction',
            sourceId: $entry->id,
            description: ($entry->type->value === 'investment' ? 'Investment' : 'Withdrawal')." {$entry->voucher_no}",
            lines: [$debit, $credit],
        );
    }

    /** An expense: debit its category account, credit the given line. */
    private static function expense(Expense $expense, ?string $postingDate, PostingLine $credit): PostingRequest
    {
        return new PostingRequest(
            postingDate: $postingDate ?? $expense->entry_date,
            sourceType: 'expense',
            sourceId: $expense->id,
            description: "Expense {$expense->voucher_no}",
            lines: [
                PostingLine::debit($expense->category->ledger_account_id, $expense->amount),
                $credit,
            ],
        );
    }
}
