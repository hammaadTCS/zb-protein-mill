<?php

declare(strict_types=1);

namespace App\Domain\Parties\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Ledger\LedgerBalance;
use App\Domain\Ledger\PostingRules;
use App\Domain\Ledger\PostingService;
use App\Domain\Parties\Enums\PartyStatus;
use App\Domain\Parties\Enums\PartyType;
use App\Domain\Parties\Models\Party;
use App\Domain\Payments\Enums\ApprovalStatus;
use App\Domain\Payments\Models\Payment;
use App\Domain\Purchases\Enums\RateStatus;
use App\Domain\Purchases\Models\Purchase;
use App\Domain\Sales\Models\Sale;
use App\Models\User;
use App\Support\Decimal;
use App\Support\EntryStatus;
use App\Support\Today;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * D-06, BR-PTY-08: the Owner merges a duplicate profile (source) into the real one
 * (target) of the same type. History is not rewritten: the source keeps its entries
 * and journal lines; one journal moves its balance to the target, and the source
 * becomes inactive, "merged into" the target.
 */
final class MergeParties
{
    public function handle(User $owner, Party $source, Party $target): Party
    {
        if ($source->id === $target->id) {
            throw ValidationException::withMessages(['target_id' => __('Pick two different parties.')]);
        }

        return DB::transaction(function () use ($owner, $source, $target): Party {
            // Both rows locked, lower id first, so two merges cannot cross.
            $locked = Party::query()->whereIn('id', [$source->id, $target->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $source = $locked->get($source->id) ?? throw ValidationException::withMessages(['source_id' => __('Select a party.')]);
            $target = $locked->get($target->id) ?? throw ValidationException::withMessages(['target_id' => __('Select a party.')]);

            $this->assertMergeable($source, $target);

            $nets = [];

            foreach (self::codes($source) as $code) {
                $nets[$code] = LedgerBalance::byCode($code, null, $source->id);
            }

            $journalId = null;

            if (array_filter($nets, fn (string $net): bool => ! Decimal::isZero($net)) !== []) {
                $journalId = PostingService::post(PostingRules::partyMerge($source, $target, $nets, Today::date()), $owner)->id;
            }

            $source->update(['status' => PartyStatus::Inactive, 'merged_into_id' => $target->id, 'updated_by' => $owner->id]);

            AuditLogger::log(AuditActions::MERGED, $source, ['status' => PartyStatus::Active->value, 'merged_into_id' => null], [
                'status' => PartyStatus::Inactive->value,
                'merged_into_id' => $target->id,
                'journal_entry_id' => $journalId,
            ], by: $owner);
            AuditLogger::log(AuditActions::MERGED, $target, [], [
                'merged_from_id' => $source->id,
                'journal_entry_id' => $journalId,
            ], by: $owner);

            return $source;
        });
    }

    private function assertMergeable(Party $source, Party $target): void
    {
        if ($source->type !== $target->type) {
            throw ValidationException::withMessages(['target_id' => __('Only parties of the same type can be merged.')]);
        }

        if ($source->status !== PartyStatus::Active || $target->status !== PartyStatus::Active) {
            throw ValidationException::withMessages(['target_id' => __('Both parties must be active.')]);
        }

        // Anything that would still post to the source later would leave it with a balance.
        $ratePending = Purchase::query()->where('party_id', $source->id)->where('status', EntryStatus::Active->value)->where('rate_status', RateStatus::Pending->value)->exists()
            || Sale::query()->where('party_id', $source->id)->where('status', EntryStatus::Active->value)->where('rate_status', RateStatus::Pending->value)->exists();
        $awaiting = Payment::query()->where('party_id', $source->id)->where('status', EntryStatus::Active->value)->where('approval_status', ApprovalStatus::Pending->value)->exists();

        if ($ratePending || $awaiting) {
            throw ValidationException::withMessages(['source_id' => __(':party has rate-pending entries or payments awaiting approval. Finish them first.', ['party' => $source->name])]);
        }
    }

    /**
     * The control accounts a party's balance sits on (spec §8.3; partners: 3000 and 3100).
     *
     * @return list<string>
     */
    public static function codes(Party $party): array
    {
        return $party->type === PartyType::Partner ? ['3000', '3100'] : [(string) $party->type->controlAccountCode()];
    }
}
