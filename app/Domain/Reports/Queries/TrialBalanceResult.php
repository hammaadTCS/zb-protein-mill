<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

/**
 * R-07 result. BR-LED-10: `balanced` must always be true; false is a software error.
 */
final readonly class TrialBalanceResult
{
    /**
     * @param  list<TrialBalanceRow>  $rows
     */
    public function __construct(
        public string $asOf,
        public bool $withParties,
        public array $rows,
        public string $totalDebit,
        public string $totalCredit,
        public bool $balanced,
    ) {}
}
