<?php

namespace Database\Seeders;

use App\Domain\Expenses\Actions\CreateExpenseCategory;
use App\Domain\Expenses\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Tests and local computers only (card T4.1): the proposed list of spec O-11, not the
 * client's confirmed list. There is no production seeder: the Owner creates the
 * confirmed list on page 44 before go-live.
 */
class TestExpenseCategoriesSeeder extends Seeder
{
    /** name => is_quick, in this order (O-11). */
    public const CATEGORIES = [
        'Wages' => true,
        'Food' => true,
        'Tea' => true,
        'Electricity' => false,
        'Vehicle Fuel' => true,
        'Transport/Freight' => true,
        'Repair' => false,
        'Stationery' => false,
        'Mobile/Internet' => false,
        'Rent' => false,
        'Bank Charges' => false,
        'Miscellaneous' => false,
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('TestExpenseCategoriesSeeder is for tests and local use only.');
        }

        $by = User::query()->orderBy('id')->first() ?? throw new RuntimeException('Create a user first.');
        $order = 0;

        foreach (self::CATEGORIES as $name => $quick) {
            $order += 10;

            if (! ExpenseCategory::query()->withoutGlobalScope('company')->where('company_id', $by->company_id)->where('name', $name)->exists()) {
                app(CreateExpenseCategory::class)->handle($by, ['name' => $name, 'is_quick' => $quick, 'sort_order' => $order]);
            }
        }
    }
}
