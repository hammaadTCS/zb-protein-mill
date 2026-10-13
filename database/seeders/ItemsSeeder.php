<?php

namespace Database\Seeders;

use App\Domain\Companies\Models\Company;
use App\Domain\Items\Models\Item;
use App\Domain\Ledger\Models\LedgerAccount;
use Illuminate\Database\Seeder;

/**
 * BR-ITM-01: the four items and their system ledger accounts (spec §8.1).
 * BR-OPS-11: an item is created only when its code does not exist yet; an existing
 * item is never changed. Runs after SystemLedgerAccountsSeeder.
 */
class ItemsSeeder extends Seeder
{
    /** code => [name, type, ledger account code] */
    public const ITEMS = [
        'WASTE' => ['Waste', 'raw_material', '5000'],
        'WOOD' => ['Wood', 'fuel', '5010'],
        'PROTEIN' => ['Protein product', 'product', '4000'],
        'OILFAT' => ['Oil/Fat', 'product', '4010'],
    ];

    public function run(): void
    {
        foreach (Company::query()->orderBy('id')->get() as $company) {
            foreach (self::ITEMS as $code => [$name, $type, $accountCode]) {
                $accountId = LedgerAccount::query()
                    ->withoutGlobalScope('company')
                    ->where('company_id', $company->id)
                    ->where('code', $accountCode)
                    ->value('id');

                Item::query()->withoutGlobalScope('company')->firstOrCreate(
                    ['company_id' => $company->id, 'code' => $code],
                    [
                        'name' => $name,
                        'type' => $type,
                        'unit' => 'kg',
                        'purchase_ledger_account_id' => $type === 'product' ? null : $accountId,
                        'sales_ledger_account_id' => $type === 'product' ? $accountId : null,
                        'is_system' => true,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
