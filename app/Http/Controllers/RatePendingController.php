<?php

namespace App\Http\Controllers;

use App\Domain\Purchases\Models\Purchase;
use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use App\Support\Today;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page 26: entries waiting for a rate, oldest first. Red when older than
 * `rate_pending_age_days` (BR-CHK-05). Sales are added in T3.4.
 */
class RatePendingController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->actor($request);
        abort_unless($user->can('purchase.view') || $user->can('sale.view'), 403);

        $today = CarbonImmutable::parse(Today::date());
        $limit = Settings::get(SettingKeys::RATE_PENDING_AGE_DAYS);
        $limit = is_int($limit) ? $limit : null;

        $rows = $user->can('purchase.view')
            ? Purchase::query()->with(['party', 'item'])
                ->where('status', 'active')
                ->where('rate_status', 'pending')
                ->orderBy('entry_date')
                ->orderBy('id')
                ->get()
                ->map(function (Purchase $p) use ($today, $limit): array {
                    $age = (int) CarbonImmutable::parse($p->entry_date)->diffInDays($today);

                    return [
                        'type' => 'purchase',
                        'id' => $p->id,
                        'voucher_no' => $p->voucher_no,
                        'entry_date' => $p->entry_date,
                        'party' => $p->party->name,
                        'item' => $p->item->name,
                        'kg' => $p->kg,
                        'age_days' => $age,
                        'aged' => $limit !== null && $age > $limit, // BR-CHK-05
                        'can_add_rate' => true,
                    ];
                })->values()->all()
            : [];

        return Inertia::render('rate-pending/index', [
            'today' => $today->toDateString(),
            'age_limit' => $limit,
            'rows' => $rows,
        ]);
    }
}
