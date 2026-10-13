<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Providers;

use App\Domain\Alerts\AlertProvider;
use App\Domain\Checks\Models\CashCount;
use App\Domain\MoneyAccounts\Enums\MoneyAccountType;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use App\Models\User;
use App\Support\Dates;
use App\Support\Today;
use Carbon\CarbonImmutable;

/**
 * BR-CHK-04: "No cash count for (date)" when yesterday has no count and no dismissal
 * for an active cash account.
 */
final class MissingCashCount implements AlertProvider
{
    public static function yesterday(): string
    {
        return CarbonImmutable::parse(Today::date())->subDay()->toDateString();
    }

    /**
     * Active cash accounts with no count and no dismissal for yesterday.
     *
     * @return list<MoneyAccount>
     */
    public static function missing(): array
    {
        $yesterday = self::yesterday();
        $start = Settings::get(SettingKeys::SYSTEM_START_DATE);

        if (is_string($start) && $yesterday < $start) {
            return [];
        }

        $counted = CashCount::query()->where('count_date', $yesterday)->pluck('money_account_id')->all();

        return array_values(MoneyAccount::query()->active()
            ->where('type', MoneyAccountType::Cash->value)
            ->whereNotIn('id', $counted)
            ->orderBy('id')
            ->get()
            ->all());
    }

    public function key(): string
    {
        return 'missing_cash_count';
    }

    public function count(User $user): int
    {
        return $user->can('cashcount.create') ? count(self::missing()) : 0;
    }

    public function route(): string
    {
        return route('cash-counts.index', [], false);
    }

    public function label(): string
    {
        return __('No cash count for :date', ['date' => Dates::display(self::yesterday())]);
    }

    public function severity(): string
    {
        return 'warning';
    }
}
