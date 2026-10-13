<?php

declare(strict_types=1);

namespace App\Domain\Purchases\Actions;

use App\Domain\Attachments\Actions\StoreAttachment;
use App\Domain\Attachments\Enums\AttachmentKind;
use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Ledger\PostingRules;
use App\Domain\Ledger\PostingService;
use App\Domain\Numbering\VoucherNumberService;
use App\Domain\Payments\Actions\RecordPayment;
use App\Domain\Payments\Enums\PaymentDirection;
use App\Domain\Payments\PaymentApproval;
use App\Domain\Periods\Periods;
use App\Domain\Purchases\Enums\RateStatus;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Purchases\PurchaseInput;
use App\Domain\Settings\Settings;
use App\Models\User;
use App\Support\Decimal;
use App\Support\EntryChecks;
use App\Support\EntryWarnings;
use App\Support\Idempotency;
use App\Support\Warnings\Warnings;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Records a purchase (BR-PUR-01 to BR-PUR-04) and, optionally, the payment made at
 * the same time ("Paid now", BR-PUR-10), in one transaction (conventions §2).
 * The amount is always kg × rate on the server (BR-GEN-06); without a rate the
 * purchase is "Rate pending" and posts nothing until the rate is added.
 */
final class CreatePurchase
{
    /**
     * @param  array<string, mixed>  $data  validated StorePurchaseRequest fields
     * @param  list<string>  $confirmedWarnings
     */
    public function handle(User $by, array $data, array $confirmedWarnings = []): Purchase
    {
        $key = (string) $data['idempotency_key'];

        try {
            return DB::transaction(fn (): Purchase => $this->create($by, $data, $key, new Warnings($confirmedWarnings)));
        } catch (QueryException $e) {
            // BR-GEN-11: the same form was saved by another request at the same moment.
            if (Idempotency::isDuplicateKeyViolation($e)) {
                return Idempotency::existing(Purchase::class, $by, $key) ?? throw $e;
            }

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function create(User $by, array $data, string $key, Warnings $warnings): Purchase
    {
        Settings::assertComplete(); // BR-GEN-07
        $date = (string) $data['date'];
        Periods::assertValidEntryDate($date); // BR-GEN-02

        $existing = Idempotency::existing(Purchase::class, $by, $key); // BR-GEN-11

        if ($existing !== null) {
            return $existing;
        }

        $input = PurchaseInput::from($data); // BR-PUR-01 to BR-PUR-03
        $paidNow = self::paidNow($data);
        EntryWarnings::slipRules($input->slipNo, ($data['slip_photo'] ?? null) instanceof UploadedFile, $input->ourWeightKg, checkPhoto: true); // BR-PUR-07

        if ($input->rate !== null) {
            EntryWarnings::unusualRate($warnings, Purchase::class, $input->item->id, $input->rate); // BR-PUR-06
        }

        EntryWarnings::duplicate($warnings, Purchase::class, 'purchase', $input->party->id, $input->item->id, $input->kg, $date); // BR-PUR-08

        $purchase = Purchase::query()->create([
            'company_id' => $by->company_id,
            'voucher_no' => VoucherNumberService::next('purchase'), // BR-GEN-05
            'entry_date' => $date,
            'party_id' => $input->party->id,
            'item_id' => $input->item->id,
            'kg' => $input->kg,
            'rate' => $input->rate,
            'amount' => $input->rate === null ? null : Decimal::amount($input->kg, $input->rate), // BR-GEN-06
            'rate_status' => $input->rate === null ? RateStatus::Pending : RateStatus::Fixed,
            'rate_fixed_at' => $input->rate === null ? null : now(),
            'slip_no' => $input->slipNo,
            'our_weight_kg' => $input->ourWeightKg,
            'note' => $input->note,
            'idempotency_key' => $key,
            'created_by' => $by->id,
        ]);
        $purchase->setRelation('item', $input->item);

        if ($input->rate !== null) {
            $journal = PostingService::post(PostingRules::pr01PurchaseFixedRate($purchase), $by); // PR-01
            $purchase->update(['journal_entry_id' => $journal->id]);
        } // BR-PUR-04, BR-LED-06: a rate-pending purchase has no journal yet.

        AuditLogger::log(AuditActions::CREATED, $purchase, [], self::audited($purchase), by: $by);
        $warnings->audit($purchase, $by);

        if (($data['slip_photo'] ?? null) instanceof UploadedFile) {
            app(StoreAttachment::class)->handle($by, $purchase, $data['slip_photo'], AttachmentKind::SlipPhoto, 'slip_photo'); // BR-PUR-07
        }

        if ($paidNow !== null) {
            // BR-PUR-10: a separate payment linked to the purchase; any extra is an advance.
            app(RecordPayment::class)->handle($by, [
                ...$paidNow,
                'entry_date' => $date,
                'direction' => PaymentDirection::Paid,
                'party_id' => $input->party->id,
                'purchase_id' => $purchase->id,
                'idempotency_key' => $key,
                'approval_status' => PaymentApproval::statusFor($by, $input->party, PaymentDirection::Paid, $paidNow['amount']), // BR-PUR-10, BR-PAY-06/07
            ]);
        }

        return $purchase;
    }

    /**
     * @return array<string, mixed>
     */
    public static function audited(Purchase $purchase): array
    {
        return [
            'voucher_no' => $purchase->voucher_no,
            'entry_date' => $purchase->entry_date,
            'party_id' => $purchase->party_id,
            'item_id' => $purchase->item_id,
            'kg' => $purchase->kg,
            'rate' => $purchase->rate,
            'amount' => $purchase->amount,
            'rate_status' => $purchase->rate_status->value,
            'slip_no' => $purchase->slip_no,
            'our_weight_kg' => $purchase->our_weight_kg,
            'note' => $purchase->note,
        ];
    }

    /**
     * BR-PUR-10: amount above 0, an active money account, optional reference and
     * "Handled by" employee.
     *
     * @param  array<string, mixed>  $data
     * @return array{amount: string, money_account_id: int, reference_no: string|null, handled_by_party_id: int|null}|null
     */
    private static function paidNow(array $data): ?array
    {
        $paid = $data['paid_now'] ?? null;

        if (! is_array($paid)) {
            return null;
        }

        $account = EntryChecks::moneyAccount(PurchaseInput::id($paid, 'money_account_id'), 'paid_now.money_account_id');
        $handledBy = EntryChecks::handledBy(PurchaseInput::id($paid, 'handled_by_party_id'), 'paid_now.handled_by_party_id');

        return [
            'amount' => EntryChecks::amount((string) ($paid['amount'] ?? ''), 'paid_now.amount'),
            'money_account_id' => $account->id,
            'reference_no' => PurchaseInput::filled($paid, 'reference_no') ? (string) $paid['reference_no'] : null,
            'handled_by_party_id' => $handledBy?->id,
        ];
    }
}
