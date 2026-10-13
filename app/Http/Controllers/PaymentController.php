<?php

namespace App\Http\Controllers;

use App\Domain\Parties\Models\Party;
use App\Domain\Payments\Actions\CancelPayment;
use App\Domain\Payments\Actions\CreatePayment;
use App\Domain\Payments\Actions\UpdatePayment;
use App\Domain\Payments\Enums\PaymentDirection;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\PaymentBalance;
use App\Http\Requests\CancelEntryRequest;
use App\Http\Requests\Payments\StorePaymentRequest;
use App\Http\Requests\Payments\UpdatePaymentRequest;
use App\Rules\DecimalString;
use App\Support\Flash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Payments and receipts (pages 9 to 11 come with T3.6). */
class PaymentController extends Controller
{
    public function store(StorePaymentRequest $request, CreatePayment $action): RedirectResponse
    {
        $this->authorize('create', Payment::class);

        $data = $request->entryData();
        $info = $this->overpayment($data);
        $payment = $action->handle($this->actor($request), $data, $request->confirmedWarnings());

        $payment->wasRecentlyCreated ? Flash::saved($payment->voucher_no) : Flash::alreadySaved($payment->voucher_no);

        if ($info !== null && $payment->wasRecentlyCreated) {
            Inertia::flash(['toast' => ['type' => 'info', 'message' => $info]]); // BR-PAY-11
        }

        return back();
    }

    public function update(UpdatePaymentRequest $request, Payment $payment, UpdatePayment $action): RedirectResponse
    {
        $this->authorize('update', $payment);

        $action->handle($this->actor($request), $payment, $request->entryData(), $request->confirmedWarnings());
        Flash::saved($payment->voucher_no);

        return back();
    }

    public function cancel(CancelEntryRequest $request, Payment $payment, CancelPayment $action): RedirectResponse
    {
        $this->authorize('cancel', $payment);

        $action->handle($this->actor($request), $payment, (string) $request->validated('reason'));
        Flash::saved($payment->voucher_no);

        return back();
    }

    /** BR-PAY-05: the party's balance now and after this payment, in words; BR-PAY-11 info. */
    public function balancePreview(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        $input = $request->validate([
            'party_id' => ['required', 'integer'],
            'direction' => ['required', Rule::enum(PaymentDirection::class)],
            'amount' => ['nullable', DecimalString::money()],
            'payment_id' => ['nullable', 'integer'],
        ]);

        $party = Party::query()->findOrFail((int) $input['party_id']);
        abort_if($party->type->controlAccountCode() === null, 422);

        $except = isset($input['payment_id']) ? Payment::query()->whereKey((int) $input['payment_id'])->value('journal_entry_id') : null;
        $preview = PaymentBalance::preview($party, PaymentDirection::from((string) $input['direction']), (string) ($input['amount'] ?? '0'), $except === null ? null : (int) $except);

        return response()->json([
            'current' => ['words' => $preview['current']->words, 'amount' => $preview['current']->amount],
            'after' => ['words' => $preview['after']->words, 'amount' => $preview['after']->amount],
            'info' => $preview['info'],
        ]);
    }

    /**
     * BR-PAY-11: information only, worked out before the save.
     *
     * @param  array<string, mixed>  $data
     */
    private function overpayment(array $data): ?string
    {
        $party = Party::query()->find((int) ($data['party_id'] ?? 0));
        $direction = PaymentDirection::tryFrom((string) ($data['direction'] ?? ''));

        if ($party === null || $direction === null || $party->type->controlAccountCode() === null || ! is_numeric($data['amount'] ?? null)) {
            return null;
        }

        return PaymentBalance::overpayment(PaymentBalance::net($party), $direction, (string) $data['amount']);
    }
}
