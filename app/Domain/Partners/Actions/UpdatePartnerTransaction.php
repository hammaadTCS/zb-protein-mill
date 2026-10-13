<?php

declare(strict_types=1);

namespace App\Domain\Partners\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Checks\CashGuard;
use App\Domain\Ledger\Journals;
use App\Domain\Ledger\PostingService;
use App\Domain\Partners\Enums\PartnerTransactionType;
use App\Domain\Partners\Models\PartnerTransaction;
use App\Domain\Partners\PartnerTransactionInput;
use App\Domain\Periods\Periods;
use App\Domain\Settings\Settings;
use App\Models\User;
use App\Support\ConcurrentEdit;
use App\Support\EntryStatus;
use App\Support\Warnings\Warnings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Edits a partner entry by reversal and repost (spec §6); refused in a closed month. */
final class UpdatePartnerTransaction
{
    /**
     * @param  array<string, mixed>  $data  validated UpdatePartnerTransactionRequest fields
     * @param  list<string>  $confirmedWarnings
     */
    public function handle(User $by, PartnerTransaction $entry, array $data, array $confirmedWarnings = []): PartnerTransaction
    {
        return DB::transaction(function () use ($by, $entry, $data, $confirmedWarnings): PartnerTransaction {
            Settings::assertComplete(); // BR-GEN-07
            $entry = PartnerTransaction::query()->lockForUpdate()->findOrFail($entry->id);
            ConcurrentEdit::check($entry->updated_at, isset($data['version']) ? (string) $data['version'] : null); // BR-GEN-09

            if ($entry->status !== EntryStatus::Active) {
                throw ValidationException::withMessages(['date' => __('A cancelled entry cannot be edited.')]);
            }

            Periods::assertDateOpen($entry->entry_date);
            $oldPostingDate = $entry->journal_entry_id === null ? null : Journals::postingDate($entry->journal_entry_id);

            if ($oldPostingDate !== null) {
                Periods::assertDateOpen($oldPostingDate);
            }

            Periods::assertValidEntryDate((string) $data['date']); // BR-GEN-02
            $input = PartnerTransactionInput::from($data);
            $warnings = new Warnings($confirmedWarnings);

            if ($input->type === PartnerTransactionType::Withdrawal) {
                CashGuard::check($warnings, $input->account, $input->amount, $entry); // BR-GEN-10
            }

            $old = CreatePartnerTransaction::audited($entry);
            $entry->fill([...CreatePartnerTransaction::fields($input), 'updated_by' => $by->id]);

            if (! $entry->isDirty()) {
                return $entry;
            }

            $entry->save();
            $entry->unsetRelation('party')->unsetRelation('moneyAccount');

            if ($oldPostingDate !== null && $entry->journal_entry_id !== null) {
                PostingService::reverseId($entry->journal_entry_id, $oldPostingDate, "Edit of {$entry->voucher_no}", $by); // PR-25
            }

            $journal = PostingService::post(CreatePartnerTransaction::posting($entry), $by);
            $entry->update(['journal_entry_id' => $journal->id]);

            $new = CreatePartnerTransaction::audited($entry);
            $changed = array_flip(array_keys(array_filter($new, fn ($value, $key) => ($old[$key] ?? null) !== $value, ARRAY_FILTER_USE_BOTH)));
            AuditLogger::log(AuditActions::UPDATED, $entry, array_intersect_key($old, $changed), array_intersect_key($new, $changed), by: $by);
            $warnings->audit($entry, $by);

            return $entry;
        });
    }
}
