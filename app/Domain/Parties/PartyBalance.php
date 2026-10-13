<?php

declare(strict_types=1);

namespace App\Domain\Parties;

/**
 * A party's balance in words (spec §8.2, glossary §2), never with a minus sign:
 * "We owe" Rs 52,000.00 / "Advance" / "Owes us" / "Settled".
 * Partners also carry invested, withdrawn and net (R-09).
 */
final readonly class PartyBalance
{
    /**
     * @param  string  $amount  absolute amount, 2 decimals
     * @param  string  $side  debit, credit or zero
     * @param  string  $pendingKg  rate-pending kg, 3 decimals
     */
    public function __construct(
        public string $words,
        public string $amount,
        public string $side,
        public string $pendingKg = '0.000',
        public ?string $invested = null,
        public ?string $withdrawn = null,
        public ?string $net = null,
    ) {}

    /**
     * @return array{words: string, amount: string, side: string, pending_kg: string}
     */
    public function toArray(): array
    {
        return ['words' => $this->words, 'amount' => $this->amount, 'side' => $this->side, 'pending_kg' => $this->pendingKg];
    }
}
