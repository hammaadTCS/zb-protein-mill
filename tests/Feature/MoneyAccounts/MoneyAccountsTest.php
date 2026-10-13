<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Ledger\Enums\AccountType;
use App\Domain\Ledger\Enums\NormalSide;
use App\Domain\Ledger\PostingLine;
use App\Domain\Ledger\PostingRequest;
use App\Domain\Ledger\PostingService;
use App\Domain\MoneyAccounts\Actions\CreateMoneyAccount;
use App\Domain\MoneyAccounts\Actions\DeactivateMoneyAccount;
use App\Domain\MoneyAccounts\Actions\UpdateMoneyAccount;
use App\Domain\MoneyAccounts\Models\MoneyAccount;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

function moneyAccount(User $by, string $name, string $type = 'cash', array $extra = []): MoneyAccount
{
    return app(CreateMoneyAccount::class)->handle($by, ['name' => $name, 'type' => $type, ...$extra]);
}

/** An opening balance posted straight through PostingService (card T2.2 test set-up). */
function postOpening(MoneyAccount $account, string $amount, User $by, string $date = '2026-01-01'): void
{
    DB::transaction(fn () => PostingService::post(new PostingRequest(
        postingDate: $date,
        sourceType: 'money_account',
        sourceId: $account->id,
        description: 'Opening balance',
        lines: [PostingLine::debit($account->ledger_account_id, $amount), PostingLine::credit('3900', $amount)],
    ), $by));
}

beforeEach(fn () => freezeToday('2026-01-15'));

it('BR-ACC-01 creating HBL creates ledger account 1100 "Bank – HBL"', function () {
    $account = moneyAccount(owner(), 'HBL', 'bank', ['bank_name' => 'Habib Bank', 'account_title' => 'ZB Mill', 'account_number' => '0001']);
    $ledger = $account->ledgerAccount;

    expect($ledger->code)->toBe('1100')
        ->and($ledger->name)->toBe('Bank – HBL')
        ->and($ledger->type)->toBe(AccountType::Asset)
        ->and($ledger->normal_side)->toBe(NormalSide::Debit)
        ->and($ledger->is_system)->toBeFalse()
        ->and($account->bank_name)->toBe('Habib Bank')
        ->and($account->is_active)->toBeTrue();
});

it('BR-ACC-01 a second bank gets 1101; the first cash gets 1010 "Cash – Main"', function () {
    $owner = owner();
    moneyAccount($owner, 'HBL', 'bank');
    $second = moneyAccount($owner, 'Meezan', 'bank');
    $cash = moneyAccount($owner, 'Main');

    expect($second->ledgerAccount->code)->toBe('1101')
        ->and($cash->ledgerAccount->code)->toBe('1010')
        ->and($cash->ledgerAccount->name)->toBe('Cash – Main');
});

it('BR-ACC-01 a cash account keeps no bank details', function () {
    $cash = moneyAccount(owner(), 'Main', 'cash', ['bank_name' => 'Habib Bank']);

    expect($cash->bank_name)->toBeNull();
});

it('BR-ACC-01 renaming an account renames its ledger account and is audited', function () {
    $owner = owner();
    $account = moneyAccount($owner, 'HBL', 'bank');

    app(UpdateMoneyAccount::class)->handle($owner, $account, ['name' => 'HBL Main', 'account_number' => '42']);

    $audit = AuditLog::query()->where('action', 'updated')->sole();

    expect($account->ledgerAccount()->first()?->name)->toBe('Bank – HBL Main')
        ->and($account->refresh()->account_number)->toBe('42')
        ->and($audit->old_values['name'])->toBe('HBL')
        ->and($audit->new_values['name'])->toBe('HBL Main');
});

it('BR-ACC-02 an account with a balance cannot be deactivated', function () {
    $owner = owner();
    $account = moneyAccount($owner, 'Main');
    postOpening($account, '5000.00', $owner);

    expect(fn () => app(DeactivateMoneyAccount::class)->handle($owner, $account))
        ->toThrow(ValidationException::class, 'This account still has a balance of Rs 5,000.00.');

    expect($account->refresh()->is_active)->toBeTrue();
});

it('BR-ACC-02 an account with a zero balance can be deactivated', function () {
    $owner = owner();
    $account = moneyAccount($owner, 'Old box');

    app(DeactivateMoneyAccount::class)->handle($owner, $account);

    expect($account->refresh()->is_active)->toBeFalse()
        ->and(MoneyAccount::query()->active()->count())->toBe(0);
});

it('MoneyAccount::balance() is debit minus credit, optionally up to a date', function () {
    $owner = owner();
    $account = moneyAccount($owner, 'Main');
    postOpening($account, '5000.00', $owner, '2026-01-01');
    postOpening($account, '250.50', $owner, '2026-01-10');

    expect($account->balance())->toBe('5250.50')
        ->and($account->balance('2026-01-09'))->toBe('5000.00')
        ->and(moneyAccount($owner, 'Empty')->balance())->toBe('0.00');
});

it('BR-GEN-03 a money account cannot be deleted', function () {
    $account = moneyAccount(owner(), 'Main');

    expect(fn () => $account->delete())->toThrow(LogicException::class);
    expect(fn () => DB::table('money_accounts')->where('id', $account->id)->delete())
        ->toThrow(QueryException::class, 'Rows in money_accounts cannot be deleted');
});

it('accountant cannot create a money account', function () {
    $this->actingAs(accountant())
        ->post(route('money-accounts.store'), ['name' => 'Main', 'type' => 'cash'])
        ->assertForbidden();

    expect(MoneyAccount::query()->count())->toBe(0);
});

it('accountant cannot open Accounts setup', function () {
    $this->actingAs(accountant())->get(route('money-accounts.index'))->assertForbidden();
});

it('the owner creates, edits and deactivates an account from page 46', function () {
    $owner = owner();

    $this->actingAs($owner)
        ->post(route('money-accounts.store'), ['name' => 'HBL', 'type' => 'bank', 'account_number' => '1234'])
        ->assertRedirect(route('money-accounts.index'))
        ->assertInertiaFlash('success', 'Saved.');

    $account = MoneyAccount::query()->sole();

    $this->actingAs($owner)
        ->put(route('money-accounts.update', $account), ['name' => 'HBL Main', 'type' => 'cash'])
        ->assertRedirect(route('money-accounts.index'));

    $this->actingAs($owner)
        ->post(route('money-accounts.deactivate', $account))
        ->assertRedirect(route('money-accounts.index'));

    $account->refresh();

    expect($account->name)->toBe('HBL Main')
        ->and($account->type->value)->toBe('bank')
        ->and($account->is_active)->toBeFalse();

    $this->actingAs($owner)
        ->get(route('money-accounts.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('money-accounts/index')
            ->where('accounts.0.name', 'HBL Main')
            ->where('accounts.0.code', '1100')
            ->where('accounts.0.balance', '0.00')
            ->where('accounts.0.is_active', false));
});

it('a money account name is required and the type must be cash or bank', function () {
    $this->actingAs(owner())
        ->post(route('money-accounts.store'), ['name' => '', 'type' => 'wallet'])
        ->assertSessionHasErrors(['name', 'type']);
});
