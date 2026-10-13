<?php

namespace Database\Seeders;

use App\Domain\Companies\Models\Company;
use Illuminate\Database\Seeder;

/**
 * D-37: the one company of version 1. Creates it only when no company exists,
 * so running it any number of times never makes a second one.
 */
class CompanySeeder extends Seeder
{
    public function run(): void
    {
        if (Company::query()->exists()) {
            return;
        }

        Company::query()->create(['name' => 'ZB Poultry Protein Mill']);
    }
}
