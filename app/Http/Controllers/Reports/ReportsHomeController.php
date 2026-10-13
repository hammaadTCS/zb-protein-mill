<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Page 32 (card T6.9): every report the user may open. */
class ReportsHomeController extends Controller
{
    /** Spec §12.2 report list: route name, title, permission, what it shows. */
    private const REPORTS = [
        ['reports.pending-balances', 'Pending balances', 'report.view', 'Who owes us, whom we owe, and advances (R-02).'],
        ['reports.daily-summary', 'Daily summary', 'report.view', 'One page for any day (R-04).'],
        ['reports.expenses', 'Expense report', 'report.view', 'Where the money went, with every note (R-05).'],
        ['accounts.index', 'Accounts', 'account.view', 'Every movement of each cash and bank account (R-06).'],
        ['reports.trial-balance', 'Trial balance', 'report.view', 'Every account with its debit or credit balance (R-07).'],
        ['reports.income-vs-spending', 'Income vs Spending', 'report.view', 'The monthly money picture (R-08).'],
        ['partners.statement', 'Partner statement', 'partner.view', 'Invested, withdrawn and net per partner (R-09).'],
        ['reports.rates', 'Rate report', 'report.view', 'Each rate over time and the weighted average (R-10).'],
        ['reports.weight-difference', 'Weight difference', 'report.view', 'Their kg against our kg (R-11).'],
        ['reports.adjustments', 'Adjustments report', 'report.view', 'Agreed deductions per party and reason (R-12).'],
    ];

    public function index(Request $request): Response
    {
        Gate::authorize('report.view');

        $user = $this->actor($request);

        return Inertia::render('reports/index', [
            'reports' => array_values(array_map(
                fn (array $report): array => ['title' => $report[1], 'description' => $report[3], 'url' => route($report[0], [], false)],
                array_filter(self::REPORTS, fn (array $report): bool => $user->can($report[2])),
            )),
        ]);
    }
}
