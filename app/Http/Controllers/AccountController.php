<?php

namespace App\Http\Controllers;

use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Domain\Reports\Queries\AccountStatement;
use App\Support\Decimal;
use App\Support\Today;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Pages 24 and 25 (R-06): cash and bank balances, and each account's statement. */
class AccountController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', MoneyAccount::class);

        $today = Today::date();

        return Inertia::render('accounts/index', [
            'today' => $today,
            'accounts' => MoneyAccount::query()
                ->orderBy(LedgerAccount::query()->select('code')->whereColumn('ledger_accounts.id', 'money_accounts.ledger_account_id'))
                ->get()
                ->map(fn (MoneyAccount $account): array => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'type' => $account->type->value,
                    'is_active' => $account->is_active,
                    'balance' => $account->balance($today),
                ])
                ->filter(fn (array $row): bool => $row['is_active'] || ! Decimal::isZero($row['balance']))
                ->values(),
        ]);
    }

    public function show(Request $request, MoneyAccount $moneyAccount): Response
    {
        $this->authorize('view', $moneyAccount);

        $today = Today::date();
        $input = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d']]);
        $from = (string) ($input['from'] ?? substr($today, 0, 8).'01'); // spec §11.2: current month
        $to = (string) ($input['to'] ?? $today);

        return Inertia::render('accounts/show', [
            'account' => ['id' => $moneyAccount->id, 'name' => $moneyAccount->name, 'type' => $moneyAccount->type->value],
            'filters' => ['from' => $from, 'to' => $to],
            'statement' => AccountStatement::for($moneyAccount, $from, $to),
        ]);
    }
}
