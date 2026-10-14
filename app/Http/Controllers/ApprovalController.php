<?php

namespace App\Http\Controllers;

use App\Domain\Parties\Models\Party;
use App\Domain\Payments\Actions\ApprovePayment;
use App\Domain\Payments\Actions\RejectPayment;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\PaymentBalance;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Page 27, Approvals (BR-PAY-08): payments waiting for the Owner, oldest first. */
class ApprovalController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('payment.approve');

        $payments = Payment::query()
            ->with(['party', 'moneyAccount', 'creator'])
            ->where('status', 'active')
            ->where('approval_status', 'pending')
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        return Inertia::render('approvals/index', [
            'payments' => $payments->map(function (Payment $p): array {
                /** @var Party $party */
                $party = $p->party;
                $balance = $party->balanceOfNet(PaymentBalance::net($party));

                return [
                    'id' => $p->id,
                    'public_id' => $p->public_id,
                    'voucher_no' => $p->voucher_no,
                    'entry_date' => $p->entry_date,
                    'party' => $party->code.' – '.$party->name,
                    'balance' => ['words' => $balance->words, 'amount' => $balance->amount],
                    'amount' => $p->amount,
                    'account' => $p->moneyAccount->name,
                    'reference_no' => $p->reference_no,
                    'note' => $p->note,
                    'created_by' => $p->creator?->name,
                ];
            })->values(),
        ]);
    }

    public function approve(Request $request, Payment $payment, ApprovePayment $action): RedirectResponse
    {
        $this->authorize('approve', $payment);

        $action->handle($this->actor($request), $payment);
        Flash::saved($payment->voucher_no);

        return back();
    }

    public function reject(Request $request, Payment $payment, RejectPayment $action): RedirectResponse
    {
        $this->authorize('approve', $payment);

        $reason = (string) $request->validate(['reason' => ['required', 'string', 'max:1000']])['reason'];
        $action->handle($this->actor($request), $payment, $reason);
        Flash::saved($payment->voucher_no);

        return back();
    }
}
