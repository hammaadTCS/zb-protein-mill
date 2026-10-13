<?php

declare(strict_types=1);

namespace App\Domain\Periods\Models;

use App\Models\User;
use App\Support\NeverDeleted;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A month closed or reopened (spec §9.5, BR-MEC-08). Never changed or deleted
 * (database trigger).
 *
 * @property int $id
 * @property int $company_id
 * @property int $period_id
 * @property string $event closed or reopened
 * @property int $user_id
 * @property string|null $reason
 * @property Carbon|null $created_at
 * @property-read User $user
 */
#[Fillable(['company_id', 'period_id', 'event', 'user_id', 'reason'])]
class PeriodEvent extends Model
{
    use BelongsToCompany;
    use NeverDeleted;

    public const CLOSED = 'closed';

    public const REOPENED = 'reopened';

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
