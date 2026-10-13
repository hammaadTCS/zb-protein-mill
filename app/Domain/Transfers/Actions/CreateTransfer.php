<?php

declare(strict_types=1);

namespace App\Domain\Transfers\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Checks\CashGuard;
use App\Domain\Ledger\PostingRules;
use App\Domain\Ledger\PostingService;
use App\Domain\Numbering\VoucherNumberService;
use App\Domain\Periods\Periods;
use App\Domain\Settings\Settings;
use App\Domain\Transfers\Models\Transfer;
use App\Domain\Transfers\TransferInput;
use App\Models\User;
use App\Support\Idempotency;
use App\Support\Warnings\Warnings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/** Page 16: money moved between two of our accounts (PR-16); never income or spending. */
final class CreateTransfer
{
    /**
     * @param  array<string, mixed>  $data  validated StoreTransferRequest fields
     * @param  list<string>  $confirmedWarnings
     */
    public function handle(User $by, array $data, array $confirmedWarnings = []): Transfer
    {
        $key = (string) $data['idempotency_key'];

        try {
            return DB::transaction(function () use ($by, $data, $key, $confirmedWarnings): Transfer {
                Settings::assertComplete(); // BR-GEN-07
                Periods::assertValidEntryDate((string) $data['date']); // BR-GEN-02

                $existing = Idempotency::existing(Transfer::class, $by, $key); // BR-GEN-11

                if ($existing !== null) {
                    return $existing;
                }

                $input = TransferInput::from($data);
                $warnings = new Warnings($confirmedWarnings);
                CashGuard::check($warnings, $input->from, $input->amount); // BR-GEN-10 (cash accounts only)

                $transfer = Transfer::query()->create([
                    ...self::fields($input),
                    'company_id' => $by->company_id,
                    'voucher_no' => VoucherNumberService::next('transfer'), // BR-GEN-05
                    'idempotency_key' => $key,
                    'created_by' => $by->id,
                ]);

                $journal = PostingService::post(PostingRules::pr16Transfer($transfer), $by);
                $transfer->update(['journal_entry_id' => $journal->id]);

                AuditLogger::log(AuditActions::CREATED, $transfer, [], self::audited($transfer), by: $by);
                $warnings->audit($transfer, $by);

                return $transfer;
            });
        } catch (QueryException $e) {
            if (Idempotency::isDuplicateKeyViolation($e)) {
                return Idempotency::existing(Transfer::class, $by, $key) ?? throw $e;
            }

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function fields(TransferInput $input): array
    {
        return [
            'entry_date' => $input->date,
            'from_account_id' => $input->from->id,
            'to_account_id' => $input->to->id,
            'amount' => $input->amount,
            'reference_no' => $input->referenceNo,
            'note' => $input->note,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function audited(Transfer $transfer): array
    {
        return [
            'voucher_no' => $transfer->voucher_no,
            'entry_date' => $transfer->entry_date,
            'from_account_id' => $transfer->from_account_id,
            'to_account_id' => $transfer->to_account_id,
            'amount' => $transfer->amount,
            'reference_no' => $transfer->reference_no,
            'note' => $transfer->note,
        ];
    }
}
