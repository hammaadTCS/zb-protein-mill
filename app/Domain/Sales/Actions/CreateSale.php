<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Attachments\Actions\StoreAttachment;
use App\Domain\Attachments\Enums\AttachmentKind;
use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Ledger\PostingRules;
use App\Domain\Ledger\PostingService;
use App\Domain\Numbering\VoucherNumberService;
use App\Domain\Payments\Actions\RecordPayment;
use App\Domain\Payments\Enums\PaymentDirection;
use App\Domain\Periods\Periods;
use App\Domain\Purchases\Enums\RateStatus;
use App\Domain\Sales\CreditLimit;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\SaleInput;
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
 * Records a sale (BR-SAL-01, BR-SAL-05, BR-SAL-06 and the purchase rules applied to
 * sales, spec §10.5) and, optionally, the money received at the same time
 * ("Received now", BR-SAL-07), in one transaction (conventions §2).
 * The amount is always kg × rate on the server (BR-GEN-06); without a rate the
 * sale is "Rate pending" and posts nothing until the rate is added.
 */
final class CreateSale
{
    /**
     * @param  array<string, mixed>  $data  validated StoreSaleRequest fields
     * @param  list<string>  $confirmedWarnings
     */
    public function handle(User $by, array $data, array $confirmedWarnings = []): Sale
    {
        $key = (string) $data['idempotency_key'];

        try {
            return DB::transaction(fn (): Sale => $this->create($by, $data, $key, new Warnings($confirmedWarnings)));
        } catch (QueryException $e) {
            // BR-GEN-11: the same form was saved by another request at the same moment.
            if (Idempotency::isDuplicateKeyViolation($e)) {
                return Idempotency::existing(Sale::class, $by, $key) ?? throw $e;
            }

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function create(User $by, array $data, string $key, Warnings $warnings): Sale
    {
        Settings::assertComplete(); // BR-GEN-07
        $date = (string) $data['date'];
        Periods::assertValidEntryDate($date); // BR-GEN-02

        $existing = Idempotency::existing(Sale::class, $by, $key); // BR-GEN-11

        if ($existing !== null) {
            return $existing;
        }

        $input = SaleInput::from($data); // BR-SAL-01, BR-SAL-05
        $receivedNow = self::receivedNow($data);
        EntryWarnings::slipRules($input->slipNo, ($data['slip_photo'] ?? null) instanceof UploadedFile, $input->ourWeightKg, checkPhoto: true); // BR-PUR-07

        if ($input->rate !== null) {
            EntryWarnings::unusualRate($warnings, Sale::class, $input->item->id, $input->rate); // BR-PUR-06
        }

        EntryWarnings::duplicate($warnings, Sale::class, 'sale', $input->party->id, $input->item->id, $input->kg, $date); // BR-PUR-08

        if ($input->rate !== null) {
            CreditLimit::check($warnings, $input->party, Decimal::sub(Decimal::amount($input->kg, $input->rate), $receivedNow['amount'] ?? '0.00')); // BR-SAL-06
        }

        $sale = Sale::query()->create([
            'company_id' => $by->company_id,
            'voucher_no' => VoucherNumberService::next('sale'), // BR-GEN-05
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
        $sale->setRelation('item', $input->item);

        if ($input->rate !== null) {
            $journal = PostingService::post(PostingRules::pr04SaleFixedRate($sale), $by); // PR-01
            $sale->update(['journal_entry_id' => $journal->id]);
        } // BR-PUR-04, BR-LED-06: a rate-pending sale has no journal yet.

        AuditLogger::log(AuditActions::CREATED, $sale, [], self::audited($sale), by: $by);
        $warnings->audit($sale, $by);

        if (($data['slip_photo'] ?? null) instanceof UploadedFile) {
            app(StoreAttachment::class)->handle($by, $sale, $data['slip_photo'], AttachmentKind::SlipPhoto, 'slip_photo'); // BR-PUR-07
        }

        if ($receivedNow !== null) {
            // BR-SAL-07: a separate receipt linked to the sale; never needs approval.
            app(RecordPayment::class)->handle($by, [
                ...$receivedNow,
                'entry_date' => $date,
                'direction' => PaymentDirection::Received,
                'party_id' => $input->party->id,
                'sale_id' => $sale->id,
                'idempotency_key' => $key,
            ]);
        }

        return $sale;
    }

    /**
     * @return array<string, mixed>
     */
    public static function audited(Sale $sale): array
    {
        return [
            'voucher_no' => $sale->voucher_no,
            'entry_date' => $sale->entry_date,
            'party_id' => $sale->party_id,
            'item_id' => $sale->item_id,
            'kg' => $sale->kg,
            'rate' => $sale->rate,
            'amount' => $sale->amount,
            'rate_status' => $sale->rate_status->value,
            'slip_no' => $sale->slip_no,
            'our_weight_kg' => $sale->our_weight_kg,
            'note' => $sale->note,
        ];
    }

    /**
     * BR-SAL-07: amount above 0, an active money account, optional reference and
     * "Handled by" employee.
     *
     * @param  array<string, mixed>  $data
     * @return array{amount: string, money_account_id: int, reference_no: string|null, handled_by_party_id: int|null}|null
     */
    private static function receivedNow(array $data): ?array
    {
        $received = $data['received_now'] ?? null;

        if (! is_array($received)) {
            return null;
        }

        $account = EntryChecks::moneyAccount(SaleInput::id($received, 'money_account_id'), 'received_now.money_account_id');
        $handledBy = EntryChecks::handledBy(SaleInput::id($received, 'handled_by_party_id'), 'received_now.handled_by_party_id');

        return [
            'amount' => EntryChecks::amount((string) ($received['amount'] ?? ''), 'received_now.amount'),
            'money_account_id' => $account->id,
            'reference_no' => SaleInput::filled($received, 'reference_no') ? (string) $received['reference_no'] : null,
            'handled_by_party_id' => $handledBy?->id,
        ];
    }
}
