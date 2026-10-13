<?php

declare(strict_types=1);

namespace App\Domain\Ledger;

use App\Domain\Ledger\Models\JournalEntry;
use App\Domain\Ledger\Models\JournalLine;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Numbering\VoucherNumberService;
use App\Domain\Parties\Models\Party;
use App\Domain\Periods\Periods;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * The only writer of journal entries and lines (CLAUDE.md rule 2, conventions §6).
 *
 * Every request is checked in PHP before anything is written: at least two lines,
 * positive 2-decimal amounts, active accounts of the company, the party rule
 * (BR-LED-04) and debit = credit (BR-LED-03). The database checks the balance again
 * (journal_balance_check) before this method returns.
 */
final class PostingService
{
    /** True only while this service is writing journal rows. Never set from outside. */
    private static bool $posting = false;

    public static function isPosting(): bool
    {
        return self::$posting;
    }

    /**
     * Posts one balanced journal and returns it.
     *
     * @throws LogicException outside a database transaction
     * @throws InvalidArgumentException when the request breaks a ledger rule
     */
    public static function post(PostingRequest $request, User $by): JournalEntry
    {
        self::assertInTransaction();
        Periods::assertDateOpen($request->postingDate); // BR-LED-07

        $lines = self::resolve($request->lines, $by);

        return self::write(
            $by,
            $request->postingDate,
            $request->sourceType,
            $request->sourceId,
            $request->description,
            $lines,
            reversalOfId: null,
        );
    }

    /**
     * PR-25: posts the same lines as the original with debit and credit swapped.
     * A journal can be reversed only once (BR-LED-05).
     *
     * @param  string  $postingDate  Y-m-d
     *
     * @throws LogicException outside a transaction, or when already reversed
     */
    public static function reverse(JournalEntry $original, string $postingDate, string $description, User $by): JournalEntry
    {
        self::assertInTransaction();
        Periods::assertDateOpen($postingDate); // BR-LED-07

        $alreadyReversed = JournalEntry::query()
            ->withoutGlobalScope('company')
            ->where('reversal_of_id', $original->id)
            ->exists();

        if ($alreadyReversed) {
            throw new LogicException("Journal {$original->entry_no} has already been reversed.");
        }

        $swapped = array_values(JournalLine::query()
            ->withoutGlobalScope('company')
            ->where('journal_entry_id', $original->id)
            ->orderBy('line_no')
            ->get()
            ->map(fn (JournalLine $line): PostingLine => Decimal::isPositive($line->debit)
                ? PostingLine::credit($line->ledger_account_id, $line->debit, $line->party_id, $line->item_id)
                : PostingLine::debit($line->ledger_account_id, $line->credit, $line->party_id, $line->item_id))
            ->all());

        return self::write(
            $by,
            $postingDate,
            $original->source_type,
            $original->source_id,
            $description,
            self::resolve($swapped, $by),
            reversalOfId: $original->id,
        );
    }

    /**
     * Checks every line and the balance, and finds each line's account.
     *
     * @param  list<PostingLine>  $lines
     * @return list<array{PostingLine, LedgerAccount}>
     */
    private static function resolve(array $lines, User $by): array
    {
        if (count($lines) < 2) {
            throw new InvalidArgumentException('A journal needs at least 2 lines.');
        }

        $resolved = [];
        $debit = '0.00';
        $credit = '0.00';

        foreach ($lines as $index => $line) {
            $number = $index + 1;

            if ($line->side !== PostingLine::DEBIT && $line->side !== PostingLine::CREDIT) {
                throw new InvalidArgumentException("Line {$number}: Side must be debit or credit.");
            }

            if (preg_match('/^\d{1,14}\.\d{2}$/', $line->amount) !== 1 || ! Decimal::isPositive($line->amount)) {
                throw new InvalidArgumentException("Line {$number} needs a positive amount with 2 decimals, got {$line->amount}.");
            }

            $account = self::account($line, $by);
            self::assertPartyRule($line, $account);

            if ($line->side === PostingLine::DEBIT) {
                $debit = Decimal::add($debit, $line->amount);
            } else {
                $credit = Decimal::add($credit, $line->amount);
            }

            $resolved[] = [$line, $account];
        }

        // BR-LED-03: checked here, before anything is written.
        if (Decimal::compare($debit, $credit) !== 0) {
            throw new InvalidArgumentException("Journal does not balance: debit {$debit} does not equal credit {$credit} (BR-LED-03).");
        }

        return $resolved;
    }

