<?php

declare(strict_types=1);

namespace App\Domain\Transfers\Actions;

use App\Domain\Audit\AuditActions;
use App\Domain\Audit\AuditLogger;
use App\Domain\Checks\CashGuard;
use App\Domain\Ledger\Journals;
use App\Domain\Ledger\PostingRules;
use App\Domain\Ledger\PostingService;
use App\Domain\Periods\Periods;
use App\Domain\Settings\Settings;
use App\Domain\Transfers\Models\Transfer;
use App\Domain\Transfers\TransferInput;
use App\Models\User;
use App\Support\ConcurrentEdit;
use App\Support\EntryStatus;
use App\Support\Warnings\Warnings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Edits a transfer by reversal and repost (spec §6); refused in a closed month. */
final class UpdateTransfer
{
    /**
     * @param  array<string, mixed>  $data  validated UpdateTransferRequest fields
     * @param  list<string>  $confirmedWarnings
     */
    public function handle(User $by, Transfer $transfer, array $data, array $confirmedWarnings = []): Transfer
    {
        return DB::transaction(function () use ($by, $transfer, $data, $confirmedWarnings): Transfer {
            Settings::assertComplete(); // BR-GEN-07
            $transfer = Transfer::query()->lockForUpdate()->findOrFail($transfer->id);
            ConcurrentEdit::check($transfer->updated_at, isset($data['version']) ? (string) $data['version'] : null); // BR-GEN-09

            if ($transfer->status !== EntryStatus::Active) {
                throw ValidationException::withMessages(['date' => __('A cancelled entry cannot be edited.')]);
            }

            Periods::assertDateOpen($transfer->entry_date);
            $oldPostingDate = $transfer->journal_entry_id === null ? null : Journals::postingDate($transfer->journal_entry_id);

            if ($oldPostingDate !== null) {
                Periods::assertDateOpen($oldPostingDate);
            }

            Periods::assertValidEntryDate((string) $data['date']); // BR-GEN-02
            $input = TransferInput::from($data);
            $warnings = new Warnings($confirmedWarnings);
            CashGuard::check($warnings, $input->from, $input->amount, $transfer); // BR-GEN-10

            $old = CreateTransfer::audited($transfer);
            $transfer->fill([...CreateTransfer::fields($input), 'updated_by' => $by->id]);

            if (! $transfer->isDirty()) {
                return $transfer;
            }

            $transfer->save();
            $transfer->unsetRelation('fromAccount')->unsetRelation('toAccount');

            if ($oldPostingDate !== null && $transfer->journal_entry_id !== null) {
                PostingService::reverseId($transfer->journal_entry_id, $oldPostingDate, "Edit of {$transfer->voucher_no}", $by); // PR-25
            }

            $journal = PostingService::post(PostingRules::pr16Transfer($transfer), $by);
            $transfer->update(['journal_entry_id' => $journal->id]);

            $new = CreateTransfer::audited($transfer);
            $changed = array_flip(array_keys(array_filter($new, fn ($value, $key) => ($old[$key] ?? null) !== $value, ARRAY_FILTER_USE_BOTH)));
            AuditLogger::log(AuditActions::UPDATED, $transfer, array_intersect_key($old, $changed), array_intersect_key($new, $changed), by: $by);
            $warnings->audit($transfer, $by);

            return $transfer;
        });
    }
}
