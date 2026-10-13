<?php

namespace App\Http\Controllers\Reports;

use App\Domain\Reports\Queries\IncomeVsSpending;
use App\Http\Controllers\Controller;
use App\Support\Today;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Page 35, R-08 Income vs Spending (BR-RPT-08: never "profit"). */
class IncomeVsSpendingController extends Controller
{
    public function show(Request $request): Response
    {
        Gate::authorize('report.view');

        $input = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d']]);
        $today = Today::date();

        return Inertia::render('reports/income-vs-spending', [
            'banner' => __(IncomeVsSpending::BANNER),
            'report' => IncomeVsSpending::for((string) ($input['from'] ?? substr($today, 0, 8).'01'), (string) ($input['to'] ?? $today)),
        ]);
    }
}
