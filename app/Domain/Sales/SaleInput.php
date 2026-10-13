<?php

declare(strict_types=1);

namespace App\Domain\Sales;

use App\Domain\Items\Models\Item;
use App\Domain\Parties\Enums\PartyType;
use App\Domain\Parties\Models\Party;
use App\Support\EntryChecks;

/**
 * The checked fields of a sale form, shared by CreateSale and UpdateSale
 * (BR-SAL-01, BR-SAL-05 and BR-PUR-02/03 applied to sales, spec §10.5). Each check
 * fails on its own form field.
 */
final readonly class SaleInput
{
    public function __construct(
        public string $date,
        public Party $party,
        public Item $item,
        public string $kg,
        public ?string $rate,
        public ?string $slipNo,
        public ?string $ourWeightKg,
        public ?string $note,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        return new self(
            date: (string) $data['date'],
            party: EntryChecks::party(self::id($data, 'party_id'), [PartyType::Buyer], 'party_id', __('Select an active buyer.')), // BR-SAL-01
            item: EntryChecks::item(self::id($data, 'item_id'), false, 'item_id', __('Select an active product.')),
            kg: EntryChecks::kg((string) $data['kg']), // BR-PUR-02, BR-SAL-05: the buyer's weight
            rate: self::filled($data, 'rate') ? EntryChecks::rate((string) $data['rate']) : null, // BR-PUR-03
            slipNo: self::filled($data, 'slip_no') ? (string) $data['slip_no'] : null,
            ourWeightKg: self::filled($data, 'our_weight_kg') ? EntryChecks::kg((string) $data['our_weight_kg'], 'our_weight_kg') : null,
            note: self::filled($data, 'note') ? (string) $data['note'] : null,
        );
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function filled(array $data, string $key): bool
    {
        return isset($data[$key]) && $data[$key] !== '';
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function id(array $data, string $key): ?int
    {
        return self::filled($data, $key) && is_numeric($data[$key]) ? (int) $data[$key] : null;
    }
}
