<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Domain\Companies\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * BR-GEN-01: every record belongs to a company, and every query is filtered by the
 * logged-in user's company. Never used on User itself.
 *
 * Without a logged-in user (console, seeders, queued work) nothing is filtered and
 * company_id must be set explicitly.
 *
 * @mixin Model
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $query): void {
            $companyId = self::loggedInCompanyId();

            if ($companyId !== null) {
                $query->where($query->getModel()->qualifyColumn('company_id'), $companyId);
            }
        });

        static::creating(function (Model $model): void {
            $companyId = self::loggedInCompanyId();

            if ($companyId === null) {
                if ($model->getAttribute('company_id') === null) {
                    throw new LogicException('company_id must be set when no user is logged in.');
                }

                return;
            }

            $given = $model->getAttribute('company_id');

            if ($given !== null && (int) $given !== $companyId) {
                throw new LogicException('A user cannot create records for another company.');
            }

            $model->setAttribute('company_id', $companyId);
        });
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    private static function loggedInCompanyId(): ?int
    {
        $user = auth()->user();

        return $user instanceof User ? $user->company_id : null;
    }
}
