<?php

declare(strict_types=1);

namespace App\Domain\Adjustments\Enums;

/** BR-ADJ-04: why both sides agreed the deduction. */
enum AdjustmentReason: string
{
    case Quality = 'quality';
    case Weight = 'weight';
    case Freight = 'freight';
    case Other = 'other';
}
