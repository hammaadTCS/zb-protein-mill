<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Checks\CashGuard;
use App\Domain\Ledger\Journals;
use App\Domain\Ledger\PostingService;
use App\Domain\Payments\Enums\ApprovalStatus;
use App\Domain\Payments\Enums\PaymentDirection;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\PaymentApproval;
use App\Domain\Payments\PaymentInput;
use App\Domain\Periods\Periods;
use App\Domain\Settings\Settings;
use App\Models\User;
use App\Support\ConcurrentEdit;
use App\Support\EntryStatus;
use App\Support\Warnings\Warnings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Edits a payment by reversal and repost (spec §6). The direction may change; the
 * voucher number is kept (conventions §8). Refused in a closed month (BR-MEC-07) and
 * when another user saved first (BR-GEN-09).
 */
final class UpdatePayment
{
    /**
     * @param  array<string, mixed>  $data  validated UpdatePaymentRequest fields
     * @param  list<string>  $confirmedWarnings
     */
    public function handle(User $by, Payment $payment, array $data, array $confirmedWarnings = []): Payment
    {
        return DB::transaction(function () use ($by, $payment, $data, $confirmedWarnings): Payment {
            Settings::assertComplete(); // BR-GEN-07
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            ConcurrentEdit::check($payment->updated_at, isset($data['version']) ? (string) $data['version'] : null); // BR-GEN-09

            if ($payment->status !== EntryStatus::Active || $payment->approval_status === ApprovalStatus::Rejected) {
                throw ValidationException::withMessages(['date' => __('Only an active payment can be edited.')]);
            }

            Periods::assertDateOpen($payment->entry_date);
            $oldPostingDate = $payment->journal_entry_id === null ? null : Journals::postingDate($payment->journal_entry_id);

            if ($oldPostingDate !== null) {
                Periods::assertDateOpen($oldPostingDate);
            }

            Periods::assertValidEntryDate((string) $data['date']); // BR-GEN-02
            $input = PaymentInput::from($data);
            $warnings = new Warnings($confirmedWarnings);
            $input->checkReference($warnings); // BR-PAY-04
            $approval = $payment->approval_status === ApprovalStatus::Approved
                ? ApprovalStatus::Approved
                : PaymentApproval::statusFor($by, $input->party, $input->direction, $input->amount, $payment->id);

            if ($input->direction === PaymentDirection::Paid && $approval !== ApprovalStatus::Pending) {
                CashGuard::check($warnings, $input->account, $input->amount, $payment); // BR-GEN-10: only the difference counts
            }

            $old = RecordPayment::audited($payment);
            $payment->fill([
                'entry_date' => $input->date,
                'direction' => $input->direction,
                'party_id' => $input->party->id,
                'amount' => $input->amount,
                'money_account_id' => $input->account->id,
                'reference_no' => $input->referenceNo,
                'handled_by_party_id' => $input->handledBy?->id,
                'note' => $input->note,
                'updated_by' => $by->id,
            ]);

            if (! $payment->isDirty()) {
                return $payment;
            }

            // The approval rules are checked again with the new figures (BR-PAY-06, BR-PAY-07);
            // an approved payment stays approved.
            $payment->approval_status = $approval;

            $payment->save();
            $payment->unsetRelation('party')->unsetRelation('moneyAccount');

            if ($oldPostingDate !== null && $payment->journal_entry_id !== null) {
                PostingService::reverseId($payment->journal_entry_id, $oldPostingDate, "Edit of {$payment->voucher_no}", $by); // PR-25
            }

            if ($payment->approval_status === ApprovalStatus::Pending) {
                $payment->update(['journal_entry_id' => null]); // BR-PAY-08: no journal while waiting
            } else {
                $journal = PostingService::post(RecordPayment::posting($payment), $by);
                $payment->update(['journal_entry_id' => $journal->id]);
            }

            $new = RecordPayment::audited($payment);
            $changed = array_flip(array_keys(array_filter($new, fn ($value, $key) => ($old[$key] ?? null) !== $value, ARRAY_FILTER_USE_BOTH)));
            AuditLogger::log(AuditActions::UPDATED, $payment, array_intersect_key($old, $changed), array_intersect_key($new, $changed), by: $by);
            $warnings->audit($payment, $by);

            return $payment;
        });
    }
}