    private static function account(PostingLine $line, User $by): LedgerAccount
    {
        if (($line->accountCode === null) === ($line->ledgerAccountId === null)) {
            throw new InvalidArgumentException('A posting line names its account by code or by id, not both.');
        }

        $query = LedgerAccount::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $by->company_id);

        $account = $line->accountCode !== null
            ? $query->where('code', $line->accountCode)->first()
            : $query->whereKey($line->ledgerAccountId)->first();

        $label = $line->accountCode ?? "#{$line->ledgerAccountId}";

        if ($account === null) {
            throw new InvalidArgumentException("Account {$label} does not exist.");
        }

        if (! $account->is_active) {
            throw new InvalidArgumentException("Account {$account->code} is inactive.");
        }

        return $account;
    }

    /**
     * BR-LED-04: a line on a party control account (1200, 1300, 2000, 2100, 3000,
     * 3100) carries a party of the matching type; a line on any other account never does.
     */
    private static function assertPartyRule(PostingLine $line, LedgerAccount $account): void
    {
        if ($account->party_type !== null && $line->partyId === null) {
            throw new InvalidArgumentException("Account {$account->code} needs a party (BR-LED-04).");
        }

        if ($account->party_type === null && $line->partyId !== null) {
            throw new InvalidArgumentException("Account {$account->code} cannot carry a party (BR-LED-04).");
        }

        if ($account->party_type !== null) {
            $party = Party::query()
                ->withoutGlobalScope('company')
                ->where('company_id', $account->company_id)
                ->find($line->partyId);

            if ($party === null || $party->type->value !== $account->party_type) {
                throw new InvalidArgumentException("Account {$account->code} needs a {$account->party_type} party (BR-LED-04).");
            }
        }
    }

    /**
     * @param  list<array{PostingLine, LedgerAccount}>  $lines
     */
    private static function write(
        User $by,
        string $postingDate,
        string $sourceType,
        int $sourceId,
        string $description,
        array $lines,
        ?int $reversalOfId,
    ): JournalEntry {
        self::$posting = true;

        try {
            $entry = JournalEntry::query()->create([
                'company_id' => $by->company_id,
                'entry_no' => VoucherNumberService::next('journal'),
                'posting_date' => $postingDate,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'description' => $description,
                'reversal_of_id' => $reversalOfId,
                'created_by' => $by->id,
            ]);

            foreach ($lines as $index => [$line, $account]) {
                JournalLine::query()->create([
                    'company_id' => $by->company_id,
                    'journal_entry_id' => $entry->id,
                    'line_no' => $index + 1,
                    'ledger_account_id' => $account->id,
                    'party_id' => $line->partyId,
                    'item_id' => $line->itemId,
                    'debit' => $line->side === PostingLine::DEBIT ? $line->amount : '0.00',
                    'credit' => $line->side === PostingLine::CREDIT ? $line->amount : '0.00',
                ]);
            }

            // BR-LED-03 in the database, now (also inside tests, which never commit).
            // Then back to deferred, so the next journal of the same save is checked
            // only once all of its lines exist.
            DB::statement('SET CONSTRAINTS journal_balance_check IMMEDIATE');
            DB::statement('SET CONSTRAINTS journal_balance_check DEFERRED');
        } finally {
            self::$posting = false;
        }

        return $entry;
    }

    private static function assertInTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Journals can only be posted inside a database transaction.');
        }
    }
}
