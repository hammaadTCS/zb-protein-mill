<?php

use App\Domain\Numbering\VoucherNumberService;
use Illuminate\Support\Facades\DB;

it('T-04 numbers are sequential per doc type', function () {
    $numbers = DB::transaction(fn () => [
        VoucherNumberService::next('purchase'),
        VoucherNumberService::next('purchase'),
        VoucherNumberService::next('purchase'),
        VoucherNumberService::next('sale'),
    ]);

    expect($numbers)->toBe(['PUR-000001', 'PUR-000002', 'PUR-000003', 'SAL-000001']);
});

it('T-04 a rolled-back save does not consume a number', function () {
    try {
        DB::transaction(function () {
            VoucherNumberService::next('purchase');

            throw new RuntimeException('save failed');
        });
    } catch (RuntimeException) {
        // the save failed and was rolled back
    }

    expect(DB::transaction(fn () => VoucherNumberService::next('purchase')))->toBe('PUR-000001');
});

it('T-04 calling outside a transaction is refused', function () {
    // Every test runs inside RefreshDatabase's transaction, so use a second connection
    // to the same database that has no open transaction.
    config(['database.connections.mysql_outside' => config('database.connections.mysql')]);
    $default = DB::getDefaultConnection();
    DB::setDefaultConnection('mysql_outside');

    try {
        expect(fn () => VoucherNumberService::next('purchase'))
            ->toThrow(LogicException::class, 'inside a database transaction');
    } finally {
        DB::setDefaultConnection($default);
        DB::purge('mysql_outside');
    }
});

it('party codes use width 4', function () {
    expect(DB::transaction(fn () => [
        VoucherNumberService::next('party_supplier'),
        VoucherNumberService::next('party_supplier'),
        VoucherNumberService::next('party_buyer'),
    ]))->toBe(['S-0001', 'S-0002', 'B-0001']);
});

it('every doc type of conventions §8 has its prefix and width', function (string $docType, string $first) {
    expect(DB::transaction(fn () => VoucherNumberService::next($docType)))->toBe($first);
})->with([
    ['purchase', 'PUR-000001'], ['sale', 'SAL-000001'], ['adjustment', 'ADJ-000001'],
    ['payment_paid', 'PAY-000001'], ['payment_received', 'RCV-000001'], ['expense', 'EXP-000001'],
    ['partner_transaction', 'PTN-000001'], ['transfer', 'TRF-000001'], ['opening_balance', 'OPB-000001'],
    ['rate_correction', 'RCO-000001'], ['cash_adjustment', 'CSH-000001'], ['journal', 'JV-000001'],
    ['party_supplier', 'S-0001'], ['party_buyer', 'B-0001'], ['party_partner', 'P-0001'],
    ['party_employee', 'E-0001'], ['party_other', 'O-0001'],
]);

it('an unknown doc type is refused', function () {
    DB::transaction(fn () => VoucherNumberService::next('cheque'));
})->throws(InvalidArgumentException::class);
