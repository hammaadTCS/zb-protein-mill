<?php

namespace App\Http\Controllers;

use App\Domain\Adjustments\Adjustments;
use App\Domain\Adjustments\Models\Adjustment;
use App\Domain\Items\Models\Item;
use App\Domain\Payments\Models\Payment;
use App\Domain\Periods\Periods;
use App\Domain\Purchases\Enums\RateStatus;
use App\Domain\Purchases\Models\RateCorrection;
use App\Domain\Purchases\RateCorrections;
use App\Domain\Reports\Queries\ChangeHistory;
use App\Domain\Reports\Queries\EntryJournals;
use App\Domain\Sales\Actions\AddSaleRate;
use App\Domain\Sales\Actions\CancelSale;
use App\Domain\Sales\Actions\CreateSale;
use App\Domain\Sales\Actions\UpdateSale;
use App\Domain\Sales\Models\Sale;
use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use App\Http\Controllers\Concerns\ListsEntries;
use App\Http\Controllers\Concerns\PresentsEntries;
use App\Http\Requests\CancelEntryRequest;
use App\Http\Requests\Purchases\AddRateRequest;
use App\Http\Requests\Sales\StoreSaleRequest;
use App\Http\Requests\Sales\UpdateSaleRequest;
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

/** Pages 6, 7 and 8: sales list, new sale, sale detail (spec §11.5: like purchases). */
class SaleController extends Controller
{
    use ListsEntries;
    use PresentsEntries;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Sale::class);

        $filters = $this->listFilters($request, ['active', 'cancelled', 'rate_pending']);

        $query = Sale::query()
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

        return Inertia::render('sales/index', [
            'filters' => $filters,
            'party' => $this->partyOption($filters['party_id']),
            'totals' => ['kg' => (string) ($totals->kg ?? '0.000'), 'amount' => (string) ($totals->amount ?? '0.00')],
            'sales' => $this->paginated($page, fn (Sale $p): array => [
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
        $this->authorize('create', Sale::class);

        // Opened from a party profile: the buyer is pre-selected (T2.9).
        $party = $this->partyOption($request->integer('party_id') ?: null);

        return Inertia::render('sales/create', [
            ...$this->formProps(),
            'party' => $party !== null && $party['type'] === 'buyer' ? $party : null,
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
                ->filter(fn (Item $item): bool => ! $item->type->isBought())
                ->map(fn (Item $item): array => ['id' => $item->id, 'name' => $item->name])
                ->values(),
            'money_accounts' => $this->moneyAccountOptions(),
            'plant_has_scale' => Settings::get(SettingKeys::PLANT_HAS_SCALE) === true,
        ];
    }

    public function store(StoreSaleRequest $request, CreateSale $action): RedirectResponse
    {
        $this->authorize('create', Sale::class);

        $sale = $action->handle($this->actor($request), $request->entryData(), $request->confirmedWarnings());

        // BR-GEN-11: a repeated save shows "Already saved. {voucher}".
        $sale->wasRecentlyCreated ? Flash::saved($sale->voucher_no) : Flash::alreadySaved($sale->voucher_no);

        return $request->boolean('add_another')
            ? to_route('sales.create')
            : to_route('sales.show', $sale);
    }

    public function show(Sale $sale): Response
    {
        $this->authorize('view', $sale);

        $sale->load(['party', 'item', 'creator', 'payments.moneyAccount']);

        return Inertia::render('sales/show', [
            'sale' => [
                'id' => $sale->id,
                'voucher_no' => $sale->voucher_no,
                'entry_date' => $sale->entry_date,
                'party' => ['id' => $sale->party->id, 'code' => $sale->party->code, 'name' => $sale->party->name],
                'item' => $sale->item->name,
                'kg' => $sale->kg,
                'rate' => $sale->rate,
                'amount' => $sale->amount,
                'rate_status' => $sale->rate_status->value,
                'slip_no' => $sale->slip_no,
                'our_weight_kg' => $sale->our_weight_kg,
                'note' => $sale->note,
                'status' => $sale->status->value,
                'cancel_reason' => $sale->cancel_reason,
                'can_add_rate' => $sale->status === EntryStatus::Active && $sale->rate_status === RateStatus::Pending,
                'is_active' => $sale->status === EntryStatus::Active,
                'created_by' => $sale->creator?->name,
                'created_at' => $sale->created_at?->toIso8601String(),
            ],
            'today' => Today::date(),
            'attachments' => $this->attachmentsOf($sale),
            'adjustments' => self::adjustmentRows($sale),
            'rate_corrections' => self::rateCorrectionRows($sale),
            'effective' => RateCorrections::effective($sale),
            // BR-PUR-14: in a closed month Edit and Cancel entry give way to "Correct rate".
            'month_closed' => Periods::isClosed($sale->entry_date),
            'adjustment_max' => $sale->rate_status === RateStatus::Fixed ? Adjustments::maximum($sale) : null, // BR-ADJ-01, BR-ADJ-03
            'history' => ChangeHistory::for($sale),
            ...(request()->user()?->can('audit.view') ? ['journal' => EntryJournals::for($sale)] : []), // owner only
            'payments' => $sale->payments->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'voucher_no' => $payment->voucher_no,
                'amount' => $payment->amount,
                'account' => $payment->moneyAccount->name,
                'status' => $payment->status->value,
                'approval_status' => $payment->approval_status->value,
            ])->values(),
        ]);
    }

    /** Edit (BR-PUR-11 for sales): the create form with the saved values and `version` (BR-GEN-09). */
    public function edit(Sale $sale): Response
    {
        $this->authorize('update', $sale);

        $sale->load(['party']);

        return Inertia::render('sales/edit', [
            ...$this->formProps(),
            'party' => $this->partyOption($sale->party_id),
            'sale' => [
                'id' => $sale->id,
                'voucher_no' => $sale->voucher_no,
                'version' => $sale->updated_at?->toIso8601String(),
                'date' => $sale->entry_date,
                'party_id' => $sale->party_id,
                'item_id' => $sale->item_id,
                'kg' => $sale->kg,
                'rate' => $sale->rate,
                'slip_no' => $sale->slip_no,
                'our_weight_kg' => $sale->our_weight_kg,
                'note' => $sale->note,
            ],
        ]);
    }

    public function update(UpdateSaleRequest $request, Sale $sale, UpdateSale $action): RedirectResponse
    {
        $this->authorize('update', $sale);

        $action->handle($this->actor($request), $sale, $request->entryData(), $request->confirmedWarnings());
        Flash::saved($sale->voucher_no);

        return to_route('sales.show', $sale);
    }

    /** "Cancel entry" with a reason; optionally the linked receipt too (BR-PUR-12). */
    public function cancel(CancelEntryRequest $request, Sale $sale, CancelSale $action): RedirectResponse
    {
        $this->authorize('cancel', $sale);

        $action->handle($this->actor($request), $sale, (string) $request->validated('reason'), $request->boolean('cancel_linked_payment'));
        Flash::saved($sale->voucher_no);

        return to_route('sales.show', $sale);
    }

    /**
     * BR-PUR-05 for sales: the last fixed rate for this buyer and item, shown under the rate
     * field. Never filled in automatically.
     */
    public function lastRate(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Sale::class);

        $input = $request->validate(['party_id' => ['required', 'integer'], 'item_id' => ['required', 'integer']]);
        $last = EntryWarnings::lastRate(Sale::class, (int) $input['party_id'], (int) $input['item_id']);
        $decimals = Settings::get(SettingKeys::RATE_DECIMALS);

        if ($last === null) {
            return JsonResponse::fromJsonString('null');
        }

        return response()->json([
            'rate' => Decimal::toScale($last['rate'], is_int($decimals) ? max(0, $decimals) : 2),
            'date' => $last['date'],
        ]);
    }

    /** "Add rate" on page 8 and on the rate-pending list (BR-PUR-04 for sales). */
    public function addRate(AddRateRequest $request, Sale $sale, AddSaleRate $action): RedirectResponse
    {
        $this->authorize('update', $sale);

        $action->handle($this->actor($request), $sale, (string) $request->validated('rate'), (string) $request->validated('date'), $request->confirmedWarnings());

        Flash::saved($sale->voucher_no);

        return back();
    }

    /**
     * @param  Builder<Sale>  $query
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
    private static function adjustmentRows(Sale $sale): array
    {
        return array_values($sale->adjustments()->orderBy('id')->get()->map(fn (Adjustment $a): array => [
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
    private static function rateCorrectionRows(Sale $sale): array
    {
        return array_values(RateCorrection::query()->where('sale_id', $sale->id)->orderBy('id')->get()->map(fn (RateCorrection $c): array => [
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
