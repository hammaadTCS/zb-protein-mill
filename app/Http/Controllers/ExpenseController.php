<?php

namespace App\Http\Controllers;

use App\Domain\Expenses\Actions\CancelExpense;
use App\Domain\Expenses\Actions\CreateExpense;
use App\Domain\Expenses\Actions\UpdateExpense;
use App\Domain\Expenses\Models\Expense;
use App\Http\Requests\CancelEntryRequest;
use App\Http\Requests\Expenses\StoreExpenseRequest;
use App\Http\Requests\Expenses\UpdateExpenseRequest;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;

/** Expenses (pages 12 to 14 come with T4.3). */
class ExpenseController extends Controller
{
    public function store(StoreExpenseRequest $request, CreateExpense $action): RedirectResponse
    {
        $this->authorize('create', Expense::class);

        $expense = $action->handle($this->actor($request), $request->entryData(), $request->confirmedWarnings());
        $expense->wasRecentlyCreated ? Flash::saved($expense->voucher_no) : Flash::alreadySaved($expense->voucher_no);

        return back();
    }

    public function update(UpdateExpenseRequest $request, Expense $expense, UpdateExpense $action): RedirectResponse
    {
        $this->authorize('update', $expense);

        $action->handle($this->actor($request), $expense, $request->entryData(), $request->confirmedWarnings());
        Flash::saved($expense->voucher_no);

        return back();
    }

    public function cancel(CancelEntryRequest $request, Expense $expense, CancelExpense $action): RedirectResponse
    {
        $this->authorize('cancel', $expense);

        $action->handle($this->actor($request), $expense, (string) $request->validated('reason'));
        Flash::saved($expense->voucher_no);

        return back();
    }
}
