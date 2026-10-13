<?php

declare(strict_types=1);

namespace App\Domain\Parties\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Parties\Models\Party;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * BR-PTY-06, BR-PTY-07: the Owner marks a new party, or a bank detail change made by
 * the Accountant, as reviewed; the alert then goes away.
 */
final class ReviewParty
{
    public const NEW_PARTY = 'new_party';

    public const BANK_CHANGE = 'bank_change';

    public function handle(User $owner, Party $party, string $what): Party
    {
        $flag = match ($what) {
            self::NEW_PARTY => 'needs_review',
            self::BANK_CHANGE => 'bank_change_needs_review',
            default => throw ValidationException::withMessages(['what' => __('Choose what was reviewed.')]),
        };

        return DB::transaction(function () use ($owner, $party, $what, $flag): Party {
            $party = Party::query()->lockForUpdate()->findOrFail($party->id);

            if (! $party->getAttribute($flag)) {
                return $party;
            }

            $party->forceFill([
                $flag => false,
                'reviewed_by' => $owner->id,
                'reviewed_at' => now(),
            ])->save();

            AuditLogger::log(AuditActions::REVIEWED, $party, [$flag => true], [$flag => false, 'what' => $what], by: $owner);

            return $party;
        });
    }
}
