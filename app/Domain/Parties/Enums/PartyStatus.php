<?php

declare(strict_types=1);

namespace App\Domain\Parties\Enums;

/** BR-PTY-05: a party is never deleted, only made inactive. */
enum PartyStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
