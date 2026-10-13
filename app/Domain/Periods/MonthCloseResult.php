<?php

declare(strict_types=1);

namespace App\Domain\Periods;

/** The outcome of a close attempt: closed or not, with every check (card T7.1). */
final readonly class MonthCloseResult
{
    /**
     * @param  list<MonthEndCheck>  $checks
     */
    public function __construct(
        public Month $month,
        public bool $closed,
        public array $checks,
    ) {}

    /**
     * @return list<MonthEndCheck>
     */
    public function failing(): array
    {
        return array_values(array_filter($this->checks, fn (MonthEndCheck $check): bool => ! $check->passed));
    }
}
