<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

/** Spec §8.1: the side on which an account normally carries its balance. */
enum NormalSide: string
{
    case Debit = 'debit';
    case Credit = 'credit';
}
