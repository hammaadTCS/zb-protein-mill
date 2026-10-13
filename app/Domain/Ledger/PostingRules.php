<?php

declare(strict_types=1);

namespace App\Domain\Ledger;

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
    public static function pr01PurchaseFixedRate(): PostingRequest
    {
        throw new LogicException('Not built yet: PR-01');
    }

    /**
     * PR-02: Payment to supplier (including advance).
     * Debit: 2000 Suppliers (P).
     * Credit: M.
     */
    public static function pr02PaymentToSupplier(): PostingRequest
    {
        throw new LogicException('Not built yet: PR-02');
    }

    /**
     * PR-03: Money received back from supplier (advance returned).
     * Debit: M.
     * Credit: 2000 Suppliers (P).
     */
    public static function pr03MoneyBackFromSupplier(): PostingRequest
    {
        throw new LogicException('Not built yet: PR-03');
    }

    /**
     * PR-04: Sale, rate fixed.
     * Debit: 1200 Buyers (P).
     * Credit: Item sales account (I).
     */
    public static function pr04SaleFixedRate(): PostingRequest
    {
        throw new LogicException('Not built yet: PR-04');
    }

    /**
     * PR-05: Receipt from buyer (including advance).
     * Debit: M.
     * Credit: 1200 Buyers (P).
     */
    public static function pr05ReceiptFromBuyer(): PostingRequest
    {
        throw new LogicException('Not built yet: PR-05');
    }

    /**
     * PR-06: Money paid back to buyer (advance returned).
     * Debit: 1200 Buyers (P).
     * Credit: M.
     */
    public static function pr06MoneyBackToBuyer(): PostingRequest
    {
        throw new LogicException('Not built yet: PR-06');
    }

    /**
     * PR-07: Agreed adjustment on a sale.
     * Debit: 4900 Sales Adjustments.
     * Credit: 1200 Buyers (P).
     */
    public static function pr07SaleAdjustment(): PostingRequest
    {
        throw new LogicException('Not built yet: PR-07');
    }

    /**
     * PR-08: Agreed adjustment on a purchase.
     * Debit: 2000 Suppliers (P).
     * Credit: 5900 Purchase Adjustments.
     */
    public static function pr08PurchaseAdjustment(): PostingRequest
    {
        throw new LogicException('Not built yet: PR-08');
    }

    /**
     * PR-09: Expense paid from cash or bank.
     * Debit: Category account.
     * Credit: M.
     */
    public static function pr09ExpensePaid(): PostingRequest
    {
        throw new LogicException('Not built yet: PR-09');
    }

    /**
     * PR-10: Expense unpaid (owed to a party).
     * Debit: Category account.
     * Credit: Party's control account (P).
     */
    public static function pr10ExpenseUnpaid(): PostingRequest
    {
        throw new LogicException('Not built yet: PR-10');
    }

    /**
     * PR-11: Expense paid by an employee from cash he holds.
     * Debit: Category account.
     * Credit: 1300 Employees (P).
     */
    public static function pr11ExpensePaidByEmployee(): PostingRequest
    {
        throw new LogicException('Not built yet: PR-11');
    }

    /**
     * PR-12: Payment to an other party or employee.
     * Debit: Party's control account (P).
     * Credit: M.
     */
    public static function pr12PaymentToOtherOrEmployee(): PostingRequest
    {
        throw new LogicException('Not built yet: PR-12');
    }

    /**
     * PR-13: Money received from an other party or employee (cash returned).
     * Debit: M.
     * Credit: Party's control account (P).
     */
    public static function pr13MoneyFromOtherOrEmployee(): PostingRequest
    {
        throw new LogicException('Not built yet: PR-13');
    }

    /**
     * PR-14: Partner investment.
     * Debit: M.
     * Credit: 3000 Partner Capital (P).
     */
    public static function pr14PartnerInvestment(): PostingRequest
    {
        throw new LogicException('Not built yet: PR-14');
    }

    /**
     * PR-15: Partner withdrawal.
     * Debit: 3100 Partner Withdrawals (P).
     * Credit: M.
     */
    public static function pr15PartnerWithdrawal(): PostingRequest
    {
        throw new LogicException('Not built yet: PR-15');
    }

    /**
     * PR-16: Transfer.
     * Debit: M of "to" account.
     * Credit: M of "from" account.
     */
    public static function pr16Transfer(): PostingRequest
    {
        throw new LogicException('Not built yet: PR-16');
    }

    /**
     * PR-17: Opening balance of a cash or bank account.
     * Debit: M.
     * Credit: 3900.
     */
    public static function pr17OpeningMoneyAccount(): PostingRequest
    {
        throw new LogicException('Not built yet: PR-17');
    }

    /**
     * PR-18: Opening: we owe a supplier or other party.
     * Debit: 3900.
     * Credit: Control account (P).
     */
    public static function pr18OpeningWeOweSupplierOrOther(): PostingRequest
    {
        throw new LogicException('Not built yet: PR-18');
    }

    /**
     * PR-19: Opening: advance with a supplier or other party.
     * Debit: Control account (P).
     * Credit: 3900.
     */
    public static function pr19OpeningAdvanceWithSupplierOrOther(): PostingRequest
    {
        throw new LogicException('Not built yet: PR-19');
    }

    /**
     * PR-20: Opening: buyer or employee owes us.
     * Debit: Control account (P).
     * Credit: 3900.
     */
    public static function pr20OpeningBuyerOrEmployeeOwesUs(): PostingRequest
    {
        throw new LogicException('Not built yet: PR-20');
    }

    /**
     * PR-21: Opening: advance from a buyer, or we owe an employee.
     * Debit: 3900.
     * Credit: Control account (P).
     */
    public static function pr21OpeningAdvanceFromBuyerOrWeOweEmployee(): PostingRequest
    {
        throw new LogicException('Not built yet: PR-21');
    }

    /**
     * PR-22: Opening: partner capital.
     * Debit: 3900.
     * Credit: 3000 Partner Capital (P).
     */
    public static function pr22OpeningPartnerCapital(): PostingRequest
    {
        throw new LogicException('Not built yet: PR-22');
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
}
