<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Ledger\PostingRequest;
use App\Domain\Ledger\PostingRules;
use App\Domain\Ledger\PostingService;
use App\Domain\Numbering\VoucherNumberService;
use App\Domain\Parties\Enums\PartyType;
use App\Domain\Payments\Enums\ApprovalStatus;
use App\Domain\Payments\Enums\PaymentDirection;
use App\Domain\Payments\Models\Payment;
use App\Models\User;
use LogicException;

/**
 * Saves one payment row and, unless it waits for approval (BR-PAY-08), its journal.
 * Runs inside the caller's transaction: CreatePurchase ("Paid now", BR-PUR-10),
 * CreateSale ("Received now") and CreatePayment.
 */
final class RecordPayment
{
    /**
     * @param  array{entry_date: string, direction: PaymentDirection, party_id: int, amount: string, money_account_id: int, reference_no?: string|null, handled_by_party_id?: int|null, purchase_id?: int|null, sale_id?: int|null, note?: string|null, idempotency_key?: string|null, approval_status?: ApprovalStatus}  $fields
     */
    public function handle(User $by, array $fields): Payment
    {
        $payment = Payment::query()->create([
            ...$fields,
            'approval_status' => $fields['approval_status'] ?? ApprovalStatus::NotRequired,
            'company_id' => $by->company_id,
            'voucher_no' => VoucherNumberService::next($fields['direction']->docType()),
            'created_by' => $by->id,
        ]);

        if ($payment->approval_status !== ApprovalStatus::Pending) {
            $journal = PostingService::post(self::posting($payment), $by);
            $payment->update(['journal_entry_id' => $journal->id]);
        }

        AuditLogger::log(AuditActions::CREATED, $payment, [], self::audited($payment), by: $by);

        return $payment;
    }

    /** Spec §8.3: the posting rule for a payment's direction and party type. */
    public static function posting(Payment $payment, ?string $postingDate = null): PostingRequest
    {
        $type = $payment->party->type;

        return match (true) {
            $payment->direction === PaymentDirection::Paid && $type === PartyType::Supplier => PostingRules::pr02PaymentToSupplier($payment, $postingDate),
            default => throw new LogicException("Not built yet: {$payment->direction->value} payment to a {$type->value}."),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function audited(Payment $payment): array
    {
        return [
            'voucher_no' => $payment->voucher_no,
            'entry_date' => $payment->entry_date,
            'direction' => $payment->direction->value,
            'party_id' => $payment->party_id,
            'amount' => $payment->amount,
            'money_account_id' => $payment->money_account_id,
            'reference_no' => $payment->reference_no,
            'handled_by_party_id' => $payment->handled_by_party_id,
            'purchase_id' => $payment->purchase_id,
            'sale_id' => $payment->sale_id,
            'approval_status' => $payment->approval_status->value,
            'note' => $payment->note,
        ];
    }
}
