<?php

declare(strict_types=1);

namespace App\Domain\OpeningBalances\Enums;

use App\Domain\Parties\Enums\PartyType;

/**
 * Card T2.6: money account → balance; supplier/other → we_owe or they_owe (advance);
 * buyer/employee → they_owe or we_owe (buyer we_owe = advance); partner → capital.
 */
enum OpeningSide: string
{
    case Balance = 'balance';
    case WeOwe = 'we_owe';
    case TheyOwe = 'they_owe';
    case Capital = 'capital';

    /**
     * The sides a party of this type may have.
     *
     * @return list<self>
     */
    public static function forParty(PartyType $type): array
    {
        return $type === PartyType::Partner ? [self::Capital] : [self::WeOwe, self::TheyOwe];
    }
}
