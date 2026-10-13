<?php

namespace App\Http\Controllers\Reports;

use App\Domain\Parties\Enums\PartyType;
use App\Domain\Reports\Queries\PendingBalances;
use App\Http\Controllers\Controller;
use App\Support\Today;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Page 33, R-02 pending balances. */
class PendingBalancesController extends Controller
{
    public function show(Request $request): Response
    {
        Gate::authorize('report.view');

        $input = $request->validate([
            'as_of' => ['nullable', 'date_format:Y-m-d'],
            'type' => ['nullable', Rule::in(['supplier', 'buyer', 'employee', 'other'])],
        ]);
        $asOf = (string) ($input['as_of'] ?? Today::date());
        $type = isset($input['type']) ? PartyType::from((string) $input['type']) : null;

        return Inertia::render('reports/pending-balances', [
            'filters' => ['as_of' => $asOf, 'type' => $type->value ?? ''],
            'sections' => PendingBalances::asOf($asOf, $type),
        ]);
    }
}
