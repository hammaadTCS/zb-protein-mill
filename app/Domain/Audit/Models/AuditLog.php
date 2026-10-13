<?php

declare(strict_types=1);

namespace App\Domain\Audit\Models;

use App\Domain\Audit\AuditLogger;
use App\Models\User;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One permanent audit row (spec §9.5, BR-AUD-01). Written only by AuditLogger;
 * never changed or deleted (BR-AUD-02; the database refuses it too).
 *
 * @property int $id
 * @property int $company_id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $auditable_type morph alias (conventions §7)
 * @property int|null $auditable_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $reason
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 */
#[Fillable(['company_id', 'user_id', 'action', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'reason', 'ip_address', 'user_agent'])]
class AuditLog extends Model
{
    use BelongsToCompany;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::creating(function (): void {
            if (! AuditLogger::isWriting()) {
                throw new LogicException('Audit rows are written only through AuditLogger.');
            }
        });

        static::updating(fn () => throw new LogicException('Audit rows are never changed (BR-AUD-02).'));
        static::deleting(fn () => throw new LogicException('Audit rows are never deleted (BR-AUD-02).'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'auditable_id' => 'integer',
        ];
    }
}
