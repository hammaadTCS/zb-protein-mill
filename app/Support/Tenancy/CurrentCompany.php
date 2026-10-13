<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Domain\Companies\Models\Company;
use App\Models\User;
use LogicException;

/**
 * The company the current code runs for: the logged-in user's company, or, without
 * a logged-in user (console, seeders, tests), the first company. Version 1 has one.
 */
final class CurrentCompany
{
    public static function id(): int
    {
        $user = auth()->user();

        if ($user instanceof User) {
            return $user->company_id;
        }

        $id = Company::query()->orderBy('id')->value('id');

        if ($id === null) {
            throw new LogicException('No company exists. Run SystemDataSeeder first.');
        }

        return (int) $id;
    }
}
