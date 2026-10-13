<?php

declare(strict_types=1);

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\PaymentInput;
use App\Domain\Periods\Periods;
use App\Domain\Settings\Settings;
use App\Models\User;
use App\Support\Idempotency;
use App\Support\Warnings\Warnings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Page 10: a payment to or a receipt from any party except a partner (BR-PAY-01 to
 * BR-PAY-04, BR-PAY-10 to BR-PAY-12). Voucher PAY for paid, RCV for received.
 * Approval rules (BR-PAY-06, BR-PAY-07) come with T3.7.
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
                ]);

                $warnings->audit($payment, $by);

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
