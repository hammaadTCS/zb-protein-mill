<?php

declare(strict_types=1);

namespace App\Domain\Periods\Models;

use App\Models\User;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One accounting month (spec §9.5). A month without a row is open (BR-MEC-07).
 *
 * @property int $id
 * @property int $company_id
 * @property int $year
 * @property int $month
 * @property string $status open or closed
 * @property int|null $closed_by
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $closedBy
 */
#[Fillable(['company_id', 'year', 'month', 'status', 'closed_by', 'closed_at', 'created_by', 'updated_by'])]
class Period extends Model
{
    use BelongsToCompany;

    public const OPEN = 'open';

    public const CLOSED = 'closed';

    /**
     * @return BelongsTo<User, $this>
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'closed_at' => 'datetime',
        ];
    }
}
