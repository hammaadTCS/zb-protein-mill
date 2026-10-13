<?php

declare(strict_types=1);

namespace App\Domain\Parties;

use App\Domain\Parties\Enums\PartyType;
use App\Domain\Parties\Models\Party;

/** Shared field handling for CreateParty and UpdateParty. */
final class PartyFields
{
    /**
     * The form fields to save for this type; fields of other types are always null
     * (BR-PTY-10).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function forType(PartyType $type, array $data): array
    {
        $fields = [];

        foreach (Party::FORM_FIELDS as $field) {
            $value = $data[$field] ?? null;
            $fields[$field] = $value === '' ? null : $value;
        }

        $fields['type'] = $type;

        if ($type !== PartyType::Buyer) {
            foreach (Party::BUYER_FIELDS as $field) {
                $fields[$field] = null;
            }
        }

        if ($type !== PartyType::Partner) {
            foreach (Party::PARTNER_FIELDS as $field) {
                $fields[$field] = null;
            }
        }

        return $fields;
    }

    /**
     * The values written to the audit log.
     *
     * @return array<string, mixed>
     */
    public static function audited(Party $party): array
    {
        $values = ['code' => $party->code];

        foreach (Party::FORM_FIELDS as $field) {
            $value = $party->getAttribute($field);
            $values[$field] = $value instanceof \BackedEnum ? $value->value : $value;
        }

        return $values;
    }
}
