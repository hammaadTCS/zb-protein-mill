<?php

namespace App\Http\Controllers;

use App\Domain\Adjustments\Adjustments;
use App\Domain\Adjustments\Models\Adjustment;
use App\Domain\Items\Models\Item;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\PaymentApproval;
use App\Domain\Periods\Periods;
use App\Domain\Purchases\Actions\AddPurchaseRate;
use App\Domain\Purchases\Actions\CancelPurchase;
use App\Domain\Purchases\Actions\CreatePurchase;
use App\Domain\Purchases\Actions\UpdatePurchase;
use App\Domain\Purchases\Enums\RateStatus;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Purchases\Models\RateCorrection;
use App\Domain\Purchases\RateCorrections;
use App\Domain\Reports\Queries\ChangeHistory;
use App\Domain\Reports\Queries\EntryJournals;
use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use App\Http\Controllers\Concerns\ListsEntries;
use App\Http\Controllers\Concerns\PresentsEntries;
use App\Http\Requests\CancelEntryRequest;
use App\Http\Requests\Purchases\AddRateRequest;
use App\Http\Requests\Purchases\StorePurchaseRequest;
use App\Http\Requests\Purchases\UpdatePurchaseRequest;
use App\Support\Decimal;
use App\Support\EntryStatus;
use App\Support\EntryWarnings;
use App\Support\Flash;
use App\Support\Today;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Pages 3, 4 and 5: purchases list, new purchase, purchase detail. */
class PurchaseController extends Controller
{
    use ListsEntries;
    use PresentsEntries;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Purchase::class);

        $filters = $this->listFilters($request, ['active', 'cancelled', 'rate_pending']);

        $query = Purchase::query()
            ->with(['party', 'item'])
            ->whereBetween('entry_date', [$filters['from'], $filters['to']])
            ->when($filters['party_id'], fn (Builder $q, int $id) => $q->where('party_id', $id))
            ->when($filters['status'] === 'rate_pending', fn (Builder $q) => $q->where('status', EntryStatus::Active->value)->where('rate_status', RateStatus::Pending->value))
            ->when(in_array($filters['status'], ['active', 'cancelled'], true), fn (Builder $q) => $q->where('status', $filters['status']))
            ->when($filters['q'] !== '', fn (Builder $q) => $this->searchVoucherOrParty($q, $filters['q']));

        // Spec §11.2: totals of active entries only, for the whole filtered list.
        $totals = (clone $query)->reorder()->where('status', EntryStatus::Active->value)
            ->toBase()
            ->selectRaw('COALESCE(SUM(kg), 0)::numeric(20, 3)::text AS kg, COALESCE(SUM(amount), 0)::numeric(20, 2)::text AS amount')
            ->first();

        $page = $query->orderByDesc('entry_date')->orderByDesc('id')->paginate(50)->withQueryString();

        return Inertia::render('purchases/index', [
            'filters' => $filters,
            'party' => $this->partyOption($filters['party_id']),
            'totals' => ['kg' => (string) ($totals->kg ?? '0.000'), 'amount' => (string) ($totals->amount ?? '0.00')],
            'purchases' => $this->paginated($page, fn (Purchase $p): array => [
                'id' => $p->id,
                'voucher_no' => $p->voucher_no,
                'entry_date' => $p->entry_date,
                'party' => $p->party->name,
                'item' => $p->item->name,
                'kg' => $p->kg,
                'rate' => $p->rate,
                'amount' => $p->amount,
                'rate_status' => $p->rate_status->value,
                'status' => $p->status->value,
            ]),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Purchase::class);

        // Opened from a party profile: the supplier is pre-selected (T2.9).
        $party = $this->partyOption($request->integer('party_id') ?: null);

        return Inertia::render('purchases/create', [
            ...$this->formProps(),
            'party' => $party !== null && $party['type'] === 'supplier' ? $party : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(): array
    {
        return [
            'today' => Today::date(),
            'items' => Item::query()->active()->orderBy('id')->get()
                ->filter(fn (Item $item): bool => $item->type->isBought())
                ->map(fn (Item $item): array => ['id' => $item->id, 'name' => $item->name])
                ->values(),
            'money_accounts' => $this->moneyAccountOptions(),
            'plant_has_scale' => Settings::get(SettingKeys::PLANT_HAS_SCALE) === true,
        ];
    }

    public function store(StorePurchaseRequest $request, CreatePurchase $action): RedirectResponse
    {
        $this->authorize('create', Purchase::class);

        $purchase = $action->handle($this->actor($request), $request->entryData(), $request->confirmedWarnings());

        // BR-GEN-11: a repeated save shows "Already saved. {voucher}".
        $purchase->wasRecentlyCreated ? Flash::saved($purchase->voucher_no) : Flash::alreadySaved($purchase->voucher_no);

        if ($purchase->wasRecentlyCreated && $purchase->payments()->where('approval_status', 'pending')->exists()) {
            Inertia::flash(['toast' => ['type' => 'info', 'message' => __(PaymentApproval::PENDING_MESSAGE)]]); // BR-PAY-08
        }

        return $request->boolean('add_another')
            ? to_route('purchases.create')
            : to_route('purchases.show', $purchase);
    }

    public function show(Purchase $purchase): Response
    {
        $this->authorize('view', $purchase);

        $purchase->load(['party', 'item', 'creator', 'payments.moneyAccount']);

        return Inertia::render('purchases/show', [
            'purchase' => [
                'id' => $purchase->id,
                'voucher_no' => $purchase->voucher_no,
                'entry_date' => $purchase->entry_date,
                'party' => ['id' => $purchase->party->id, 'code' => $purchase->party->code, 'name' => $purchase->party->name],
                'item' => $purchase->item->name,
                'kg' => $purchase->kg,
                'rate' => $purchase->rate,
                'amount' => $purchase->amount,
                'rate_status' => $purchase->rate_status->value,
                'slip_no' => $purchase->slip_no,
                'our_weight_kg' => $purchase->our_weight_kg,
                'note' => $purchase->note,
                'status' => $purchase->status->value,
                'cancel_reason' => $purchase->cancel_reason,
                'can_add_rate' => $purchase->status === EntryStatus::Active && $purchase->rate_status === RateStatus::Pending,
                'is_active' => $purchase->status === EntryStatus::Active,
                'created_by' => $purchase->creator?->name,
                'created_at' => $purchase->created_at?->toIso8601String(),
            ],
            'today' => Today::date(),
            'attachments' => $this->attachmentsOf($purchase),
            'adjustments' => self::adjustmentRows($purchase),
            'rate_corrections' => self::rateCorrectionRows($purchase),
            'effective' => RateCorrections::effective($purchase),
            // BR-PUR-14: in a closed month Edit and Cancel entry give way to "Correct rate".
            'month_closed' => Periods::isClosed($purchase->entry_date),
            'adjustment_max' => $purchase->rate_status === RateStatus::Fixed ? Adjustments::maximum($purchase) : null, // BR-ADJ-01, BR-ADJ-03
            'history' => ChangeHistory::for($purchase),
            ...(request()->user()?->can('audit.view') ? ['journal' => EntryJournals::for($purchase)] : []), // owner only
            'payments' => $purchase->payments->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'voucher_no' => $payment->voucher_no,
                'amount' => $payment->amount,
                'account' => $payment->moneyAccount->name,
                'status' => $payment->status->value,
                'approval_status' => $payment->approval_status->value,
            ])->values(),
        ]);
    }

    /** Edit (BR-PUR-11): the create form with the saved values and `version` (BR-GEN-09). */
    public function edit(Purchase $purchase): Response
    {
        $this->authorize('update', $purchase);

        $purchase->load(['party']);

        return Inertia::render('purchases/edit', [
            ...$this->formProps(),
            'party' => $this->partyOption($purchase->party_id),
            'purchase' => [
                'id' => $purchase->id,
                'voucher_no' => $purchase->voucher_no,
                'version' => $purchase->updated_at?->toIso8601String(),
                'date' => $purchase->entry_date,
                'party_id' => $purchase->party_id,
                'item_id' => $purchase->item_id,
                'kg' => $purchase->kg,
                'rate' => $purchase->rate,
                'slip_no' => $purchase->slip_no,
                'our_weight_kg' => $purchase->our_weight_kg,
                'note' => $purchase->note,
            ],
        ]);
    }

    public function update(UpdatePurchaseRequest $request, Purchase $purchase, UpdatePurchase $action): RedirectResponse
    {
        $this->authorize('update', $purchase);

        $action->handle($this->actor($request), $purchase, $request->entryData(), $request->confirmedWarnings());
        Flash::saved($purchase->voucher_no);

        return to_route('purchases.show', $purchase);
    }

    /** "Cancel entry" with a reason; optionally the linked payment too (BR-PUR-12). */
    public function cancel(CancelEntryRequest $request, Purchase $purchase, CancelPurchase $action): RedirectResponse
    {
        $this->authorize('cancel', $purchase);

        $action->handle($this->actor($request), $purchase, (string) $request->validated('reason'), $request->boolean('cancel_linked_payment'));
        Flash::saved($purchase->voucher_no);

        return to_route('purchases.show', $purchase);
    }

    /**
     * BR-PUR-05: the last fixed rate for this supplier and item, shown under the rate
     * field. Never filled in automatically.
     */
    public function lastRate(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Purchase::class);

        $input = $request->validate(['party_id' => ['required', 'integer'], 'item_id' => ['required', 'integer']]);
        $last = EntryWarnings::lastRate(Purchase::class, (int) $input['party_id'], (int) $input['item_id']);
        $decimals = Settings::get(SettingKeys::RATE_DECIMALS);

        if ($last === null) {
            return JsonResponse::fromJsonString('null');
        }

        return response()->json([
            'rate' => Decimal::toScale($last['rate'], is_int($decimals) ? max(0, $decimals) : 2),
            'date' => $last['date'],
        ]);
    }

    /** "Add rate" on page 5 and on the rate-pending list (BR-PUR-04). */
    public function addRate(AddRateRequest $request, Purchase $purchase, AddPurchaseRate $action): RedirectResponse
    {
        $this->authorize('update', $purchase);

        $action->handle($this->actor($request), $purchase, (string) $request->validated('rate'), (string) $request->validated('date'), $request->confirmedWarnings());

        Flash::saved($purchase->voucher_no);

        return back();
    }

    /**
     * @param  Builder<Purchase>  $query
     */
    private function searchVoucherOrParty(Builder $query, string $text): void
    {
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $text).'%';

        $query->where(fn (Builder $q) => $q
            ->where('voucher_no', 'ilike', $like)
            ->orWhereHas('party', fn (Builder $party) => $party->where('name', 'ilike', $like)));
    }

    /**
     * The agreed adjustments listed on the detail page (spec §11.6).
     *
     * @return list<array<string, mixed>>
     */
    private static function adjustmentRows(Purchase $purchase): array
    {
        return array_values($purchase->adjustments()->orderBy('id')->get()->map(fn (Adjustment $a): array => [
            'id' => $a->id,
            'voucher_no' => $a->voucher_no,
            'entry_date' => $a->entry_date,
            'amount' => $a->amount,
            'reason' => $a->reason->value,
            'note' => $a->note,
            'status' => $a->status->value,
            'cancel_reason' => $a->cancel_reason,
        ])->all());
    }

    /**
     * Rate corrections of an entry in a closed month (BR-LED-09).
     *
     * @return list<array<string, mixed>>
     */
    private static function rateCorrectionRows(Purchase $purchase): array
    {
        return array_values(RateCorrection::query()->where('purchase_id', $purchase->id)->orderBy('id')->get()->map(fn (RateCorrection $c): array => [
            'id' => $c->id,
            'voucher_no' => $c->voucher_no,
            'correction_date' => $c->correction_date,
            'old_rate' => $c->old_rate,
            'new_rate' => $c->new_rate,
            'difference' => $c->difference,
            'reason' => $c->reason,
        ])->all());
    }
}
