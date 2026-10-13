<?php

namespace App\Http\Controllers\Reports;

use App\Domain\Reports\Queries\DailySummary;
use App\Http\Controllers\Controller;
use App\Support\Today;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Page 29, Daily summary (R-04). */
class DailySummaryController extends Controller
{
    public function show(Request $request): Response
    {
        Gate::authorize('report.view');

        $date = (string) ($request->validate(['date' => ['nullable', 'date_format:Y-m-d']])['date'] ?? Today::date());

        return Inertia::render('reports/daily-summary', [
            'summary' => DailySummary::for($date),
        ]);
    }
}
