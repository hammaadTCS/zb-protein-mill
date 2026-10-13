<?php

declare(strict_types=1);

namespace App\Domain\Periods;

use App\Domain\Periods\Models\Period;
use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use App\Support\Tenancy\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/** One calendar month, written "2026-01" (spec §9.5 periods). */
final readonly class Month
{
    private function __construct(public int $year, public int $month) {}

    /** "2026-01"; anything else is refused on the `period` field. */
    public static function of(string $yyyymm): self
    {
        if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $yyyymm, $parts) !== 1) {
            throw ValidationException::withMessages(['period' => __('Select a month.')]);
        }

        return new self((int) $parts[1], (int) $parts[2]);
    }

    /** The month of a Y-m-d date. */
    public static function ofDate(string $ymd): self
    {
        return self::of(substr($ymd, 0, 7));
    }

    /** BR-MEC-01: the first month is the month of `system_start_date`. */
    public static function first(): ?self
    {
        $start = Settings::get(SettingKeys::SYSTEM_START_DATE);

        return is_string($start) ? self::ofDate($start) : null;
    }

    public function key(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }

    public function firstDay(): string
    {
        return $this->key().'-01';
    }

    public function lastDay(): string
    {
        return CarbonImmutable::parse($this->firstDay())->endOfMonth()->toDateString();
    }

    /** "January 2026". */
    public function name(): string
    {
        return CarbonImmutable::parse($this->firstDay())->format('F Y');
    }

    public function previous(): self
    {
        return self::ofDate(CarbonImmutable::parse($this->firstDay())->subMonth()->toDateString());
    }

    public function next(): self
    {
        return self::ofDate(CarbonImmutable::parse($this->firstDay())->addMonth()->toDateString());
    }

    public function isBefore(self $other): bool
    {
        return $this->key() < $other->key();
    }

    /** The periods row, if the month has one. */
    public function period(): ?Period
    {
        return Period::query()->where('year', $this->year)->where('month', $this->month)->first();
    }

    /** The periods row, created open when missing (a month without a row is open). */
    public function periodOrCreate(): Period
    {
        return $this->period() ?? Period::query()->create([
            'company_id' => CurrentCompany::id(),
            'year' => $this->year,
            'month' => $this->month,
            'status' => Period::OPEN,
        ]);
    }

    public function isClosed(): bool
    {
        return $this->period()?->status === Period::CLOSED;
    }
}
