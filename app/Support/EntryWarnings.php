<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use App\Support\Warnings\Warnings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Warnings and slip rules shared by purchases and sales (BR-PUR-05 to BR-PUR-08,
 * spec §10.5). `$model` is the entry class (Purchase or Sale).
 */
final class EntryWarnings
{
    /**
     * BR-PUR-05: the latest active fixed rate for this party and item, with its date.
     *
     * @param  class-string<Model>  $model
     * @return array{rate: string, date: string}|null
     */
    public static function lastRate(string $model, int $partyId, int $itemId): ?array
    {
        $row = $model::query()
            ->where('party_id', $partyId)
            ->where('item_id', $itemId)
            ->where('status', EntryStatus::Active->value)
            ->where('rate_status', 'fixed')
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->first(['rate', 'entry_date']);

        return $row === null ? null : ['rate' => (string) $row->getAttribute('rate'), 'date' => (string) $row->getAttribute('entry_date')];
    }

    /**
     * BR-PUR-06: the weighted average rate (Σ amount ÷ Σ kg, spec §12.4) of the last
     * `unusual_rate_lookback` active fixed entries of this item, all parties, by entry
     * date then id; null when there are none.
     *
     * @param  class-string<Model>  $model
     */
    public static function averageRate(string $model, int $itemId, ?int $exceptId = null): ?string
    {
        $lookback = Settings::get(SettingKeys::UNUSUAL_RATE_LOOKBACK);

        $rows = $model::query()
            ->where('item_id', $itemId)
            ->where('status', EntryStatus::Active->value)
            ->where('rate_status', 'fixed')
            ->when($exceptId !== null, fn (Builder $q) => $q->whereKeyNot($exceptId))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->limit(is_int($lookback) ? max(1, $lookback) : 5)
            ->get(['kg', 'amount']);

        $kg = Decimal::sum($rows->map(fn (Model $r): string => (string) $r->getAttribute('kg'))->all(), '0.000');

        if (! Decimal::isPositive($kg)) {
            return null;
        }

        $amount = Decimal::sum($rows->map(fn (Model $r): string => (string) $r->getAttribute('amount'))->all());

        return Decimal::divide($amount, $kg, 4);
    }

    /**
     * BR-PUR-06: warn when the rate differs from the recent average by more than
     * `unusual_rate_percent`.
     *
     * @param  class-string<Model>  $model
     */
    public static function unusualRate(Warnings $warnings, string $model, int $itemId, string $rate, ?int $exceptId = null): void
    {
        $average = self::averageRate($model, $itemId, $exceptId);
        $limit = Settings::get(SettingKeys::UNUSUAL_RATE_PERCENT);

        if ($average === null || ! is_string($limit) || ! Decimal::isPositive($average)) {
            return;
        }

        $difference = Decimal::sub($rate, $average);
        $percent = Decimal::divide(Decimal::multiply(Decimal::abs($difference), '100', 6), $average, 4);

        if (Decimal::compare($percent, $limit) <= 0) {
            return;
        }

        $warnings->raise('unusual_rate', __('This rate is :percent% :direction than the recent average of :average. Check it before saving.', [
            'percent' => Decimal::divide($percent, '1', 0),
            'direction' => Decimal::isPositive($difference) ? 'higher' : 'lower',
            'average' => NumberFormat::rate($average),
        ]), ['rate' => $rate, 'average' => $average, 'percent' => $percent]);
    }

    /**
     * BR-PUR-08: warn when another active entry has the same party, item, kg and date.
     *
     * @param  class-string<Model>  $model
     * @param  string  $label  "purchase" or "sale"
     */
    public static function duplicate(Warnings $warnings, string $model, string $label, int $partyId, int $itemId, string $kg, string $date, ?int $exceptId = null): void
    {
        $voucher = $model::query()
            ->where('party_id', $partyId)
            ->where('item_id', $itemId)
            ->where('kg', $kg)
            ->where('entry_date', $date)
            ->where('status', EntryStatus::Active->value)
            ->when($exceptId !== null, fn (Builder $q) => $q->whereKeyNot($exceptId))
            ->orderBy('id')
            ->value('voucher_no');

        if ($voucher !== null) {
            $warnings->raise('duplicate_entry', __('A :type with the same party, item, kg and date already exists (:voucher).', [
                'type' => $label,
                'voucher' => $voucher,
            ]), ['voucher' => $voucher]);
        }
    }

    /**
     * BR-PUR-07: slip number and photo follow the settings; "Our weight" is refused
     * when the plant has no scale.
     */
    public static function slipRules(?string $slipNo, bool $hasPhoto, ?string $ourWeightKg, bool $checkPhoto): void
    {
        $errors = [];

        if (Settings::get(SettingKeys::SLIP_NO_REQUIRED) === true && ($slipNo === null || trim($slipNo) === '')) {
            $errors['slip_no'] = __('Weight slip number is required.');
        }

        if ($checkPhoto && Settings::get(SettingKeys::SLIP_PHOTO_REQUIRED) === true && ! $hasPhoto) {
            $errors['slip_photo'] = __('Slip photo is required.');
        }

        if ($ourWeightKg !== null && Settings::get(SettingKeys::PLANT_HAS_SCALE) !== true) {
            $errors['our_weight_kg'] = __('Our weight is not used: the plant has no scale.');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
