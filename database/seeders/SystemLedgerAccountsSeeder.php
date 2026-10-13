<?php

namespace Database\Seeders;

use App\Domain\Companies\Models\Company;
use App\Domain\Ledger\Models\LedgerAccount;
use Illuminate\Database\Seeder;

/**
 * Spec §8.1 system accounts (BR-LED-01). BR-OPS-11: a row is created only when its
 * code does not exist yet; an existing row is never changed.
 */
class SystemLedgerAccountsSeeder extends Seeder
{
    /** code => [name, type, normal side, party type] */
    public const ACCOUNTS = [
        '1200' => ['Buyers', 'asset', 'debit', 'buyer'],
        '1300' => ['Employees', 'asset', 'debit', 'employee'],
        '2000' => ['Suppliers', 'liability', 'credit', 'supplier'],
        '2100' => ['Other Parties', 'liability', 'credit', 'other'],
        '3000' => ['Partner Capital', 'equity', 'credit', 'partner'],
        '3100' => ['Partner Withdrawals', 'equity', 'debit', 'partner'],
        '3900' => ['Opening Balance Equity', 'equity', 'credit', null],
        '4000' => ['Sales – Protein product', 'income', 'credit', null],
        '4010' => ['Sales – Oil/Fat', 'income', 'credit', null],
        '4900' => ['Sales Adjustments', 'income', 'debit', null],
        '5000' => ['Purchases – Waste', 'expense', 'debit', null],
        '5010' => ['Purchases – Wood', 'expense', 'debit', null],
        '5900' => ['Purchase Adjustments', 'expense', 'credit', null],
        '6900' => ['Cash Shortage / Excess', 'expense', 'debit', null],
    ];

    public function run(): void
    {
        foreach (Company::query()->orderBy('id')->get() as $company) {
            foreach (self::ACCOUNTS as $code => [$name, $type, $side, $partyType]) {
                LedgerAccount::query()->firstOrCreate(
                    ['company_id' => $company->id, 'code' => (string) $code],
                    [
                        'name' => $name,
                        'type' => $type,
                        'normal_side' => $side,
                        'party_type' => $partyType,
                        'is_system' => true,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
