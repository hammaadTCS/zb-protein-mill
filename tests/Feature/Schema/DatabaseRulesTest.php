<?php

use App\Domain\MoneyAccounts\Actions\CreateMoneyAccount;
use App\Domain\Parties\Actions\CreateParty;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Card T10.2: the rules the MariaDB schema itself carries. These tests read and write the
 * real migrated schema, not the application code that checks the same rules first, so a
 * dropped index or a lost collation is caught here rather than in production.
 */

/** The collation of one column of the current database, from the server's own catalogue. */
function columnCollation(string $table, string $column): ?string
{
    $row = DB::selectOne(
        'SELECT collation_name FROM information_schema.columns'
        .' WHERE table_schema = ? AND table_name = ? AND column_name = ?',
        [DB::getDatabaseName(), $table, $column],
    );

    return $row?->collation_name;
}

/**
 * BR-OPB-02 / Q-8. The PostgreSQL schema had two partial unique indexes
 * (`UNIQUE ... WHERE ... IS NOT NULL`). MariaDB has no partial indexes, so both are plain
 * unique indexes over a nullable column, and MariaDB allows many NULLs in one — the same
 * behaviour, proved here.
 */
it('Q-8 an opening-balance unique index allows many NULLs but refuses a duplicate target', function () {
    $owner = owner();
    $companyId = $owner->company_id;

    $cashA = app(CreateMoneyAccount::class)->handle($owner, ['name' => 'Cash A', 'type' => 'cash']);
    $cashB = app(CreateMoneyAccount::class)->handle($owner, ['name' => 'Cash B', 'type' => 'cash']);
    $aslam = app(CreateParty::class)->handle($owner, ['type' => 'supplier', 'name' => 'Aslam', 'mobile' => '03000000001']);
    $karim = app(CreateParty::class)->handle($owner, ['type' => 'supplier', 'name' => 'Karim', 'mobile' => '03000000002']);

    $insert = function (array $target, string $voucherNo) use ($companyId): void {
        DB::table('opening_balances')->insert([
            'company_id' => $companyId,
            'voucher_no' => $voucherNo,
            'as_of_date' => '2026-01-01',
            'amount' => '100.00',
            'side' => isset($target['money_account_id']) ? 'balance' : 'we_owe',
        ] + $target);
    };

    // Two party rows: money_account_id is NULL in both, which the unique index on it allows.
    $insert(['party_id' => $aslam->id], 'OPB-A');
    $insert(['party_id' => $karim->id], 'OPB-B');

    // Two account rows: party_id is NULL in both.
    $insert(['money_account_id' => $cashA->id], 'OPB-C');
    $insert(['money_account_id' => $cashB->id], 'OPB-D');

    expect(DB::table('opening_balances')->count())->toBe(4);

    // A second row for the same account, or the same party, is refused by the index.
    expect(fn () => $insert(['money_account_id' => $cashA->id], 'OPB-E'))
        ->toThrow(QueryException::class, 'opening_balances_money_account_unique');

    expect(fn () => $insert(['party_id' => $aslam->id], 'OPB-F'))
        ->toThrow(QueryException::class, 'opening_balances_party_unique');
});

/**
 * Q-6. On PostgreSQL these columns were compared by rule, so "Aslam", "aslam" and "Aslam "
 * were three different values. On MariaDB the rule is the column collation: a binary
 * collation keeps them apart, while the default `utf8mb4_unicode_ci` would fold the case and
 * the trailing space and refuse the second row. This proves the unique rule still holds and
 * still tells the three apart.
 */
it('Q-6 a unique-rule column tells "Aslam", "aslam" and "Aslam " apart', function () {
    $companyId = owner()->company_id;

    $insert = fn (string $code) => DB::table('ledger_accounts')->insert([
        'company_id' => $companyId,
        'code' => $code,
        'name' => "Test {$code}",
        'type' => 'expense',
        'normal_side' => 'debit',
    ]);

    // Three rows, three different values: no duplicate rule is broken.
    foreach (['Aslam', 'aslam', 'Aslam '] as $code) {
        $insert($code);
    }

    expect(DB::table('ledger_accounts')->where('code', 'Aslam')->count())->toBe(1)
        ->and(DB::table('ledger_accounts')->where('code', 'aslam')->count())->toBe(1)
        ->and(DB::table('ledger_accounts')->where('code', 'Aslam ')->count())->toBe(1);

    // A real duplicate is still refused.
    expect(fn () => $insert('Aslam'))
        ->toThrow(QueryException::class, 'ledger_accounts_company_id_code_unique');
});

/**
 * §14c. The unique-rule columns carry the binary collation and nothing else does, so the
 * duplicate rules and the case-insensitive searches both keep working. This is a snapshot:
 * a new unique-rule column must be added here, on purpose.
 */
it('§14c the unique-rule columns are binary-collated and the search columns are not', function () {
    $binary = collect(DB::select(
        "SELECT CONCAT_WS('.', table_name, column_name) AS pair FROM information_schema.columns"
        .' WHERE table_schema = ? AND collation_name = ?',
        [DB::getDatabaseName(), 'utf8mb4_nopad_bin'],
    ))->pluck('pair')->sort()->values()->all();

    expect($binary)->toBe([
        'adjustments.idempotency_key',
        'adjustments.public_id',
        'adjustments.voucher_no',
        'attachments.public_id',
        'expense_categories.name',
        'expense_categories.public_id',
        'expenses.idempotency_key',
        'expenses.public_id',
        'expenses.voucher_no',
        'items.code',
        'items.name',
        'items.public_id',
        'journal_entries.entry_no',
        'ledger_accounts.code',
        'ledger_accounts.name',
        'money_accounts.name',
        'money_accounts.public_id',
        'opening_balances.voucher_no',
        'parties.code',
        'parties.name',
        'parties.public_id',
        'partner_transactions.idempotency_key',
        'partner_transactions.public_id',
        'partner_transactions.voucher_no',
        'payments.idempotency_key',
        'payments.public_id',
        'payments.voucher_no',
        'purchases.idempotency_key',
        'purchases.public_id',
        'purchases.voucher_no',
        'rate_corrections.voucher_no',
        'sales.idempotency_key',
        'sales.public_id',
        'sales.voucher_no',
        'settings.key',
        'transfers.idempotency_key',
        'transfers.public_id',
        'transfers.voucher_no',
        'users.email',
        'users.public_id',
        'voucher_sequences.doc_type',
    ]);

    expect(columnCollation('parties', 'name_ur'))->toBe('utf8mb4_unicode_ci')
        ->and(columnCollation('parties', 'notes'))->toBe('utf8mb4_unicode_ci')
        ->and(columnCollation('parties', 'address'))->toBe('utf8mb4_unicode_ci')
        ->and(columnCollation('journal_entries', 'description'))->toBe('utf8mb4_unicode_ci')
        ->and(columnCollation('purchases', 'note'))->toBe('utf8mb4_unicode_ci');
});
