<?php

declare(strict_types=1);

namespace App\Domain\Purchases\Enums;

/** BR-PUR-03: a rate is fixed, or pending until it is decided (used by purchases and sales). */
enum RateStatus: string
{
    case Fixed = 'fixed';
    case Pending = 'pending';
}
