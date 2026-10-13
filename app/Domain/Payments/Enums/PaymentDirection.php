<?php

declare(strict_types=1);

namespace App\Domain\Payments\Enums;

/** BR-PAY-01: money paid out to a party, or received from one (glossary: Payment / Receipt). */
enum PaymentDirection: string
{
    case Paid = 'paid';
    case Received = 'received';

    /** conventions §8: PAY for paid, RCV for received. */
    public function docType(): string
    {
        return $this === self::Paid ? 'payment_paid' : 'payment_received';
    }
}
