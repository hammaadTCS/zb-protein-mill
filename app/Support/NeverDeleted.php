<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * BR-GEN-03: master and transaction rows are never deleted. Mistakes are made
 * inactive, edited or cancelled. The database refuses deletes too (spec §9.7).
 *
 * @mixin Model
 */
trait NeverDeleted
{
    public static function bootNeverDeleted(): void
    {
        static::deleting(function (Model $model): void {
            throw new LogicException('Rows in '.$model->getTable().' are never deleted (BR-GEN-03).');
        });
    }
}
