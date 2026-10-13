<?php

namespace App\Http\Controllers\Reports;

use App\Domain\Insights\BusinessInsights;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Page 40, R-13 Business insights (D-36): rough indicators only. */
class InsightsController extends Controller
{
    public function show(Request $request): Response
    {
        Gate::authorize('insights.view');

        $input = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $period = BusinessInsights::defaultPeriod();

        return Inertia::render('reports/insights', [
            'insights' => BusinessInsights::for((string) ($input['from'] ?? $period['from']), (string) ($input['to'] ?? $period['to'])),
        ]);
    }
}
