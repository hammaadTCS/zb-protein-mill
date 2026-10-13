<?php

declare(strict_types=1);

namespace App\Domain\Partners\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Checks\CashGuard;
use App\Domain\Ledger\PostingRequest;
use App\Domain\Ledger\PostingRules;
use App\Domain\Ledger\PostingService;
use App\Domain\Numbering\VoucherNumberService;
use App\Domain\Partners\Enums\PartnerTransactionType;
use App\Domain\Partners\Models\PartnerTransaction;
use App\Domain\Partners\PartnerTransactionInput;
use App\Domain\Periods\Periods;
use App\Domain\Settings\Settings;
use App\Models\User;
use App\Support\Idempotency;
use App\Support\Warnings\Warnings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Page 22: a partner's investment (PR-14) or withdrawal (PR-15). Never mixed with
 * sales or expenses; no profit is worked out (BR-PTN-04).
 */
final class CreatePartnerTransaction
{
    /**
     * @param  array<string, mixed>  $data  validated StorePartnerTransactionRequest fields
     * @param  list<string>  $confirmedWarnings
     */
    public function handle(User $by, array $data, array $confirmedWarnings = []): PartnerTransaction
    {
        $key = (string) $data['idempotency_key'];

        try {
            return DB::transaction(function () use ($by, $data, $key, $confirmedWarnings): PartnerTransaction {
                Settings::assertComplete(); // BR-GEN-07
                Periods::assertValidEntryDate((string) $data['date']); // BR-GEN-02

                $existing = Idempotency::existing(PartnerTransaction::class, $by, $key); // BR-GEN-11

                if ($existing !== null) {
                    return $existing;
                }

                $input = PartnerTransactionInput::from($data);
                $warnings = new Warnings($confirmedWarnings);

                if ($input->type === PartnerTransactionType::Withdrawal) {
                    CashGuard::check($warnings, $input->account, $input->amount); // BR-GEN-10
                }

                $entry = PartnerTransaction::query()->create([
                    ...self::fields($input),
                    'company_id' => $by->company_id,
                    'voucher_no' => VoucherNumberService::next('partner_transaction'), // BR-GEN-05
                    'idempotency_key' => $key,
                    'created_by' => $by->id,
                ]);

                $journal = PostingService::post(self::posting($entry), $by);
                $entry->update(['journal_entry_id' => $journal->id]);

                AuditLogger::log(AuditActions::CREATED, $entry, [], self::audited($entry), by: $by);
                $warnings->audit($entry, $by);

                return $entry;
            });
        } catch (QueryException $e) {
            if (Idempotency::isDuplicateKeyViolation($e)) {
                return Idempotency::existing(PartnerTransaction::class, $by, $key) ?? throw $e;
            }

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function fields(PartnerTransactionInput $input): array
    {
        return [
            'entry_date' => $input->date,
            'party_id' => $input->partner->id,
            'type' => $input->type,
            'amount' => $input->amount,
            'money_account_id' => $input->account->id,
            'note' => $input->note,
        ];
    }

    /** BR-PTN-02: an investment posts PR-14, a withdrawal PR-15. */
    public static function posting(PartnerTransaction $entry, ?string $postingDate = null): PostingRequest
    {
        return match ($entry->type) {
            PartnerTransactionType::Investment => PostingRules::pr14PartnerInvestment($entry, $postingDate),
            PartnerTransactionType::Withdrawal => PostingRules::pr15PartnerWithdrawal($entry, $postingDate),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function audited(PartnerTransaction $entry): array
    {
        return [
            'voucher_no' => $entry->voucher_no,
            'entry_date' => $entry->entry_date,
            'party_id' => $entry->party_id,
            'type' => $entry->type->value,
            'amount' => $entry->amount,
            'money_account_id' => $entry->money_account_id,
            'note' => $entry->note,
        ];
    }
}
