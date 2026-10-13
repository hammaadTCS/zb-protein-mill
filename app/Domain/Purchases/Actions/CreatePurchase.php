<?php

declare(strict_types=1);

namespace App\Domain\Purchases\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Ledger\PostingRules;
use App\Domain\Ledger\PostingService;
use App\Domain\Numbering\VoucherNumberService;
use App\Domain\Parties\Enums\PartyType;
use App\Domain\Payments\Actions\RecordPayment;
use App\Domain\Payments\Enums\PaymentDirection;
use App\Domain\Periods\Periods;
use App\Domain\Purchases\Enums\RateStatus;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Settings\Settings;
use App\Models\User;
use App\Support\Decimal;
use App\Support\EntryChecks;
use App\Support\Idempotency;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records a purchase (BR-PUR-01 to BR-PUR-03) and, optionally, the payment made at
 * the same time ("Paid now", BR-PUR-10), in one transaction (conventions §2).
 * The amount is always kg × rate on the server (BR-GEN-06).
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
            return DB::transaction(fn (): Purchase => $this->create($by, $data, $key));
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
    private function create(User $by, array $data, string $key): Purchase
    {
        Settings::assertComplete(); // BR-GEN-07
        $date = (string) $data['date'];
        Periods::assertValidEntryDate($date); // BR-GEN-02

        $existing = Idempotency::existing(Purchase::class, $by, $key); // BR-GEN-11

        if ($existing !== null) {
            return $existing;
        }

        // BR-PUR-01
        $party = EntryChecks::party(self::id($data, 'party_id'), [PartyType::Supplier], 'party_id', __('Select an active supplier.'));
        $item = EntryChecks::item(self::id($data, 'item_id'), true, 'item_id', __('Select an active raw material or fuel item.'));
        $kg = EntryChecks::kg((string) $data['kg']); // BR-PUR-02
        $rate = self::rate($data); // BR-PUR-03
        $ourWeight = self::filled($data, 'our_weight_kg') ? EntryChecks::kg((string) $data['our_weight_kg'], 'our_weight_kg') : null;
        $paidNow = self::paidNow($data);

        $purchase = Purchase::query()->create([
            'company_id' => $by->company_id,
            'voucher_no' => VoucherNumberService::next('purchase'), // BR-GEN-05
            'entry_date' => $date,
            'party_id' => $party->id,
            'item_id' => $item->id,
            'kg' => $kg,
            'rate' => $rate,
            'amount' => Decimal::amount($kg, $rate), // BR-GEN-06
            'rate_status' => RateStatus::Fixed,
            'rate_fixed_at' => now(),
            'slip_no' => self::filled($data, 'slip_no') ? (string) $data['slip_no'] : null,
            'our_weight_kg' => $ourWeight,
            'note' => self::filled($data, 'note') ? (string) $data['note'] : null,
            'idempotency_key' => $key,
            'created_by' => $by->id,
        ]);
        $purchase->setRelation('item', $item);

        $journal = PostingService::post(PostingRules::pr01PurchaseFixedRate($purchase), $by); // PR-01
        $purchase->update(['journal_entry_id' => $journal->id]);

        AuditLogger::log(AuditActions::CREATED, $purchase, [], self::audited($purchase), by: $by);

        if ($paidNow !== null) {
            // BR-PUR-10: a separate payment linked to the purchase; any extra is an advance.
            app(RecordPayment::class)->handle($by, [
                ...$paidNow,
                'entry_date' => $date,
                'direction' => PaymentDirection::Paid,
                'party_id' => $party->id,
                'purchase_id' => $purchase->id,
                'idempotency_key' => $key,
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
     * BR-PUR-03: in this version of the form the rate is required; rate pending
     * comes with T3.1.
     *
     * @param  array<string, mixed>  $data
     */
    private static function rate(array $data): string
    {
        if (! self::filled($data, 'rate')) {
            throw ValidationException::withMessages(['rate' => __('Rate is required')]);
        }

        return EntryChecks::rate((string) $data['rate']);
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

        $account = EntryChecks::moneyAccount(self::id($paid, 'money_account_id'), 'paid_now.money_account_id');
        $handledBy = EntryChecks::handledBy(self::id($paid, 'handled_by_party_id'), 'paid_now.handled_by_party_id');

        return [
            'amount' => EntryChecks::amount((string) ($paid['amount'] ?? ''), 'paid_now.amount'),
            'money_account_id' => $account->id,
            'reference_no' => self::filled($paid, 'reference_no') ? (string) $paid['reference_no'] : null,
            'handled_by_party_id' => $handledBy?->id,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function filled(array $data, string $key): bool
    {
        return isset($data[$key]) && $data[$key] !== '';
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function id(array $data, string $key): ?int
    {
        return self::filled($data, $key) && is_numeric($data[$key]) ? (int) $data[$key] : null;
    }
}
