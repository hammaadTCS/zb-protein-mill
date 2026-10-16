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
 * The seal row of one journal entry (spec §9.5, BR-LED-03, BR-LED-11, D-45): written by
 * PostingService at the end of the saving transaction, where the PostgreSQL code ran
 * SET CONSTRAINTS ... IMMEDIATE. The BEFORE INSERT trigger on this table sums the entry's
 * journal_lines and refuses the whole save when the entry has fewer than two lines or its
 * debits do not equal its credits. Seal rows are never changed or deleted (the database
 * refuses it too).
 *
 * @property int $id
 * @property int $company_id
 * @property int $journal_entry_id
 * @property string $source what wrote the seal; 'posting'
 * @property int $line_count
 * @property string $total_debit
 * @property string $total_credit
 * @property Carbon|null $sealed_at
 */
#[Fillable(['company_id', 'journal_entry_id', 'source', 'line_count', 'total_debit', 'total_credit', 'sealed_at'])]
class JournalSeal extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (): void {
            if (! PostingService::isPosting()) {
                throw new LogicException('Journal rows can only be created by PostingService');
            }
        });

        static::updating(fn () => throw new LogicException('Seal rows are never changed (BR-LED-11).'));
        static::deleting(fn () => throw new LogicException('Seal rows are never deleted (BR-LED-11).'));
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    protected function casts(): array
    {
        return [
            'line_count' => 'integer',
            'total_debit' => 'decimal:2',
            'total_credit' => 'decimal:2',
            'sealed_at' => 'datetime',
        ];
    }
}
