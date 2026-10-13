<?php

namespace App\Http\Controllers;

use App\Domain\MoneyAccounts\Actions\CreateMoneyAccount;
use App\Domain\MoneyAccounts\Actions\DeactivateMoneyAccount;
use App\Domain\MoneyAccounts\Actions\UpdateMoneyAccount;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Http\Requests\MoneyAccounts\StoreMoneyAccountRequest;
use App\Http\Requests\MoneyAccounts\UpdateMoneyAccountRequest;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Page 46, Accounts setup (/settings/accounts). */
class MoneyAccountController extends Controller
{
    public function index(): Response
    {
        $this->authorize('manage', MoneyAccount::class);

        $accounts = MoneyAccount::query()
            ->with('ledgerAccount')
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(fn (MoneyAccount $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type->value,
                'bank_name' => $account->bank_name,
                'account_title' => $account->account_title,
                'account_number' => $account->account_number,
                'code' => $account->ledgerAccount->code,
                'balance' => $account->balance(),
                'is_active' => $account->is_active,
            ]);

        return Inertia::render('money-accounts/index', ['accounts' => $accounts]);
    }

    public function store(StoreMoneyAccountRequest $request, CreateMoneyAccount $action): RedirectResponse
    {
        $this->authorize('create', MoneyAccount::class);

        /** @var array{name: string, type: string, bank_name?: string|null, account_title?: string|null, account_number?: string|null} $data */
        $data = $request->validated();
        $action->handle($this->actor($request), $data);

        Flash::success(__('Saved.'));

        return to_route('money-accounts.index');
    }

    public function update(UpdateMoneyAccountRequest $request, MoneyAccount $moneyAccount, UpdateMoneyAccount $action): RedirectResponse
    {
        $this->authorize('update', $moneyAccount);

        /** @var array{name: string, bank_name?: string|null, account_title?: string|null, account_number?: string|null} $data */
        $data = $request->validated();
        $action->handle($this->actor($request), $moneyAccount, $data);

        Flash::success(__('Saved.'));

        return to_route('money-accounts.index');
    }

    public function deactivate(Request $request, MoneyAccount $moneyAccount, DeactivateMoneyAccount $action): RedirectResponse
    {
        $this->authorize('deactivate', $moneyAccount);

        $action->handle($this->actor($request), $moneyAccount);

        Flash::success(__('Saved.'));

        return to_route('money-accounts.index');
    }
}
