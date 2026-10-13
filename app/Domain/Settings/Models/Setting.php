<?php

declare(strict_types=1);

namespace App\Domain\Settings\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One company setting (spec §9.2, keys in §9.6). Read and written through
 * App\Domain\Settings\Settings, which types and validates the value.
 *
 * @property int $id
 * @property int $company_id
 * @property string $key
 * @property string|null $value
 * @property string $value_type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['company_id', 'key', 'value', 'value_type'])]
class Setting extends Model
{
    use BelongsToCompany;
}
