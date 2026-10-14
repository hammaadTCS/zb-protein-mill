<?php

namespace App\Http\Controllers\Reports;

use App\Domain\Adjustments\Enums\AdjustmentReason;
use App\Domain\Items\Models\Item;
use App\Domain\Parties\Models\Party;
use App\Domain\Reports\Queries\RateWeightReports;
use App\Http\Controllers\Concerns\ListsEntries;
use App\Http\Controllers\Controller;
use App\Support\Dates;
use App\Support\Today;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pages 37, 38 and 39: R-10 rates, R-11 weight difference, R-12 adjustments.
 * BR-RPT-10: over a range longer than 3 months, rates and weights show one line per
 * month (`monthly` true) instead of one per entry; totals and averages are the same.
 */
class RateWeightReportsController extends Controller
{
    use ListsEntries;

    public function rates(Request $request): Response
    {
        Gate::authorize('report.view');

        $filters = $this->filters($request, ['item_id' => ['nullable', 'string']]);
        $monthly = Dates::longerThanThreeMonths($filters['from'], $filters['to']);

        return Inertia::render('reports/rates', [
            'filters' => $this->shownFilters($filters),
            'party' => $this->partyOption($filters['party_id']),
            'items' => Item::query()->orderBy('id')->get()
                ->filter(fn (Item $item) => $item->type->isBought() === ($filters['type'] === 'purchase'))
                ->map(fn (Item $item): array => ['id' => $item->id, 'public_id' => $item->public_id, 'name' => $item->name])->values(),
            'monthly' => $monthly,
            'groups' => $monthly ? RateWeightReports::rateMonths($filters) : RateWeightReports::rates($filters),
        ]);
    }

    public function weights(Request $request): Response
    {
        Gate::authorize('report.view');

        $filters = $this->filters($request);
        $monthly = Dates::longerThanThreeMonths($filters['from'], $filters['to']);

        return Inertia::render('reports/weight-difference', [
            'filters' => $this->shownFilters($filters),
            'party' => $this->partyOption($filters['party_id']),
            'monthly' => $monthly,
            'groups' => $monthly ? RateWeightReports::weightMonths($filters) : RateWeightReports::weights($filters),
        ]);
    }

    public function adjustments(Request $request): Response
    {
        Gate::authorize('report.view');

        $filters = $this->filters($request, ['reason' => ['nullable', Rule::enum(AdjustmentReason::class)]]);

        return Inertia::render('reports/adjustments', [
            'filters' => $this->shownFilters($filters),
            'party' => $this->partyOption($filters['party_id']),
            'rows' => RateWeightReports::adjustments($filters),
        ]);
    }

    /**
     * Type (purchase by default), date range (current month by default) and party,
     * plus the report's own filters.
     *
     * @param  array<string, mixed>  $extra
     * @return array{type: string, from: string, to: string, party_id: int|null, item_id?: int|null, reason?: string|null}
     */
    private function filters(Request $request, array $extra = []): array
    {
        $input = $request->validate([
            'type' => ['nullable', Rule::in(['purchase', 'sale'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'party_id' => ['nullable', 'string'],
            ...$extra,
        ]);
        $today = Today::date();
        $filters = [
            'type' => (string) ($input['type'] ?? 'purchase'),
            'from' => (string) ($input['from'] ?? substr($today, 0, 8).'01'),
            'to' => (string) ($input['to'] ?? $today),
            'party_id' => Party::idFromPublic($input['party_id'] ?? null),
        ];

        if (isset($extra['item_id'])) {
            $filters['item_id'] = Item::idFromPublic($input['item_id'] ?? null);
        }

        if (isset($extra['reason'])) {
            $filters['reason'] = isset($input['reason']) ? (string) $input['reason'] : null;
        }

        return $filters;
    }
}
