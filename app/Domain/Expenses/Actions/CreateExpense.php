<?php

declare(strict_types=1);

namespace App\Domain\Expenses\Actions;

use App\Domain\Attachments\Actions\StoreAttachment;
use App\Domain\Attachments\Enums\AttachmentKind;
use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Checks\CashGuard;
use App\Domain\Expenses\Enums\ExpensePaymentMode;
use App\Domain\Expenses\ExpenseInput;
use App\Domain\Expenses\Models\Expense;
use App\Domain\Ledger\PostingRequest;
use App\Domain\Ledger\PostingRules;
use App\Domain\Ledger\PostingService;
use App\Domain\Numbering\VoucherNumberService;
use App\Domain\Periods\Periods;
use App\Domain\Settings\Settings;
use App\Models\User;
use App\Support\Idempotency;
use App\Support\Warnings\Warnings;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Page 13 (Quick expense): a running cost paid now (PR-09), owed to a party (PR-10)
 * or paid by an employee from cash he holds (PR-11), always with a note.
 */
final class CreateExpense
{
    /**
     * @param  array<string, mixed>  $data  validated StoreExpenseRequest fields
     * @param  list<string>  $confirmedWarnings
     */
    public function handle(User $by, array $data, array $confirmedWarnings = []): Expense
    {
        $key = (string) $data['idempotency_key'];

        try {
            return DB::transaction(function () use ($by, $data, $key, $confirmedWarnings): Expense {
                Settings::assertComplete(); // BR-GEN-07
                Periods::assertValidEntryDate((string) $data['date']); // BR-GEN-02

                $existing = Idempotency::existing(Expense::class, $by, $key); // BR-GEN-11

                if ($existing !== null) {
                    return $existing;
                }

                $input = ExpenseInput::from($data);
                $warnings = new Warnings($confirmedWarnings);

                if ($input->account !== null) {
                    CashGuard::check($warnings, $input->account, $input->amount); // BR-GEN-10
                }

                $expense = Expense::query()->create([
                    ...self::fields($input),
                    'company_id' => $by->company_id,
                    'voucher_no' => VoucherNumberService::next('expense'), // BR-GEN-05
                    'idempotency_key' => $key,
                    'created_by' => $by->id,
                ]);

                $journal = PostingService::post(self::posting($expense), $by);
                $expense->update(['journal_entry_id' => $journal->id]);

                AuditLogger::log(AuditActions::CREATED, $expense, [], self::audited($expense), by: $by);
                $warnings->audit($expense, $by);

                if (($data['photo'] ?? null) instanceof UploadedFile) {
                    app(StoreAttachment::class)->handle($by, $expense, $data['photo'], AttachmentKind::ReceiptPhoto, 'photo');
                }

                return $expense;
            });
        } catch (QueryException $e) {
            if (Idempotency::isDuplicateKeyViolation($e)) {
                return Idempotency::existing(Expense::class, $by, $key) ?? throw $e;
            }

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function fields(ExpenseInput $input): array
    {
        return [
            'entry_date' => $input->date,
            'category_id' => $input->category->id,
            'amount' => $input->amount,
            'payment_mode' => $input->mode,
            'money_account_id' => $input->account?->id,
            'party_id' => $input->party?->id,
            'handled_by_party_id' => $input->handledBy?->id,
            'linked_purchase_id' => $input->linkedPurchaseId,
            'linked_sale_id' => $input->linkedSaleId,
            'note' => $input->note,
        ];
    }

    /** Spec §8.3 PR-09, PR-10 or PR-11 by payment mode. */
    public static function posting(Expense $expense, ?string $postingDate = null): PostingRequest
    {
        return match ($expense->payment_mode) {
            ExpensePaymentMode::Paid => PostingRules::pr09ExpensePaid($expense, $postingDate),
            ExpensePaymentMode::Unpaid => PostingRules::pr10ExpenseUnpaid($expense, $postingDate),
            ExpensePaymentMode::PaidByEmployee => PostingRules::pr11ExpensePaidByEmployee($expense, $postingDate),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function audited(Expense $expense): array
    {
        return [
            'voucher_no' => $expense->voucher_no,
            'entry_date' => $expense->entry_date,
            'category_id' => $expense->category_id,
            'amount' => $expense->amount,
            'payment_mode' => $expense->payment_mode->value,
            'money_account_id' => $expense->money_account_id,
            'party_id' => $expense->party_id,
            'handled_by_party_id' => $expense->handled_by_party_id,
            'linked_purchase_id' => $expense->linked_purchase_id,
            'linked_sale_id' => $expense->linked_sale_id,
            'note' => $expense->note,
        ];
    }
}
