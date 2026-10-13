<?php

namespace App\Http\Controllers;

use App\Domain\Purchases\Models\Purchase;
use App\Domain\Sales\Models\Sale;
use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use App\Support\Today;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page 26: purchases and sales waiting for a rate, oldest first, with a type filter.
 * Red when older than `rate_pending_age_days` (BR-CHK-05).
 */
class RatePendingController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->actor($request);
        abort_unless($user->can('purchase.view') || $user->can('sale.view'), 403);

        $type = (string) ($request->validate(['type' => ['nullable', Rule::in(['purchase', 'sale'])]])['type'] ?? '');
        $today = CarbonImmutable::parse(Today::date());
        $limit = Settings::get(SettingKeys::RATE_PENDING_AGE_DAYS);
        $limit = is_int($limit) ? $limit : null;

        $rows = [];

        foreach (['purchase' => Purchase::class, 'sale' => Sale::class] as $kind => $model) {
            if (($type !== '' && $type !== $kind) || ! $user->can("{$kind}.view")) {
                continue;
            }

            $entries = $model::query()->with(['party', 'item'])
                ->where('status', 'active')
                ->where('rate_status', 'pending')
                ->get();

            foreach ($entries as $entry) {
                $rows[] = $this->row($kind, $entry, $today, $limit, $user->can("{$kind}.update"));
            }
        }

        usort($rows, fn (array $a, array $b): int => [$a['entry_date'], $a['voucher_no']] <=> [$b['entry_date'], $b['voucher_no']]);

        return Inertia::render('rate-pending/index', [
            'today' => $today->toDateString(),
            'age_limit' => $limit,
            'type' => $type,
            'rows' => $rows,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $kind, Purchase|Sale $entry, CarbonImmutable $today, ?int $limit, bool $canAddRate): array
    {
        /** @var Model $party */
        $party = $entry->party;
        $age = (int) CarbonImmutable::parse($entry->entry_date)->diffInDays($today);

        return [
            'type' => $kind,
            'id' => $entry->id,
            'voucher_no' => $entry->voucher_no,
            'entry_date' => $entry->entry_date,
            'party' => (string) $party->getAttribute('name'),
            'item' => $entry->item->name,
            'kg' => $entry->kg,
            'age_days' => $age,
            'aged' => $limit !== null && $age > $limit, // BR-CHK-05
            'can_add_rate' => $canAddRate,
        ];
    }
}
