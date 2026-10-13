<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

/**
 * One line of the trial balance (R-07). Money values are 2-decimal strings; of the
 * two balance columns at most one is above zero.
 */
final readonly class TrialBalanceRow
{
    public function __construct(
        public string $code,
        public string $name,
        public ?int $partyId,
        public ?string $partyCode,
        public string $debitTotal,
        public string $creditTotal,
        public string $balanceDebit,
        public string $balanceCredit,
        public ?string $partyName = null,
    ) {}
}
