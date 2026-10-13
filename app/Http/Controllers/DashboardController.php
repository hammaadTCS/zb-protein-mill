<?php

namespace App\Http\Controllers;

use App\Domain\Alerts\Alerts;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Domain\Reports\Queries\DashboardToday;
use App\Support\Today;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Page 2 (spec §11.3): today, balances, alerts and quick buttons. */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $this->actor($request);
        $today = Today::date();

        return Inertia::render('dashboard', [
            'today' => $today,
            'figures' => DashboardToday::for($today),
            'balances' => $user->can('account.view')
                ? MoneyAccount::query()->active()->orderBy(LedgerAccount::query()->select('code')->whereColumn('ledger_accounts.id', 'money_accounts.ledger_account_id'))->get()
                    ->map(fn (MoneyAccount $account): array => [
                        'id' => $account->id,
                        'name' => $account->name,
                        'type' => $account->type->value,
                        'balance' => $account->balance(),
                    ])->values()
                : [],
            'alerts' => Alerts::for($user),
        ]);
    }
}
