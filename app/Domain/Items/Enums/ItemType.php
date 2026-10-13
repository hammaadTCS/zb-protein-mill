<?php

declare(strict_types=1);

namespace App\Domain\Items\Enums;

/** Spec §9.2 items: what the mill buys (raw material, fuel) or sells (product). */
enum ItemType: string
{
    case RawMaterial = 'raw_material';
    case Fuel = 'fuel';
    case Product = 'product';

    public function label(): string
    {
        return match ($this) {
            self::RawMaterial => 'Raw material',
            self::Fuel => 'Fuel',
            self::Product => 'Product',
        };
    }

    /** Raw material and fuel are bought (BR-PUR-01); products are sold (BR-SAL-01). */
    public function isBought(): bool
    {
        return $this !== self::Product;
    }
}
