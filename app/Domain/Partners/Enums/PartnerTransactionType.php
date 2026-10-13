<?php

declare(strict_types=1);

namespace App\Domain\Partners\Enums;

/** BR-PTN-01: a partner entry is an investment or a withdrawal (no loan type, O-23). */
enum PartnerTransactionType: string
{
    case Investment = 'investment';
    case Withdrawal = 'withdrawal';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
