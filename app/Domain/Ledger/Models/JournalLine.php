<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Models;

use App\Domain\Ledger\PostingService;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One debit or credit line of a journal entry (spec §9.4). Money is a 2-decimal
 * string (conventions §3). Created only by PostingService; never changed.
 *
 * @property int $id
 * @property int $company_id
 * @property int $journal_entry_id
 * @property int $line_no
 * @property int $ledger_account_id
 * @property int|null $party_id
 * @property int|null $item_id
 * @property string $debit
 * @property string $credit
 * @property Carbon|null $created_at
 */
#[Fillable(['company_id', 'journal_entry_id', 'line_no', 'ledger_account_id', 'party_id', 'item_id', 'debit', 'credit'])]
class JournalLine extends Model
{
    use BelongsToCompany;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::creating(function (): void {
            if (! PostingService::isPosting()) {
                throw new LogicException('Journal rows can only be created by PostingService');
            }
        });

        static::updating(fn () => throw new LogicException('Journal rows are never updated (BR-LED-05).'));
        static::deleting(fn () => throw new LogicException('Journal rows are never deleted (BR-LED-05).'));
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    /**
     * @return BelongsTo<LedgerAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }
}
