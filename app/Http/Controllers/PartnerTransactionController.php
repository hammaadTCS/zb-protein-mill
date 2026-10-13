<?php

namespace App\Http\Controllers;

use App\Domain\Partners\Actions\CancelPartnerTransaction;
use App\Domain\Partners\Actions\CreatePartnerTransaction;
use App\Domain\Partners\Actions\UpdatePartnerTransaction;
use App\Domain\Partners\Enums\PartnerTransactionType;
use App\Domain\Partners\Models\PartnerTransaction;
use App\Domain\Reports\Queries\PartnerStatement;
use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use App\Http\Controllers\Concerns\ListsEntries;
use App\Http\Controllers\Concerns\PresentsEntries;
use App\Http\Requests\CancelEntryRequest;
use App\Http\Requests\Partners\StorePartnerTransactionRequest;
use App\Http\Requests\Partners\UpdatePartnerTransactionRequest;
use App\Support\EntryStatus;
use App\Support\Flash;
use App\Support\Today;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Pages 21, 22 and 23: partner money and the partner statement (R-09). */
class PartnerTransactionController extends Controller
{
    use ListsEntries;
    use PresentsEntries;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PartnerTransaction::class);

        $filters = $this->listFilters($request, ['active', 'cancelled']);
        $type = (string) ($request->validate(['type' => ['nullable', Rule::enum(PartnerTransactionType::class)]])['type'] ?? '');

        $query = PartnerTransaction::query()
            ->with(['party', 'moneyAccount'])
            ->whereBetween('entry_date', [$filters['from'], $filters['to']])
            ->when($type !== '', fn (Builder $q) => $q->where('type', $type))
            ->when($filters['party_id'], fn (Builder $q, int $id) => $q->where('party_id', $id))
            ->when($filters['status'] !== '', fn (Builder $q) => $q->where('status', $filters['status']))
            ->when($filters['q'] !== '', function (Builder $q) use ($filters): void {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']).'%';
                $q->where(fn (Builder $inner) => $inner->where('voucher_no', 'ilike', $like)
                    ->orWhereHas('party', fn (Builder $party) => $party->where('name', 'ilike', $like)));
            });

        // Spec §11.2: totals of active entries only; investments and withdrawals apart.
        $totals = (clone $query)->reorder()->where('status', EntryStatus::Active->value)
            ->toBase()
            ->selectRaw("COALESCE(SUM(amount) FILTER (WHERE type = 'investment'), 0)::numeric(20, 2)::text AS invested")
            ->selectRaw("COALESCE(SUM(amount) FILTER (WHERE type = 'withdrawal'), 0)::numeric(20, 2)::text AS withdrawn")
            ->first();

        $page = $query->orderByDesc('entry_date')->orderByDesc('id')->paginate(50)->withQueryString();

        return Inertia::render('partners/index', [
            'filters' => [...$filters, 'type' => $type],
            'party' => $this->partyOption($filters['party_id']),
            'totals' => ['invested' => (string) ($totals->invested ?? '0.00'), 'withdrawn' => (string) ($totals->withdrawn ?? '0.00')],
            'entries' => $this->paginated($page, fn (PartnerTransaction $t): array => [
                'id' => $t->id,
                'voucher_no' => $t->voucher_no,
                'entry_date' => $t->entry_date,
                'type' => $t->type->value,
                'partner' => $t->party->name,
                'account' => $t->moneyAccount->name,
                'amount' => $t->amount,
                'note' => $t->note,
                'status' => $t->status->value,
                'cancel_reason' => $t->cancel_reason,
            ]),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', PartnerTransaction::class);

        $party = $this->partyOption($request->integer('party_id') ?: null);

        return Inertia::render('partners/create', [
            'today' => Today::date(),
            'money_accounts' => $this->moneyAccountOptions(),
            'party' => $party !== null && $party['type'] === 'partner' ? $party : null,
        ]);
    }

    public function store(StorePartnerTransactionRequest $request, CreatePartnerTransaction $action): RedirectResponse
    {
        $this->authorize('create', PartnerTransaction::class);

        $entry = $action->handle($this->actor($request), $request->entryData(), $request->confirmedWarnings());
        $entry->wasRecentlyCreated ? Flash::saved($entry->voucher_no) : Flash::alreadySaved($entry->voucher_no);

        return to_route('partners.index');
    }

    public function edit(PartnerTransaction $partnerTransaction): Response
    {
        $this->authorize('update', $partnerTransaction);

        return Inertia::render('partners/edit', [
            'today' => Today::date(),
            'money_accounts' => $this->moneyAccountOptions(),
            'party' => $this->partyOption($partnerTransaction->party_id),
            'entry' => [
                'id' => $partnerTransaction->id,
                'voucher_no' => $partnerTransaction->voucher_no,
                'version' => $partnerTransaction->updated_at?->toIso8601String(),
                'date' => $partnerTransaction->entry_date,
                'party_id' => $partnerTransaction->party_id,
                'type' => $partnerTransaction->type->value,
                'amount' => $partnerTransaction->amount,
                'money_account_id' => $partnerTransaction->money_account_id,
                'note' => $partnerTransaction->note,
            ],
        ]);
    }

    public function update(UpdatePartnerTransactionRequest $request, PartnerTransaction $partnerTransaction, UpdatePartnerTransaction $action): RedirectResponse
    {
        $this->authorize('update', $partnerTransaction);

        $action->handle($this->actor($request), $partnerTransaction, $request->entryData(), $request->confirmedWarnings());
        Flash::saved($partnerTransaction->voucher_no);

        return to_route('partners.index');
    }

    public function cancel(CancelEntryRequest $request, PartnerTransaction $partnerTransaction, CancelPartnerTransaction $action): RedirectResponse
    {
        $this->authorize('cancel', $partnerTransaction);

        $action->handle($this->actor($request), $partnerTransaction, (string) $request->validated('reason'));
        Flash::saved($partnerTransaction->voucher_no);

        return back();
    }

    /** Page 23, R-09: invested, withdrawn and net per partner for a date range. */
    public function statement(Request $request): Response
    {
        $this->authorize('viewAny', PartnerTransaction::class);

        $input = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'party_id' => ['nullable', 'integer'],
        ]);
        $start = Settings::get(SettingKeys::SYSTEM_START_DATE);
        $from = $input['from'] ?? (is_string($start) ? $start : substr(Today::date(), 0, 8).'01');
        $to = $input['to'] ?? Today::date();
        $partyId = isset($input['party_id']) ? (int) $input['party_id'] : null;

        return Inertia::render('partners/statement', [
            'filters' => ['from' => $from, 'to' => $to, 'party_id' => $partyId],
            'party' => $this->partyOption($partyId),
            'rows' => PartnerStatement::between($from, $to, $partyId),
        ]);
    }
}
