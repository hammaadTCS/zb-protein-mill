<?php

namespace App\Http\Controllers;

use App\Domain\Periods\Actions\ReopenMonth;
use App\Domain\Periods\Month;
use App\Domain\Periods\Months;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Page 31, Months list (spec §11.12): status per month and "Reopen month" (BR-MEC-08). */
class MonthController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('period.close');

        $latestClosed = Months::latestClosed()?->key();

        return Inertia::render('months/index', [
            'months' => array_map(function (Month $month) use ($latestClosed): array {
                $period = $month->period();

                return [
                    'key' => $month->key(),
                    'name' => $month->name(),
                    'status' => $period === null ? 'open' : $period->status,
                    'closed_by' => $period?->closedBy?->name,
                    'closed_at' => $period?->closed_at?->toIso8601String(),
                    'can_reopen' => $month->key() === $latestClosed,
                ];
            }, array_reverse(Months::all())),
            'can_reopen' => $request->user()?->can('period.reopen') ?? false,
        ]);
    }

    /** BR-MEC-08: only the latest closed month, with a reason. */
    public function reopen(Request $request, string $period, ReopenMonth $action): RedirectResponse
    {
        Gate::authorize('period.reopen');

        $input = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $action->handle($this->actor($request), $period, (string) $input['reason']);
        Flash::success(__(':month is open again.', ['month' => Month::of($period)->name()]));

        return to_route('months.index');
    }
}
