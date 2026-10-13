<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Attachments\Actions\StoreAttachment;
use App\Domain\Attachments\Enums\AttachmentKind;
use App\Domain\Checks\CashGuard;
use App\Domain\Payments\Enums\ApprovalStatus;
use App\Domain\Payments\Enums\PaymentDirection;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\PaymentApproval;
use App\Domain\Payments\PaymentInput;
use App\Domain\Periods\Periods;
use App\Domain\Settings\Settings;
use App\Models\User;
use App\Support\Idempotency;
use App\Support\Warnings\Warnings;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Page 10: a payment to or a receipt from any party except a partner (BR-PAY-01 to
 * BR-PAY-04, BR-PAY-10 to BR-PAY-12). Voucher PAY for paid, RCV for received.
 * A "paid" payment by a user without `payment.approve` may wait for the Owner
 * (BR-PAY-06, BR-PAY-07): it is saved without a journal (BR-PAY-08).
 */
final class CreatePayment
{
    /**
     * @param  array<string, mixed>  $data  validated StorePaymentRequest fields
     * @param  list<string>  $confirmedWarnings
     */
    public function handle(User $by, array $data, array $confirmedWarnings = []): Payment
    {
        $key = (string) $data['idempotency_key'];

        try {
            return DB::transaction(function () use ($by, $data, $key, $confirmedWarnings): Payment {
                Settings::assertComplete(); // BR-GEN-07
                Periods::assertValidEntryDate((string) $data['date']); // BR-GEN-02

                $existing = Idempotency::existing(Payment::class, $by, $key); // BR-GEN-11

                if ($existing !== null) {
                    return $existing;
                }

                $input = PaymentInput::from($data);
                $warnings = new Warnings($confirmedWarnings);
                $input->checkReference($warnings); // BR-PAY-04
                $approval = PaymentApproval::statusFor($by, $input->party, $input->direction, $input->amount); // BR-PAY-06, BR-PAY-07

                if ($input->direction === PaymentDirection::Paid && $approval !== ApprovalStatus::Pending) {
                    CashGuard::check($warnings, $input->account, $input->amount); // BR-GEN-10
                }

                $payment = app(RecordPayment::class)->handle($by, [
                    'entry_date' => $input->date,
                    'direction' => $input->direction,
                    'party_id' => $input->party->id,
                    'amount' => $input->amount,
                    'money_account_id' => $input->account->id,
                    'reference_no' => $input->referenceNo,
                    'handled_by_party_id' => $input->handledBy?->id,
                    'note' => $input->note,
                    'idempotency_key' => $key,
                    'approval_status' => $approval,
                ]);

                $warnings->audit($payment, $by);

                if (($data['photo'] ?? null) instanceof UploadedFile) {
                    app(StoreAttachment::class)->handle($by, $payment, $data['photo'], AttachmentKind::ReceiptPhoto, 'photo');
                }

                return $payment;
            });
        } catch (QueryException $e) {
            if (Idempotency::isDuplicateKeyViolation($e)) {
                return Idempotency::existing(Payment::class, $by, $key) ?? throw $e;
            }

            throw $e;
        }
    }
}
