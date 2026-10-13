<?php

namespace App\Http\Controllers\Reports;

use App\Domain\Reports\Queries\TrialBalance;
use App\Domain\Reports\Queries\TrialBalanceRow;
use App\Http\Controllers\Controller;
use App\Support\Today;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Page 36, R-07 trial balance. Debit and Credit are allowed words on this page only. */
class TrialBalanceController extends Controller
{
    public function show(Request $request): Response
    {
        Gate::authorize('report.view');

        $input = $request->validate([
            'as_of' => ['nullable', 'date_format:Y-m-d'],
            'parties' => ['nullable', 'boolean'],
        ]);

        $result = TrialBalance::asOf($input['as_of'] ?? Today::date(), (bool) ($input['parties'] ?? false));

        return Inertia::render('reports/trial-balance', [
            'as_of' => $result->asOf,
            'show_parties' => $result->withParties,
            'rows' => array_map(fn (TrialBalanceRow $row): array => [
                'code' => $row->code,
                'name' => $row->name,
                'party' => $row->partyCode === null ? null : $row->partyCode.' – '.$row->partyName,
                'debit' => $row->balanceDebit,
                'credit' => $row->balanceCredit,
            ], $result->rows),
            'total_debit' => $result->totalDebit,
            'total_credit' => $result->totalCredit,
            'balanced' => $result->balanced,
        ]);
    }
}
