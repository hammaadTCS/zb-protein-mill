<?php

namespace App\Http\Controllers;

use App\Domain\Expenses\Actions\CancelExpense;
use App\Domain\Expenses\Actions\CreateExpense;
use App\Domain\Expenses\Actions\UpdateExpense;
use App\Domain\Expenses\Enums\ExpensePaymentMode;
use App\Domain\Expenses\Models\Expense;
use App\Domain\Expenses\Models\ExpenseCategory;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\MoneyAccounts\Enums\MoneyAccountType;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Reports\Queries\ChangeHistory;
use App\Domain\Reports\Queries\EntryJournals;
use App\Domain\Sales\Models\Sale;
use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use App\Http\Controllers\Concerns\ListsEntries;
use App\Http\Controllers\Concerns\PresentsEntries;
use App\Http\Requests\CancelEntryRequest;
use App\Http\Requests\Expenses\StoreExpenseRequest;
use App\Http\Requests\Expenses\UpdateExpenseRequest;
use App\Support\EntryStatus;
use App\Support\Flash;
use App\Support\Today;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Pages 12, 13 and 14: expenses (spec §11.9). */
class ExpenseController extends Controller
{
    use ListsEntries;
    use PresentsEntries;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Expense::class);

        $filters = $this->listFilters($request, ['active', 'cancelled']);
        $categoryId = ExpenseCategory::idFromPublic($request->validate(['category_id' => ['nullable', 'string']])['category_id'] ?? null);

        $query = Expense::query()
            ->with(['category', 'moneyAccount', 'party'])
            ->whereBetween('entry_date', [$filters['from'], $filters['to']])
            ->when($categoryId, fn (Builder $q, int $id) => $q->where('category_id', $id))
            ->when($filters['party_id'], fn (Builder $q, int $id) => $q->where('party_id', $id))
            ->when($filters['status'] !== '', fn (Builder $q) => $q->where('status', $filters['status']))
            ->when($filters['q'] !== '', function (Builder $q) use ($filters): void {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']).'%';
                $q->where(fn (Builder $inner) => $inner->where('voucher_no', 'ilike', $like)
                    ->orWhere('note', 'ilike', $like)
                    ->orWhereHas('party', fn (Builder $party) => $party->where('name', 'ilike', $like)));
            });

        // Spec §11.2: the total of active entries only.
        $total = (clone $query)->reorder()->where('status', EntryStatus::Active->value)
            ->toBase()
            ->selectRaw('COALESCE(SUM(amount), 0)::numeric(20, 2)::text AS amount')
            ->value('amount');

        $page = $query->orderByDesc('entry_date')->orderByDesc('id')->paginate(50)->withQueryString();

        return Inertia::render('expenses/index', [
            'filters' => $this->shownFilters([...$filters, 'category_id' => $categoryId]),
            'party' => $this->partyOption($filters['party_id']),
            'categories' => $this->categoryOptions(withInactive: true),
            'total' => (string) ($total ?? '0.00'),
            'expenses' => $this->paginated($page, fn (Expense $e): array => [
                'id' => $e->id,
                'public_id' => $e->public_id,
                'voucher_no' => $e->voucher_no,
                'entry_date' => $e->entry_date,
                'category' => $e->category->name,
                'note' => $e->note,
                'payment_mode' => $e->payment_mode->value,
                'paid_from' => $e->moneyAccount->name ?? $e->party?->name,
                'amount' => $e->amount,
                'status' => $e->status->value,
            ]),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Expense::class);

        return Inertia::render('expenses/create', [
            ...$this->formProps(),
            // BR-EXP-09: today, "Paid" and the default cash account.
            'defaults' => ['date' => Today::date(), 'payment_mode' => ExpensePaymentMode::Paid->value, 'money_account_id' => $this->defaultCashAccountId()],
        ]);
    }

    public function store(StoreExpenseRequest $request, CreateExpense $action): RedirectResponse
    {
        $this->authorize('create', Expense::class);

        $expense = $action->handle($this->actor($request), $request->entryData(), $request->confirmedWarnings());
        $expense->wasRecentlyCreated ? Flash::saved($expense->voucher_no) : Flash::alreadySaved($expense->voucher_no);

        return $request->boolean('add_another')
            ? to_route('expenses.create')
            : to_route('expenses.show', $expense);
    }

    public function show(Expense $expense): Response
    {
        $this->authorize('view', $expense);

        $expense->load(['category', 'moneyAccount', 'party', 'handledBy', 'linkedPurchase', 'linkedSale', 'creator']);

        return Inertia::render('expenses/show', [
            'expense' => [
                'id' => $expense->id,
                'public_id' => $expense->public_id,
                'voucher_no' => $expense->voucher_no,
                'entry_date' => $expense->entry_date,
                'category' => $expense->category->name,
                'amount' => $expense->amount,
                'payment_mode' => $expense->payment_mode->value,
                'payment_mode_label' => $expense->payment_mode->label(),
                'account' => $expense->moneyAccount?->name,
                'party' => $expense->party === null ? null : ['id' => $expense->party->id, 'code' => $expense->party->code, 'name' => $expense->party->name],
                'handled_by' => $expense->handledBy?->name,
                'linked' => $this->linkOf($expense),
                'note' => $expense->note,
                'status' => $expense->status->value,
                'cancel_reason' => $expense->cancel_reason,
                'created_by' => $expense->creator?->name,
                'created_at' => $expense->created_at?->toIso8601String(),
                'is_active' => $expense->status === EntryStatus::Active,
            ],
            'attachments' => $this->attachmentsOf($expense),
            'history' => ChangeHistory::for($expense),
            ...(request()->user()?->can('audit.view') ? ['journal' => EntryJournals::for($expense)] : []), // owner only
        ]);
    }

    public function edit(Expense $expense): Response
    {
        $this->authorize('update', $expense);

        return Inertia::render('expenses/edit', [
            ...$this->formProps(),
            'party' => $this->partyOption($expense->party_id),
            'handled_by' => $this->partyOption($expense->handled_by_party_id),
            'link' => $this->linkOf($expense),
            'expense' => [
                'id' => $expense->id,
                'public_id' => $expense->public_id,
                'voucher_no' => $expense->voucher_no,
                'version' => $expense->updated_at?->toIso8601String(),
                'date' => $expense->entry_date,
                'category_id' => $expense->category_id,
                'amount' => $expense->amount,
                'payment_mode' => $expense->payment_mode->value,
                'money_account_id' => $expense->money_account_id,
                'party_id' => $expense->party_id,
                'handled_by_party_id' => $expense->handled_by_party_id,
                'linked_purchase_id' => $expense->linked_purchase_id,
                'linked_sale_id' => $expense->linked_sale_id,
                'note' => $expense->note,
            ],
        ]);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense, UpdateExpense $action): RedirectResponse
    {
        $this->authorize('update', $expense);

        $action->handle($this->actor($request), $expense, $request->entryData(), $request->confirmedWarnings());
        Flash::saved($expense->voucher_no);

        return to_route('expenses.show', $expense);
    }

    public function cancel(CancelEntryRequest $request, Expense $expense, CancelExpense $action): RedirectResponse
    {
        $this->authorize('cancel', $expense);

        $action->handle($this->actor($request), $expense, (string) $request->validated('reason'));
        Flash::saved($expense->voucher_no);

        return to_route('expenses.show', $expense);
    }

    /**
     * BR-EXP-08: the active purchase or sale with this voucher number, for the
     * "Link to purchase or sale" field. Null when there is none.
     */
    public function linkLookup(Request $request): JsonResponse
    {
        $this->authorize('create', Expense::class);

        $voucher = strtoupper(trim((string) $request->validate(['voucher_no' => ['required', 'string', 'max:20']])['voucher_no']));
        $model = match (true) {
            str_starts_with($voucher, 'PUR-') => Purchase::class,
            str_starts_with($voucher, 'SAL-') => Sale::class,
            default => null,
        };
        $entry = $model === null ? null : $model::query()->with('party')->where('voucher_no', $voucher)->where('status', EntryStatus::Active->value)->first();

        if ($entry === null) {
            return JsonResponse::fromJsonString('null');
        }

        return response()->json([
            'type' => $entry instanceof Purchase ? 'purchase' : 'sale',
            'id' => $entry->id,
            'public_id' => $entry->public_id,
            'voucher_no' => $entry->voucher_no,
            'label' => $entry->voucher_no.' · '.$entry->party->name,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(): array
    {
        $limit = Settings::get(SettingKeys::EXPENSE_PARTY_REQUIRED_LIMIT);

        return [
            'today' => Today::date(),
            'money_accounts' => $this->moneyAccountOptions(),
            'categories' => $this->categoryOptions(withInactive: false),
            'party_required_limit' => is_string($limit) ? $limit : null, // BR-EXP-03
        ];
    }

    /**
     * Active categories by `sort_order` (quick buttons first in the form), or every
     * category for the list filter.
     *
     * @return list<array{id: int, name: string, is_quick: bool}>
     */
    private function categoryOptions(bool $withInactive): array
    {
        return array_values(ExpenseCategory::query()
            ->when(! $withInactive, fn (Builder $q) => $q->active())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (ExpenseCategory $c): array => ['id' => $c->id, 'public_id' => $c->public_id, 'name' => $c->name, 'is_quick' => (bool) $c->is_quick])
            ->all());
    }

    /** BR-EXP-09: the active cash account with the lowest ledger code. */
    private function defaultCashAccountId(): ?int
    {
        $id = MoneyAccount::query()
            ->active()
            ->where('type', MoneyAccountType::Cash->value)
            ->orderBy(LedgerAccount::query()->select('code')->whereColumn('ledger_accounts.id', 'money_accounts.ledger_account_id'))
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @return array{type: string, id: int, voucher_no: string, label: string}|null
     */
    private function linkOf(Expense $expense): ?array
    {
        $entry = $expense->linkedPurchase ?? $expense->linkedSale;

        return $entry === null ? null : [
            'type' => $entry instanceof Purchase ? 'purchase' : 'sale',
            'id' => $entry->id,
            'public_id' => $entry->public_id,
            'voucher_no' => $entry->voucher_no,
            'label' => $entry->voucher_no.' · '.$entry->party->name,
        ];
    }
}
