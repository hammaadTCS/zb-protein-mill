<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Models;

use App\Domain\Ledger\PostingService;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One journal entry (spec §9.4). Created only by PostingService; never updated or
 * deleted (BR-LED-05; the database refuses it too).
 *
 * @property int $id
 * @property int $company_id
 * @property string $entry_no
 * @property string $posting_date Y-m-d
 * @property string $source_type
 * @property int $source_id
 * @property string $description
 * @property int|null $reversal_of_id
 * @property int $created_by
 * @property Carbon|null $created_at
 */
#[Fillable(['company_id', 'entry_no', 'posting_date', 'source_type', 'source_id', 'description', 'reversal_of_id', 'created_by'])]
class JournalEntry extends Model
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
     * @return HasMany<JournalLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('line_no');
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_of_id');
    }

    protected function casts(): array
    {
        return [
            'source_id' => 'integer',
        ];
    }
}
