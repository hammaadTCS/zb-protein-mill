<?php

namespace App\Http\Controllers;

use App\Domain\Alerts\Providers\MissingCashCount;
use App\Domain\Checks\Actions\CreateCashCount;
use App\Domain\Checks\Actions\DismissCashCount;
use App\Domain\Checks\CashCounts;
use App\Domain\Checks\Models\CashCount;
use App\Domain\MoneyAccounts\Enums\MoneyAccountType;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Support\Flash;
use App\Support\Today;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Page 28, Daily cash check (D-25, BR-CHK-01 to BR-CHK-04). */
class CashCountController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('cashcount.create');

        $missing = MissingCashCount::missing();

        return Inertia::render('cash-counts/index', [
            'today' => Today::date(),
            'cash_accounts' => MoneyAccount::query()->active()->where('type', MoneyAccountType::Cash->value)->orderBy('id')->get()
                ->map(fn (MoneyAccount $a): array => ['id' => $a->id, 'name' => $a->name])->values(),
            'missing' => $missing === [] ? null : [
                'date' => MissingCashCount::yesterday(),
                'accounts' => array_map(fn (MoneyAccount $a): array => ['id' => $a->id, 'name' => $a->name], $missing),
            ],
            'can_dismiss' => $request->user()?->can('period.close') ?? false,
            'history' => CashCount::query()->with(['moneyAccount', 'counter'])->orderByDesc('count_date')->orderByDesc('id')->limit(60)->get()
                ->map(fn (CashCount $c): array => [
                    'id' => $c->id,
                    'date' => $c->count_date,
                    'account' => $c->moneyAccount->name,
                    'counted' => $c->counted_amount,
                    'system' => $c->system_amount,
                    'difference' => $c->difference,
                    'business_closed' => $c->business_closed,
                    'note' => $c->note,
                    'user' => $c->counter->name,
                    'at' => $c->created_at?->toIso8601String(),
                ])->values(),
        ]);
    }

    /** BR-CHK-02: the system amount for the chosen account and date (the form refreshes it). */
    public function systemAmount(Request $request): JsonResponse
    {
        Gate::authorize('cashcount.create');

        $input = $request->validate(['money_account_id' => ['required', 'integer'], 'date' => ['required', 'date_format:Y-m-d']]);
        $cash = MoneyAccount::query()->findOrFail((int) $input['money_account_id']);
        CashCounts::assertCash($cash);

        return response()->json(['system_amount' => CashCounts::systemAmount($cash, (string) $input['date'])]);
    }

    public function store(Request $request, CreateCashCount $action): RedirectResponse
    {
        Gate::authorize('cashcount.create');

        $input = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'money_account_id' => ['required', 'integer'],
            'counted_amount' => ['required', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $cash = MoneyAccount::query()->active()->findOrFail((int) $input['money_account_id']);
        $count = $action->handle($this->actor($request), $cash, (string) $input['date'], (string) $input['counted_amount'], $input['note'] ?? null);
        Flash::success(__('Cash count saved. Difference :difference.', ['difference' => (string) $count->difference]));

        return to_route('cash-counts.index');
    }

    /** BR-CHK-04: Owner only ("Mark business closed"). */
    public function dismiss(Request $request, DismissCashCount $action): RedirectResponse
    {
        Gate::authorize('period.close');

        $input = $request->validate(['date' => ['required', 'date_format:Y-m-d'], 'money_account_id' => ['required', 'integer']]);
        $cash = MoneyAccount::query()->findOrFail((int) $input['money_account_id']);
        $action->handle($this->actor($request), $cash, (string) $input['date']);
        Flash::success(__('Marked business closed.'));

        return to_route('cash-counts.index');
    }
}
