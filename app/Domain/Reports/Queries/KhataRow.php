<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

use App\Domain\Parties\PartyBalance;

/**
 * One khata line (R-01): what changed and the balance after it, in words.
 * Increase = the party's balance grew in its usual direction (we owe more to a
 * supplier, a buyer owes us more); decrease = the other way. A rate-pending entry
 * has kg and no amount, and does not change the balance.
 */
final readonly class KhataRow
{
    public function __construct(
        public string $date,
        public ?string $voucher,
        public string $description,
        public ?string $increase,
        public ?string $decrease,
        public PartyBalance $balance,
        public ?string $route = null,
        public ?string $pendingKg = null,
    ) {}
}
