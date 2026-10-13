<?php

use App\Domain\Ledger\AccountCodeAllocator;
use App\Domain\Ledger\Enums\AccountType;
use App\Domain\Ledger\Enums\NormalSide;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Support\Tenancy\CurrentCompany;
use Database\Seeders\SystemDataSeeder;

it('spec §8.1 system accounts are seeded exactly', function () {
    $rows = LedgerAccount::query()->orderBy('code')->get()
        ->map(fn (LedgerAccount $a) => [$a->code, $a->name, $a->type->value, $a->normal_side->value, $a->party_type, $a->is_system, $a->is_active])
        ->all();

    expect($rows)->toBe([
        ['1200', 'Buyers', 'asset', 'debit', 'buyer', true, true],
        ['1300', 'Employees', 'asset', 'debit', 'employee', true, true],
        ['2000', 'Suppliers', 'liability', 'credit', 'supplier', true, true],
        ['2100', 'Other Parties', 'liability', 'credit', 'other', true, true],
        ['3000', 'Partner Capital', 'equity', 'credit', 'partner', true, true],
        ['3100', 'Partner Withdrawals', 'equity', 'debit', 'partner', true, true],
        ['3900', 'Opening Balance Equity', 'equity', 'credit', null, true, true],
        ['4000', 'Sales – Protein product', 'income', 'credit', null, true, true],
        ['4010', 'Sales – Oil/Fat', 'income', 'credit', null, true, true],
        ['4900', 'Sales Adjustments', 'income', 'debit', null, true, true],
        ['5000', 'Purchases – Waste', 'expense', 'debit', null, true, true],
        ['5010', 'Purchases – Wood', 'expense', 'debit', null, true, true],
        ['5900', 'Purchase Adjustments', 'expense', 'credit', null, true, true],
        ['6900', 'Cash Shortage / Excess', 'expense', 'debit', null, true, true],
    ]);
});

it('BR-OPS-11 running SystemDataSeeder twice creates no duplicate account', function () {
    $this->seed(SystemDataSeeder::class);
    $this->seed(SystemDataSeeder::class);

    expect(LedgerAccount::query()->count())->toBe(14);
});

it('BR-LED-01 a system account cannot be renamed or disabled', function (array $change) {
    $account = LedgerAccount::query()->where('code', '2000')->firstOrFail();

    expect(fn () => $account->update($change))->toThrow(LogicException::class);
    expect($account->fresh()->name)->toBe('Suppliers');
})->with([
    'rename' => [['name' => 'Creditors']],
    'disable' => [['is_active' => false]],
    'new code' => [['code' => '2001']],
    'new type' => [['type' => AccountType::Asset]],
    'new side' => [['normal_side' => NormalSide::Debit]],
    'new party type' => [['party_type' => 'buyer']],
]);

it('code allocator returns the next free code and stops at the range end', function () {
    expect(AccountCodeAllocator::next('cash'))->toBe('1010');

    makeAccount('1010');
    makeAccount('1012');
    expect(AccountCodeAllocator::next('cash'))->toBe('1011');

    makeAccount('1011');
    expect(AccountCodeAllocator::next('cash'))->toBe('1013');

    foreach (range(1013, 1099) as $code) {
        makeAccount((string) $code);
    }
    expect(fn () => AccountCodeAllocator::next('cash'))->toThrow(LogicException::class);

    expect(AccountCodeAllocator::next('bank'))->toBe('1100')
        ->and(AccountCodeAllocator::next('purchase_item'))->toBe('5020')
        ->and(AccountCodeAllocator::next('sales_item'))->toBe('4020')
        ->and(AccountCodeAllocator::next('expense_category'))->toBe('6000');
});

it('code allocator refuses an unknown range', function () {
    AccountCodeAllocator::next('loans');
})->throws(InvalidArgumentException::class);

function makeAccount(string $code): void
{
    LedgerAccount::query()->create([
        'company_id' => CurrentCompany::id(),
        'code' => $code,
        'name' => "Cash {$code}",
        'type' => AccountType::Asset,
        'normal_side' => NormalSide::Debit,
        'is_system' => false,
        'is_active' => true,
    ]);
}
