<?php

namespace App\Http\Controllers;

use App\Domain\Checks\Actions\CreateCashCount;
use App\Domain\MoneyAccounts\Enums\MoneyAccountType;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Domain\Periods\Actions\CloseMonth;
use App\Domain\Periods\Actions\PostCashDifference;
use App\Domain\Periods\Actions\RecordBankStatement;
use App\Domain\Periods\Month;
use App\Domain\Periods\MonthEndCheck;
use App\Domain\Periods\MonthEndChecks;
use App\Domain\Periods\Months;
use App\Support\Flash;
use App\Support\Today;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page 30, Month-end close (spec §11.12, D-26): the oldest open month, its checks as
 * steps, the bank and cash inputs, and "Close month".
 */
class MonthEndController extends Controller
{
    public function show(): Response
    {
        Gate::authorize('period.close');

        $month = Months::oldestOpen();
        $ended = $month !== null && $month->lastDay() < Today::date();

        return Inertia::render('month-end/index', [
            'month' => $month === null ? null : [
                'key' => $month->key(),
                'name' => $month->name(),
                'last_day' => $month->lastDay(),
                'ended' => $ended,
            ],
            // Step 2 is shown as it stands; the "Close with pending rates" tick decides it on close.
            'checks' => $month === null || ! $ended ? [] : array_map(fn (MonthEndCheck $check): array => $check->toArray(), MonthEndChecks::run($month, false)),
        ]);
    }

    /** Step 4: a bank statement closing balance (BR-MEC-05). */
    public function bankStatement(Request $request, RecordBankStatement $action): RedirectResponse
    {
        Gate::authorize('period.close');

        $input = $request->validate([
            'period' => ['required', 'string', 'size:7'],
            'money_account_id' => ['required', 'integer'],
            'statement_balance' => ['required', 'string', 'max:20'],
        ]);
        $bank = MoneyAccount::query()->active()->where('type', MoneyAccountType::Bank->value)->findOrFail((int) $input['money_account_id']);
        $action->handle($this->actor($request), (string) $input['period'], $bank, (string) $input['statement_balance']);
        Flash::success(__('Bank statement balance saved.'));

        return to_route('month-end.show');
    }

    /** Step 5: the cash count on the month's last day (BR-MEC-06), as on page 28. */
    public function cashCount(Request $request, CreateCashCount $action): RedirectResponse
    {
        Gate::authorize('period.close');

        $input = $request->validate([
            'period' => ['required', 'string', 'size:7'],
            'money_account_id' => ['required', 'integer'],
            'counted_amount' => ['required', 'string', 'max:20'],
        ]);
        $month = Month::of((string) $input['period']);
        $cash = MoneyAccount::query()->active()->findOrFail((int) $input['money_account_id']);
        $action->handle($this->actor($request), $cash, $month->lastDay(), (string) $input['counted_amount']);
        Flash::success(__('Cash count saved.'));

        return to_route('month-end.show');
    }

    /** Step 5: the Owner posts the remaining cash difference (PR-23, PR-24). */
    public function cashDifference(Request $request, PostCashDifference $action): RedirectResponse
    {
        Gate::authorize('period.close');

        $input = $request->validate([
            'period' => ['required', 'string', 'size:7'],
            'money_account_id' => ['required', 'integer'],
            'amount' => ['required', 'string', 'max:20'],
            'direction' => ['required', 'string', 'in:shortage,excess'],
            'note' => ['required', 'string', 'max:2000'],
        ]);
        $cash = MoneyAccount::query()->active()->findOrFail((int) $input['money_account_id']);
        $voucher = $action->handle($this->actor($request), (string) $input['period'], $cash, (string) $input['amount'], (string) $input['direction'], (string) $input['note']);
        Flash::saved($voucher);

        return to_route('month-end.show');
    }

    /** Step 6: "Close month" (BR-MEC-01 to BR-MEC-06); the checks run again on the server. */
    public function close(Request $request, CloseMonth $action): RedirectResponse
    {
        Gate::authorize('period.close');

        $input = $request->validate([
            'period' => ['required', 'string', 'size:7'],
            'accept_pending_rates' => ['boolean'],
        ]);
        $result = $action->handle($this->actor($request), (string) $input['period'], (bool) ($input['accept_pending_rates'] ?? false));

        if (! $result->closed) {
            return to_route('month-end.show')->withErrors(['period' => __('The month cannot close yet. Fix the steps marked Not done.')]);
        }

        Flash::success(__(':month is closed.', ['month' => $result->month->name()]));

        return to_route('months.index');
    }
}
