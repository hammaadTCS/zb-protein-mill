<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Providers;

use App\Domain\Alerts\AlertProvider;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Sales\Models\Sale;
use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use App\Models\User;
use App\Support\Today;
use Carbon\CarbonImmutable;

/** BR-CHK-05: rate-pending entries older than `rate_pending_age_days` (red). */
final class RatePendingAged implements AlertProvider
{
    public function key(): string
    {
        return 'rate_pending_aged';
    }

    public function count(User $user): int
    {
        $limit = Settings::get(SettingKeys::RATE_PENDING_AGE_DAYS);

        if (! is_int($limit)) {
            return 0;
        }

        $before = CarbonImmutable::parse(Today::date())->subDays($limit)->toDateString();
        $count = 0;

        foreach (['purchase' => Purchase::class, 'sale' => Sale::class] as $kind => $model) {
            if ($user->can("{$kind}.view")) {
                $count += $model::query()->where('status', 'active')->where('rate_status', 'pending')->where('entry_date', '<', $before)->count();
            }
        }

        return $count;
    }

    public function route(): string
    {
        return route('rate-pending.index', [], false);
    }

    public function label(): string
    {
        return __('Rate pending too long');
    }

    public function severity(): string
    {
        return 'danger';
    }
}
