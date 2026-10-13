<?php

declare(strict_types=1);

namespace App\Domain\Purchases;

use App\Domain\Items\Models\Item;
use App\Domain\Parties\Enums\PartyType;
use App\Domain\Parties\Models\Party;
use App\Support\EntryChecks;

/**
 * The checked fields of a purchase form, shared by CreatePurchase and UpdatePurchase
 * (BR-PUR-01 to BR-PUR-03). Each check fails on its own form field.
 */
final readonly class PurchaseInput
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
            party: EntryChecks::party(self::id($data, 'party_id'), [PartyType::Supplier], 'party_id', __('Select an active supplier.')), // BR-PUR-01
            item: EntryChecks::item(self::id($data, 'item_id'), true, 'item_id', __('Select an active raw material or fuel item.')),
            kg: EntryChecks::kg((string) $data['kg']), // BR-PUR-02
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
