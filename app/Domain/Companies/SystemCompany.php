<?php

declare(strict_types=1);

namespace App\Domain\Companies;

use App\Domain\Companies\Models\Company;

/**
 * The company SystemDataSeeder creates (version 1 has one). Server commands ask this
 * class, so a release without system data is reported instead of half-created.
 */
class SystemCompany
{
    public function find(): ?Company
    {
        return Company::query()->orderBy('id')->first();
    }
}
