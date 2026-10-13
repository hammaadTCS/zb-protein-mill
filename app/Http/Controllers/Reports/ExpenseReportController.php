<?php

namespace App\Http\Controllers\Reports;

use App\Domain\Expenses\Models\ExpenseCategory;
use App\Domain\Reports\Queries\ExpenseReport;
use App\Http\Controllers\Concerns\ListsEntries;
use App\Http\Controllers\Controller;
use App\Support\Today;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Page 34, R-05 expense report. */
class ExpenseReportController extends Controller
{
    use ListsEntries;

    public function show(Request $request): Response
    {
        Gate::authorize('report.view');

        $input = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'category_id' => ['nullable', 'integer'],
            'party_id' => ['nullable', 'integer'],
        ]);
        $today = Today::date();
        $filters = [
            'from' => (string) ($input['from'] ?? substr($today, 0, 8).'01'),
            'to' => (string) ($input['to'] ?? $today),
            'category_id' => isset($input['category_id']) ? (int) $input['category_id'] : null,
            'party_id' => isset($input['party_id']) ? (int) $input['party_id'] : null,
        ];

        return Inertia::render('reports/expenses', [
            'filters' => $filters,
            'party' => $this->partyOption($filters['party_id']),
            'categories' => ExpenseCategory::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])
                ->map(fn (ExpenseCategory $c): array => ['id' => $c->id, 'name' => $c->name])->values(),
            'report' => ExpenseReport::for($filters['from'], $filters['to'], $filters['category_id'], $filters['party_id']),
        ]);
    }
}
