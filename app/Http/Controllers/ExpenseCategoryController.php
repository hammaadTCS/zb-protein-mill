<?php

namespace App\Http\Controllers;

use App\Domain\Expenses\Actions\CreateExpenseCategory;
use App\Domain\Expenses\Actions\DeactivateExpenseCategory;
use App\Domain\Expenses\Actions\UpdateExpenseCategory;
use App\Domain\Expenses\Models\ExpenseCategory;
use App\Http\Requests\Expenses\ExpenseCategoryRequest;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Page 44, Expense categories (/settings/expense-categories). */
class ExpenseCategoryController extends Controller
{
    public function index(): Response
    {
        $this->authorize('manage', ExpenseCategory::class);

        return Inertia::render('expense-categories/index', [
            'categories' => ExpenseCategory::query()->with('ledgerAccount')->orderBy('sort_order')->orderBy('name')->get()
                ->map(fn (ExpenseCategory $c): array => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'code' => $c->ledgerAccount->code,
                    'is_quick' => $c->is_quick,
                    'sort_order' => $c->sort_order,
                    'is_active' => $c->is_active,
                ]),
        ]);
    }

    public function store(ExpenseCategoryRequest $request, CreateExpenseCategory $action): RedirectResponse
    {
        $this->authorize('create', ExpenseCategory::class);

        /** @var array{name: string, is_quick?: bool, sort_order?: int} $data */
        $data = $request->validated();
        $action->handle($this->actor($request), $data);
        Flash::success(__('Saved.'));

        return to_route('expense-categories.index');
    }

    public function update(ExpenseCategoryRequest $request, ExpenseCategory $category, UpdateExpenseCategory $action): RedirectResponse
    {
        $this->authorize('update', $category);

        /** @var array{name: string, is_quick?: bool, sort_order?: int} $data */
        $data = $request->validated();
        $action->handle($this->actor($request), $category, $data);
        Flash::success(__('Saved.'));

        return to_route('expense-categories.index');
    }

    public function deactivate(Request $request, ExpenseCategory $category, DeactivateExpenseCategory $action): RedirectResponse
    {
        $this->authorize('deactivate', $category);

        $action->handle($this->actor($request), $category);
        Flash::success(__('Saved.'));

        return to_route('expense-categories.index');
    }
}
