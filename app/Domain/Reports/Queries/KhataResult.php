<?php

declare(strict_types=1);

namespace App\Domain\Reports\Queries;

use App\Domain\Parties\PartyBalance;

/** R-01 khata: the balance before `from`, the lines, and the closing balance. */
final readonly class KhataResult
{
    /**
     * @param  list<KhataRow>  $rows
     */
    public function __construct(
        public ?string $from,
        public ?string $to,
        public PartyBalance $opening,
        public array $rows,
        public PartyBalance $closing,
    ) {}
}
