<?php

declare(strict_types=1);

namespace App\Domain\OpeningBalances\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Ledger\PostingRequest;
use App\Domain\Ledger\PostingRules;
use App\Domain\Ledger\PostingService;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Domain\Numbering\VoucherNumberService;
use App\Domain\OpeningBalances\Enums\OpeningSide;
use App\Domain\OpeningBalances\Models\OpeningBalance;
use App\Domain\OpeningBalances\OpeningBalances;
use App\Domain\Parties\Enums\PartyStatus;
use App\Domain\Parties\Enums\PartyType;
use App\Domain\Parties\Models\Party;
use App\Domain\Settings\SettingKeys;
use App\Domain\Settings\Settings;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * BR-OPB-01 to BR-OPB-03: sets the opening of one money account or one party, dated
 * system_start_date. A changed opening reverses the old journal and posts the new one;
 * amount 0 removes it.
 */
final class SetOpeningBalance
{
    /**
     * @param  array{money_account_id?: int|null, party_id?: int|null, amount: string, side?: string|null}  $data
     */
    public function handle(User $by, array $data): ?OpeningBalance
    {
        return DB::transaction(function () use ($by, $data): ?OpeningBalance {
            Settings::assertComplete(); // BR-GEN-07
            OpeningBalances::assertEditable(); // BR-OPB-03

            $startDate = (string) Settings::get(SettingKeys::SYSTEM_START_DATE);
            $amount = Decimal::money($data['amount']);
            [$account, $party, $side] = $this->target($data);

            $opening = OpeningBalance::query()
                ->lockForUpdate()
                ->where($account !== null ? 'money_account_id' : 'party_id', $account->id ?? $party?->id)
                ->first();

            if ($opening === null && Decimal::isZero($amount)) {
                return null; // nothing to remove
            }

            $old = $opening === null ? [] : ['amount' => $opening->amount, 'side' => $opening->side->value];

            if ($opening !== null && $opening->amount === $amount && $opening->side === $side) {
                return $opening; // unchanged
            }

            if ($opening?->journal_entry_id !== null) {
                PostingService::reverseId($opening->journal_entry_id, $startDate, "Reversal of opening balance {$opening->voucher_no}", $by);
            }

            $opening ??= new OpeningBalance([
                'company_id' => $by->company_id,
                'voucher_no' => VoucherNumberService::next('opening_balance'),
                'money_account_id' => $account?->id,
                'party_id' => $party?->id,
                'created_by' => $by->id,
            ]);

            $opening->fill([
                'as_of_date' => $startDate,
                'amount' => $amount,
                'side' => $side,
                'journal_entry_id' => null,
                'updated_by' => $by->id,
            ]);
            $opening->save();

            if (Decimal::isPositive($amount)) {
                $journal = PostingService::post($this->posting($opening, $account, $party), $by);
                $opening->update(['journal_entry_id' => $journal->id]);
            }

            AuditLogger::log(
                $old === [] ? AuditActions::CREATED : AuditActions::UPDATED,
                $opening,
                $old,
                ['amount' => $opening->amount, 'side' => $opening->side->value],
                by: $by,
            );

            return $opening;
        });
    }

    /**
     * @param  array{money_account_id?: int|null, party_id?: int|null, amount: string, side?: string|null}  $data
     * @return array{MoneyAccount|null, Party|null, OpeningSide}
     */
    private function target(array $data): array
    {
        if (($data['money_account_id'] ?? null) !== null) {
            $account = MoneyAccount::query()->active()->find($data['money_account_id'])
                ?? throw ValidationException::withMessages(['money_account_id' => __('Select an active account.')]);

            return [$account, null, OpeningSide::Balance];
        }

        $party = Party::query()->where('status', PartyStatus::Active->value)->find($data['party_id'] ?? null)
            ?? throw ValidationException::withMessages(['party_id' => __('Select an active party.')]);

        $side = OpeningSide::tryFrom((string) ($data['side'] ?? ''));

        if ($side === null || ! in_array($side, OpeningSide::forParty($party->type), true)) {
            throw ValidationException::withMessages(['side' => __('Select a side for this party.')]);
        }

        return [null, $party, $side];
    }

    /** Spec §8.3 PR-17 to PR-22. */
    private function posting(OpeningBalance $opening, ?MoneyAccount $account, ?Party $party): PostingRequest
    {
        if ($account !== null) {
            return PostingRules::pr17OpeningMoneyAccount($opening, $account);
        }

        assert($party !== null);
        $theyOwe = $opening->side === OpeningSide::TheyOwe;

        return match ($party->type) {
            PartyType::Supplier, PartyType::Other => $theyOwe
                ? PostingRules::pr19OpeningAdvanceWithSupplierOrOther($opening, $party)
                : PostingRules::pr18OpeningWeOweSupplierOrOther($opening, $party),
            PartyType::Buyer, PartyType::Employee => $theyOwe
                ? PostingRules::pr20OpeningBuyerOrEmployeeOwesUs($opening, $party)
                : PostingRules::pr21OpeningAdvanceFromBuyerOrWeOweEmployee($opening, $party),
            PartyType::Partner => PostingRules::pr22OpeningPartnerCapital($opening, $party),
        };
    }
}
