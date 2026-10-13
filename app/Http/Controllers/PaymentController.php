<?php

namespace App\Http\Controllers;

use App\Domain\Parties\Models\Party;
use App\Domain\Payments\Actions\CancelPayment;
use App\Domain\Payments\Actions\CreatePayment;
use App\Domain\Payments\Actions\UpdatePayment;
use App\Domain\Payments\Enums\PaymentDirection;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\PaymentBalance;
use App\Domain\Payments\PaymentInput;
use App\Http\Controllers\Concerns\ListsEntries;
use App\Http\Controllers\Concerns\PresentsEntries;
use App\Http\Requests\CancelEntryRequest;
use App\Http\Requests\Payments\StorePaymentRequest;
use App\Http\Requests\Payments\UpdatePaymentRequest;
use App\Rules\DecimalString;
use App\Support\EntryStatus;
use App\Support\Flash;
use App\Support\Today;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Pages 9, 10 and 11: payments and receipts (spec §11.8). */
class PaymentController extends Controller
{
    use ListsEntries;
    use PresentsEntries;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Payment::class);

        $filters = $this->listFilters($request, ['active', 'cancelled', 'awaiting_approval']);
        $direction = (string) ($request->validate(['direction' => ['nullable', Rule::enum(PaymentDirection::class)]])['direction'] ?? '');

        $query = Payment::query()
            ->with(['party', 'moneyAccount'])
            ->whereBetween('entry_date', [$filters['from'], $filters['to']])
            ->when($direction !== '', fn (Builder $q) => $q->where('direction', $direction))
            ->when($filters['party_id'], fn (Builder $q, int $id) => $q->where('party_id', $id))
            ->when($filters['status'] === 'awaiting_approval', fn (Builder $q) => $q->where('status', 'active')->where('approval_status', 'pending'))
            ->when(in_array($filters['status'], ['active', 'cancelled'], true), fn (Builder $q) => $q->where('status', $filters['status']))
            ->when($filters['q'] !== '', function (Builder $q) use ($filters): void {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']).'%';
                $q->where(fn (Builder $inner) => $inner->where('voucher_no', 'ilike', $like)
                    ->orWhereHas('party', fn (Builder $party) => $party->where('name', 'ilike', $like)));
            });

        // Spec §11.2: totals of active entries only; paid and received apart.
        $totals = (clone $query)->reorder()->where('status', EntryStatus::Active->value)->whereIn('approval_status', ['not_required', 'approved'])
            ->toBase()
            ->selectRaw("COALESCE(SUM(amount) FILTER (WHERE direction = 'paid'), 0)::numeric(20, 2)::text AS paid")
            ->selectRaw("COALESCE(SUM(amount) FILTER (WHERE direction = 'received'), 0)::numeric(20, 2)::text AS received")
            ->first();

        $page = $query->orderByDesc('entry_date')->orderByDesc('id')->paginate(50)->withQueryString();

        return Inertia::render('payments/index', [
            'filters' => [...$filters, 'direction' => $direction],
            'party' => $this->partyOption($filters['party_id']),
            'totals' => ['paid' => (string) ($totals->paid ?? '0.00'), 'received' => (string) ($totals->received ?? '0.00')],
            'payments' => $this->paginated($page, fn (Payment $p): array => [
                'id' => $p->id,
                'voucher_no' => $p->voucher_no,
                'entry_date' => $p->entry_date,
                'direction' => $p->direction->value,
                'party' => $p->party->name,
                'account' => $p->moneyAccount->name,
                'amount' => $p->amount,
                'status' => $p->status->value,
                'approval_status' => $p->approval_status->value,
            ]),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Payment::class);

        $party = $this->partyOption($request->integer('party_id') ?: null);

        return Inertia::render('payments/create', [
            ...$this->formProps(),
            'party' => $party !== null && $party['type'] !== 'partner' ? $party : null,
        ]);
    }

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

        return to_route('payments.show', $payment);
    }

    public function show(Payment $payment): Response
    {
        $this->authorize('view', $payment);

        $payment->load(['party', 'moneyAccount', 'handledBy', 'purchase', 'sale', 'creator']);

        return Inertia::render('payments/show', [
            'payment' => [
                'id' => $payment->id,
                'voucher_no' => $payment->voucher_no,
                'entry_date' => $payment->entry_date,
                'direction' => $payment->direction->value,
                'party' => ['id' => $payment->party->id, 'code' => $payment->party->code, 'name' => $payment->party->name],
                'amount' => $payment->amount,
                'account' => $payment->moneyAccount->name,
                'method' => PaymentInput::method($payment->moneyAccount), // BR-PAY-03
                'reference_no' => $payment->reference_no,
                'handled_by' => $payment->handledBy?->name,
                'note' => $payment->note,
                'status' => $payment->status->value,
                'approval_status' => $payment->approval_status->value,
                'rejection_reason' => $payment->rejection_reason,
                'cancel_reason' => $payment->cancel_reason,
                'created_by' => $payment->creator?->name,
                'created_at' => $payment->created_at?->toIso8601String(),
                'is_active' => $payment->status === EntryStatus::Active,
                'linked' => match (true) {
                    $payment->purchase !== null => ['type' => 'purchase', 'id' => $payment->purchase->id, 'voucher_no' => $payment->purchase->voucher_no],
                    $payment->sale !== null => ['type' => 'sale', 'id' => $payment->sale->id, 'voucher_no' => $payment->sale->voucher_no],
                    default => null,
                },
            ],
            'attachments' => $this->attachmentsOf($payment),
        ]);
    }

    public function edit(Payment $payment): Response
    {
        $this->authorize('update', $payment);

        return Inertia::render('payments/edit', [
            ...$this->formProps(),
            'party' => $this->partyOption($payment->party_id),
            'handled_by' => $this->partyOption($payment->handled_by_party_id),
            'payment' => [
                'id' => $payment->id,
                'voucher_no' => $payment->voucher_no,
                'version' => $payment->updated_at?->toIso8601String(),
                'date' => $payment->entry_date,
                'direction' => $payment->direction->value,
                'party_id' => $payment->party_id,
                'amount' => $payment->amount,
                'money_account_id' => $payment->money_account_id,
                'reference_no' => $payment->reference_no,
                'handled_by_party_id' => $payment->handled_by_party_id,
                'note' => $payment->note,
            ],
        ]);
    }

    public function update(UpdatePaymentRequest $request, Payment $payment, UpdatePayment $action): RedirectResponse
    {
        $this->authorize('update', $payment);

        $action->handle($this->actor($request), $payment, $request->entryData(), $request->confirmedWarnings());
        Flash::saved($payment->voucher_no);

        return to_route('payments.show', $payment);
    }

    public function cancel(CancelEntryRequest $request, Payment $payment, CancelPayment $action): RedirectResponse
    {
        $this->authorize('cancel', $payment);

        $action->handle($this->actor($request), $payment, (string) $request->validated('reason'));
        Flash::saved($payment->voucher_no);

        return to_route('payments.show', $payment);
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
     * @return array<string, mixed>
     */
    private function formProps(): array
    {
        return [
            'today' => Today::date(),
            'money_accounts' => $this->moneyAccountOptions(),
        ];
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
