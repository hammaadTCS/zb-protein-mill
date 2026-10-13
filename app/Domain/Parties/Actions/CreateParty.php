<?php

declare(strict_types=1);

namespace App\Domain\Parties\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Numbering\VoucherNumberService;
use App\Domain\Parties\DuplicateParty;
use App\Domain\Parties\Enums\PartyType;
use App\Domain\Parties\Models\Party;
use App\Domain\Parties\PartyFields;
use App\Models\User;
use App\Support\Warnings\Warnings;
use Illuminate\Support\Facades\DB;

/**
 * Adds a party (BR-PTY-01 to BR-PTY-03, BR-PTY-06). The code comes from its type's
 * sequence (S-0001, B-0001, ...) and never changes.
 */
final class CreateParty
{
    /**
     * @param  array<string, mixed>  $data  validated StorePartyRequest fields
     * @param  list<string>  $confirmedWarnings
     */
    public function handle(User $by, array $data, array $confirmedWarnings = []): Party
    {
        return DB::transaction(function () use ($by, $data, $confirmedWarnings): Party {
            $type = PartyType::from((string) $data['type']);
            $warnings = new Warnings($confirmedWarnings);

            DuplicateParty::check($warnings, (string) $data['name'], (string) $data['mobile']); // BR-PTY-03

            $party = Party::query()->create([
                ...PartyFields::forType($type, $data),
                'company_id' => $by->company_id,
                'code' => VoucherNumberService::next('party_'.$type->value),
                'needs_review' => ! $by->can('party.review'), // BR-PTY-06
                'created_by' => $by->id,
            ]);

            AuditLogger::log(AuditActions::CREATED, $party, [], PartyFields::audited($party), by: $by);
            $warnings->audit($party, $by);

            return $party;
        });
    }
}
