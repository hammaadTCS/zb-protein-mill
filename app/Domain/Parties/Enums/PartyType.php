<?php

declare(strict_types=1);

namespace App\Domain\Parties\Enums;

/** BR-PTY-02: the five party types (glossary §1). */
enum PartyType: string
{
    case Supplier = 'supplier';
    case Buyer = 'buyer';
    case Partner = 'partner';
    case Employee = 'employee';
    case Other = 'other';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Spec §8.3: the control account a party's lines are posted to. Partners use
     * 3000 Partner Capital and 3100 Partner Withdrawals instead.
     */
    public function controlAccountCode(): ?string
    {
        return match ($this) {
            self::Supplier => '2000',
            self::Buyer => '1200',
            self::Employee => '1300',
            self::Other => '2100',
            self::Partner => null,
        };
    }
}
