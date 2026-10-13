<?php

declare(strict_types=1);

namespace App\Domain\Parties;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Parties\Enums\PartyStatus;
use App\Domain\Parties\Models\Party;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Makes a party active or inactive, audited (BR-PTY-05). */
final class SetPartyStatus
{
    public static function to(PartyStatus $status, User $by, Party $party): Party
    {
        return DB::transaction(function () use ($status, $by, $party): Party {
            $party = Party::query()->lockForUpdate()->findOrFail($party->id);

            if ($party->status === $status) {
                return $party;
            }

            $old = $party->status->value;
            $party->update(['status' => $status, 'updated_by' => $by->id]);

            AuditLogger::log(AuditActions::UPDATED, $party, ['status' => $old], ['status' => $status->value], by: $by);

            return $party;
        });
    }
}
