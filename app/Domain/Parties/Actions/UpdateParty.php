<?php

declare(strict_types=1);

namespace App\Domain\Parties\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Parties\DuplicateParty;
use App\Domain\Parties\Enums\PartyType;
use App\Domain\Parties\Models\Party;
use App\Domain\Parties\PartyFields;
use App\Models\User;
use App\Support\Warnings\Warnings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Edits a party. The code never changes; the type is locked after the first entry
 * (BR-PTY-04). Bank changes by a user without party.review are flagged and logged
 * with old and new values (BR-PTY-07).
 */
final class UpdateParty
{
    /**
     * @param  array<string, mixed>  $data  validated UpdatePartyRequest fields
     * @param  list<string>  $confirmedWarnings
     */
    public function handle(User $by, Party $party, array $data, array $confirmedWarnings = []): Party
    {
        return DB::transaction(function () use ($by, $party, $data, $confirmedWarnings): Party {
            $party = Party::query()->lockForUpdate()->findOrFail($party->id);
            $type = PartyType::from((string) $data['type']);
            $warnings = new Warnings($confirmedWarnings);

            if ($type !== $party->type && $party->hasEntries()) {
                throw ValidationException::withMessages(['type' => __('Type cannot change after the first entry.')]);
            }

            $old = PartyFields::audited($party);
            $party->fill(PartyFields::forType($type, $data));

            if (! $party->isDirty()) {
                return $party;
            }

            if ($party->isDirty(['name', 'mobile'])) {
                DuplicateParty::check($warnings, $party->name, $party->mobile, $party->id); // BR-PTY-03
            }

            $bankChanged = $party->isDirty(Party::BANK_FIELDS);

            if ($bankChanged && ! $by->can('party.review')) {
                $party->bank_change_needs_review = true;
            }

            $party->updated_by = $by->id;
            $party->save();

            $new = PartyFields::audited($party);
            $changed = array_flip(self::changedKeys($old, $new));

            AuditLogger::log(AuditActions::UPDATED, $party, array_intersect_key($old, $changed), array_intersect_key($new, $changed), by: $by);

            if ($bankChanged && ! $by->can('party.review')) {
                AuditLogger::log(
                    AuditActions::BANK_DETAILS_CHANGED,
                    $party,
                    array_intersect_key($old, array_flip(Party::BANK_FIELDS)),
                    array_intersect_key($new, array_flip(Party::BANK_FIELDS)),
                    by: $by,
                );
            }

            $warnings->audit($party, $by);

            return $party;
        });
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return list<string>
     */
    private static function changedKeys(array $old, array $new): array
    {
        $changed = [];

        foreach ($new as $key => $value) {
            if (self::text($old[$key] ?? null) !== self::text($value)) {
                $changed[] = $key;
            }
        }

        return $changed;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
