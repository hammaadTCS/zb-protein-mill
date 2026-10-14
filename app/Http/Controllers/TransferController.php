<?php

namespace App\Http\Controllers;

use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Domain\Transfers\Actions\CancelTransfer;
use App\Domain\Transfers\Actions\CreateTransfer;
use App\Domain\Transfers\Actions\UpdateTransfer;
use App\Domain\Transfers\Models\Transfer;
use App\Http\Controllers\Concerns\ListsEntries;
use App\Http\Controllers\Concerns\PresentsEntries;
use App\Http\Requests\CancelEntryRequest;
use App\Http\Requests\Transfers\StoreTransferRequest;
use App\Http\Requests\Transfers\UpdateTransferRequest;
use App\Support\EntryStatus;
use App\Support\Flash;
use App\Support\Today;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Pages 15 and 16: transfers between our own accounts (D-22). */
class TransferController extends Controller
{
    use ListsEntries;
    use PresentsEntries;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Transfer::class);

        $filters = $this->listFilters($request, ['active', 'cancelled']);
        $accountId = MoneyAccount::idFromPublic($request->validate(['money_account_id' => ['nullable', 'string']])['money_account_id'] ?? null);

        $query = Transfer::query()
            ->with(['fromAccount', 'toAccount'])
            ->whereBetween('entry_date', [$filters['from'], $filters['to']])
            ->when($accountId, fn (Builder $q, int $id) => $q->where(fn (Builder $inner) => $inner->where('from_account_id', $id)->orWhere('to_account_id', $id)))
            ->when($filters['status'] !== '', fn (Builder $q) => $q->where('status', $filters['status']))
            ->when($filters['q'] !== '', function (Builder $q) use ($filters): void {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']).'%';
                $q->where(fn (Builder $inner) => $inner->where('voucher_no', 'ilike', $like)->orWhere('reference_no', 'ilike', $like));
            });

        // Spec §11.2: the total of active entries only.
        $total = (clone $query)->reorder()->where('status', EntryStatus::Active->value)
            ->toBase()
            ->selectRaw('COALESCE(SUM(amount), 0)::numeric(20, 2)::text AS amount')
            ->value('amount');

        $page = $query->orderByDesc('entry_date')->orderByDesc('id')->paginate(50)->withQueryString();

        return Inertia::render('transfers/index', [
            'filters' => $this->shownFilters([...$filters, 'money_account_id' => $accountId]),
            'money_accounts' => $this->moneyAccountOptions(),
            'total' => (string) ($total ?? '0.00'),
            'transfers' => $this->paginated($page, fn (Transfer $t): array => [
                'id' => $t->id,
                'public_id' => $t->public_id,
                'voucher_no' => $t->voucher_no,
                'entry_date' => $t->entry_date,
                'from' => $t->fromAccount->name,
                'to' => $t->toAccount->name,
                'amount' => $t->amount,
                'reference_no' => $t->reference_no,
                'note' => $t->note,
                'status' => $t->status->value,
                'cancel_reason' => $t->cancel_reason,
            ]),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Transfer::class);

        return Inertia::render('transfers/create', [
            'today' => Today::date(),
            'money_accounts' => $this->moneyAccountOptions(),
        ]);
    }

    public function store(StoreTransferRequest $request, CreateTransfer $action): RedirectResponse
    {
        $this->authorize('create', Transfer::class);

        $transfer = $action->handle($this->actor($request), $request->entryData(), $request->confirmedWarnings());
        $transfer->wasRecentlyCreated ? Flash::saved($transfer->voucher_no) : Flash::alreadySaved($transfer->voucher_no);

        return to_route('transfers.index');
    }

    public function edit(Transfer $transfer): Response
    {
        $this->authorize('update', $transfer);

        return Inertia::render('transfers/edit', [
            'today' => Today::date(),
            'money_accounts' => $this->moneyAccountOptions(),
            'transfer' => [
                'id' => $transfer->id,
                'public_id' => $transfer->public_id,
                'voucher_no' => $transfer->voucher_no,
                'version' => $transfer->updated_at?->toIso8601String(),
                'date' => $transfer->entry_date,
                'from_account_id' => $transfer->from_account_id,
                'to_account_id' => $transfer->to_account_id,
                'amount' => $transfer->amount,
                'reference_no' => $transfer->reference_no,
                'note' => $transfer->note,
            ],
        ]);
    }

    public function update(UpdateTransferRequest $request, Transfer $transfer, UpdateTransfer $action): RedirectResponse
    {
        $this->authorize('update', $transfer);

        $action->handle($this->actor($request), $transfer, $request->entryData(), $request->confirmedWarnings());
        Flash::saved($transfer->voucher_no);

        return to_route('transfers.index');
    }

    public function cancel(CancelEntryRequest $request, Transfer $transfer, CancelTransfer $action): RedirectResponse
    {
        $this->authorize('cancel', $transfer);

        $action->handle($this->actor($request), $transfer, (string) $request->validated('reason'));
        Flash::saved($transfer->voucher_no);

        return back();
    }
}
