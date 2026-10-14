<?php

namespace App\Console\Commands;

use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Domain\Parties\Models\Party;
use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use App\Models\User;
use App\Support\Dates;
use App\Support\Today;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * T8.2 (NFR-02): opens every list and report page as the local Owner, three times
 * each, and prints the slowest of the three in milliseconds (server time, including
 * every query). Local computers only.
 */
class TimePages extends Command
{
    protected $signature = 'zb:time-pages {--runs=3}';

    protected $description = 'Time every list and report page (local only)';

    public function handle(Kernel $kernel): int
    {
        if (app()->environment('production')) {
            $this->error('zb:time-pages is for local use only.');

            return self::FAILURE;
        }

        $owner = User::query()->where('email', 'test@example.com')->firstOrFail();
        Auth::login($owner);

        $start = (string) Settings::get(SettingKeys::SYSTEM_START_DATE);
        $today = Today::date();
        $year = ['from' => $start, 'to' => $today];
        // The first of each, named by public id as every route is (BR-SEC-19).
        $cash = MoneyAccount::query()->orderBy('id')->value('public_id');
        $supplier = Party::query()->where('type', 'supplier')->orderBy('id')->value('public_id');
        $buyer = Party::query()->where('type', 'buyer')->orderBy('id')->value('public_id');

        $pages = [
            'Dashboard' => route('dashboard', [], false),
            'Purchases' => route('purchases.index', [], false),
            'Purchases, 12 months' => route('purchases.index', $year, false),
            'Sales' => route('sales.index', [], false),
            'Payments' => route('payments.index', [], false),
            'Expenses' => route('expenses.index', [], false),
            'Transfers' => route('transfers.index', [], false),
            'Parties' => route('parties.index', [], false),
            'Supplier profile and khata' => route('parties.show', $supplier, false),
            'Buyer profile and khata' => route('parties.show', $buyer, false),
            'Partners' => route('partners.index', [], false),
            'Partner statement' => route('partners.statement', $year, false),
            'Accounts' => route('accounts.index', [], false),
            'Account statement' => route('accounts.show', $cash, false),
            'Account statement, 12 months' => route('accounts.show', ['moneyAccount' => $cash, ...$year], false),
            'Rate pending' => route('rate-pending.index', [], false),
            'Approvals' => route('approvals.index', [], false),
            'Cash check' => route('cash-counts.index', [], false),
            'Daily summary' => route('reports.daily-summary', [], false),
            'Pending balances' => route('reports.pending-balances', [], false),
            'Expense report, 12 months' => route('reports.expenses', $year, false),
            'Trial balance' => route('reports.trial-balance', [], false),
            'Trial balance with parties' => route('reports.trial-balance', ['with_parties' => 1], false),
            'Income vs Spending, 12 months' => route('reports.income-vs-spending', $year, false),
            'Rate report' => route('reports.rates', [], false),
            'Rate report, 12 months' => route('reports.rates', $year, false),
            'Weight difference' => route('reports.weight-difference', [], false),
            'Weight difference, 12 months' => route('reports.weight-difference', $year, false),
            'Adjustments report, 12 months' => route('reports.adjustments', $year, false),
            'Business insights' => route('insights.show', [], false),
            'Business insights, 12 months' => route('insights.show', $year, false),
            'Audit log' => route('audit-log.index', [], false),
            'Month-end close' => route('month-end.show', [], false),
            'Months' => route('months.index', [], false),
            'Statement PDF, this month' => route('parties.statement', ['party' => $supplier, 'from' => substr($today, 0, 8).'01', 'to' => $today], false),
            // BR-RPT-11: the longest period one statement PDF may cover.
            'Statement PDF, 3 months' => route('parties.statement', ['party' => $supplier, 'from' => Dates::threeMonthsBefore($today), 'to' => $today], false),
        ];

        // One plain request first: the Inertia asset version is known after it.
        $warmUp = Request::create(route('dashboard', [], false), 'GET');
        $kernel->terminate($warmUp, $kernel->handle($warmUp));
        $rows = [];

        foreach ($pages as $name => $uri) {
            $times = [];
            $status = 0;

            for ($run = 0; $run < max(1, (int) $this->option('runs')); $run++) {
                $request = Request::create($uri, 'GET');
                $request->headers->set('X-Inertia', 'true');
                $request->headers->set('X-Inertia-Version', (string) Inertia::getVersion());
                $began = hrtime(true);
                $response = $kernel->handle($request);
                $times[] = intdiv(hrtime(true) - $began, 1_000_000);
                $status = $response->getStatusCode();
                $kernel->terminate($request, $response);
            }

            $rows[] = [$name, $status, max($times)];
        }

        $this->table(['Page', 'Status', 'Slowest ms'], $rows);

        return collect($rows)->every(fn (array $row): bool => $row[1] === 200 && $row[2] < 3000) ? self::SUCCESS : self::FAILURE;
    }
}
