<?php

declare(strict_types=1);

namespace App\Domain\Parties;

use App\Domain\Parties\Models\Party;
use App\Support\Warnings\Warnings;

/**
 * BR-PTY-03: warn when another active party has the same mobile, or the same name
 * ignoring case and spaces. The warning names that party.
 */
final class DuplicateParty
{
    public static function check(Warnings $warnings, string $name, string $mobile, ?int $exceptId = null): void
    {
        $others = Party::query()
            ->active()
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId));

        $byMobile = (clone $others)->where('mobile', $mobile)->orderBy('id')->first();

        if ($byMobile !== null) {
            self::raise($warnings, 'mobile', $byMobile);

            return;
        }

        $byName = (clone $others)
            ->whereRaw("lower(replace(name, ' ', '')) = ?", [self::normalizedName($name)])
            ->orderBy('id')
            ->first();

        if ($byName !== null) {
            self::raise($warnings, 'name', $byName);
        }
    }

    public static function normalizedName(string $name): string
    {
        return mb_strtolower(str_replace(' ', '', $name));
    }

    private static function raise(Warnings $warnings, string $field, Party $party): void
    {
        $warnings->raise(
            'duplicate_party',
            __('A party with this :field already exists: :name (:code).', ['field' => $field, 'name' => $party->name, 'code' => $party->code]),
            ['field' => $field, 'party_id' => $party->id, 'code' => $party->code],
        );
    }
}
