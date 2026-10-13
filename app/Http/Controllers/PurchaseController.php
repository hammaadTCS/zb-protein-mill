<?php

namespace App\Http\Controllers;

use App\Domain\Items\Models\Item;
use App\Domain\Payments\Models\Payment;
use App\Domain\Purchases\Actions\CreatePurchase;
use App\Domain\Purchases\Enums\RateStatus;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use App\Http\Controllers\Concerns\ListsEntries;
use App\Http\Controllers\Concerns\PresentsEntries;
use App\Http\Requests\Purchases\StorePurchaseRequest;
use App\Support\EntryStatus;
use App\Support\Flash;
use App\Support\Today;
use Illuminate\Database\Eloquent\Builder;
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
            'today' => Today::date(),
            'party' => $party !== null && $party['type'] === 'supplier' ? $party : null,
            'items' => Item::query()->active()->orderBy('id')->get()
                ->filter(fn (Item $item): bool => $item->type->isBought())
                ->map(fn (Item $item): array => ['id' => $item->id, 'name' => $item->name])
                ->values(),
            'money_accounts' => $this->moneyAccountOptions(),
            'plant_has_scale' => Settings::get(SettingKeys::PLANT_HAS_SCALE) === true,
        ]);
    }

    public function store(StorePurchaseRequest $request, CreatePurchase $action): RedirectResponse
    {
        $this->authorize('create', Purchase::class);

        $purchase = $action->handle($this->actor($request), $request->entryData(), $request->confirmedWarnings());

        // BR-GEN-11: a repeated save shows "Already saved. {voucher}".
        $purchase->wasRecentlyCreated ? Flash::saved($purchase->voucher_no) : Flash::alreadySaved($purchase->voucher_no);

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
                'created_by' => $purchase->creator?->name,
                'created_at' => $purchase->created_at?->toIso8601String(),
            ],
            'attachments' => $this->attachmentsOf($purchase),
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
}
