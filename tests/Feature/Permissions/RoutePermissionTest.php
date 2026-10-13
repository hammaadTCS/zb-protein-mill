<?php

use App\Domain\Adjustments\Actions\CreateAdjustment;
use App\Domain\Expenses\Actions\CreateExpense;
use App\Domain\Expenses\Models\ExpenseCategory;
use App\Domain\Items\Models\Item;
use App\Domain\Partners\Actions\CreatePartnerTransaction;
use App\Domain\Payments\Actions\CreatePayment;
use App\Domain\Purchases\Actions\CreatePurchase;
use App\Domain\Sales\Actions\CreateSale;
use App\Domain\Transfers\Actions\CreateTransfer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TestExpenseCategoriesSeeder;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Spec §7 matrix per route: the permission each application route needs. `null`
 * means any logged-in user (the page checks the record itself). Every new route must
 * be added here (T-09).
 */
const ROUTE_PERMISSIONS = [
    'dashboard' => null,
    'rate-pending.index' => null, // purchase.view or sale.view, checked in the controller
    'attachments.show' => null, // the attached record's own view permission
    'accounts.index' => 'account.view',
    'accounts.show' => 'account.view',
    'adjustments.cancel' => 'adjustment.cancel',
    'audit-log.index' => 'audit.view',
    'cash-counts.index' => 'cashcount.create',
    'cash-counts.system-amount' => 'cashcount.create',
    'cash-counts.store' => 'cashcount.create',
    'cash-counts.dismiss' => 'period.close',
    'approvals.index' => 'payment.approve',
    'approvals.approve' => 'payment.approve',
    'approvals.reject' => 'payment.approve',
    'expenses.index' => 'expense.view',
    'expenses.create' => 'expense.create',
    'expenses.store' => 'expense.create',
    'expenses.link-lookup' => 'expense.create',
    'expenses.show' => 'expense.view',
    'expenses.edit' => 'expense.update',
    'expenses.update' => 'expense.update',
    'expenses.cancel' => 'expense.cancel',
    'parties.index' => 'party.view',
    'parties.search' => 'party.view',
    'parties.show' => 'party.view',
    'parties.create' => 'party.create',
    'parties.store' => 'party.create',
    'parties.edit' => 'party.update',
    'parties.update' => 'party.update',
    'parties.activate' => 'party.update',
    'parties.deactivate' => 'party.update',
    'parties.review' => 'party.review',
    'partners.index' => 'partner.view',
    'partners.statement' => 'partner.view',
    'partners.create' => 'partner.manage',
    'partners.store' => 'partner.manage',
    'partners.edit' => 'partner.manage',
    'partners.update' => 'partner.manage',
    'partners.cancel' => 'partner.manage',
    'payments.index' => 'payment.view',
    'payments.balance-preview' => 'payment.view',
    'payments.show' => 'payment.view',
    'payments.create' => 'payment.create',
    'payments.store' => 'payment.create',
    'payments.edit' => 'payment.update',
    'payments.update' => 'payment.update',
    'payments.cancel' => 'payment.cancel',
    'purchases.index' => 'purchase.view',
    'purchases.last-rate' => 'purchase.view',
    'purchases.show' => 'purchase.view',
    'purchases.create' => 'purchase.create',
    'purchases.store' => 'purchase.create',
    'purchases.edit' => 'purchase.update',
    'purchases.update' => 'purchase.update',
    'purchases.rate' => 'purchase.update',
    'purchases.rate-correction' => 'purchase.update',
    'purchases.cancel' => 'purchase.cancel',
    'purchases.adjustments.store' => 'adjustment.create',
    'sales.index' => 'sale.view',
    'sales.last-rate' => 'sale.view',
    'sales.show' => 'sale.view',
    'sales.create' => 'sale.create',
    'sales.store' => 'sale.create',
    'sales.edit' => 'sale.update',
    'sales.update' => 'sale.update',
    'sales.rate' => 'sale.update',
    'sales.rate-correction' => 'sale.update',
    'sales.cancel' => 'sale.cancel',
    'sales.adjustments.store' => 'adjustment.create',
    'reports.trial-balance' => 'report.view',
    'reports.daily-summary' => 'report.view',
    'reports.pending-balances' => 'report.view',
    'company-settings.edit' => 'settings.manage',
    'company-settings.update' => 'settings.manage',
    'money-accounts.index' => 'settings.manage',
    'money-accounts.store' => 'settings.manage',
    'money-accounts.update' => 'settings.manage',
    'money-accounts.deactivate' => 'settings.manage',
    'expense-categories.index' => 'settings.manage',
    'expense-categories.store' => 'settings.manage',
    'expense-categories.update' => 'settings.manage',
    'expense-categories.deactivate' => 'settings.manage',
    'items.index' => 'settings.manage',
    'items.store' => 'settings.manage',
    'items.update' => 'settings.manage',
    'items.deactivate' => 'settings.manage',
    'opening-balances.index' => 'opening.manage',
    'opening-balances.store' => 'opening.manage',
    'opening-balances.complete' => 'opening.manage',
    'users.index' => 'users.manage',
    'users.store' => 'users.manage',
    'users.disable' => 'users.manage',
    'users.enable' => 'users.manage',
    'users.password' => 'users.manage',
    'users.role' => 'users.manage',
    'transfers.index' => 'transfer.view',
    'transfers.create' => 'transfer.create',
    'transfers.store' => 'transfer.create',
    'transfers.edit' => 'transfer.update',
    'transfers.update' => 'transfer.update',
    'transfers.cancel' => 'transfer.cancel',
];

/** Login, logout, the home redirect and the kit's own account routes are not classified. */
const ROUTES_NOT_CLASSIFIED = [
    'home', 'login', 'login.store', 'logout', 'profile.edit', 'profile.update', 'security.edit', 'appearance.edit',
    'user-password.update', 'password.confirm', 'password.confirm.store', 'password.confirmation',
    'two-factor.login', 'two-factor.login.store', 'two-factor.enable', 'two-factor.confirm', 'two-factor.disable',
    'two-factor.qr-code', 'two-factor.secret-key', 'two-factor.recovery-codes', 'two-factor.regenerate-recovery-codes',
];

/** @return list<RoutingRoute> application routes (named; development tools and the health check have no name) */
function applicationRoutes(): array
{
    return array_values(array_filter(
        Route::getRoutes()->getRoutes(),
        fn (RoutingRoute $route) => $route->getName() !== null && ! in_array($route->getName(), ROUTES_NOT_CLASSIFIED, true),
    ));
}

/**
 * One record for every route parameter, so model binding finds it and the request
 * reaches the permission check.
 *
 * @return array<string, int>
 */
function routeParameters(User $owner): array
{
    $world = acceptanceWorld(acceptance('at1'), $owner);
    test()->seed(TestExpenseCategoriesSeeder::class);
    $key = fn () => (string) Str::uuid();
    $cash = $world['accounts']['cash']->id;
    $item = fn (string $code) => Item::query()->where('code', $code)->value('id');

    $purchase = app(CreatePurchase::class)->handle($owner, ['idempotency_key' => $key(), 'date' => '2026-01-05', 'party_id' => $world['parties']['aslam']->id, 'item_id' => $item('WASTE'), 'kg' => '800', 'rate' => '50']);
    $sale = app(CreateSale::class)->handle($owner, ['idempotency_key' => $key(), 'date' => '2026-01-05', 'party_id' => $world['parties']['abc']->id, 'item_id' => $item('PROTEIN'), 'kg' => '300', 'rate' => '210']);
    $payment = app(CreatePayment::class)->handle($owner, ['idempotency_key' => $key(), 'date' => '2026-01-05', 'direction' => 'paid', 'party_id' => $world['parties']['aslam']->id, 'amount' => '1000', 'money_account_id' => $cash]);
    $category = ExpenseCategory::query()->orderBy('id')->firstOrFail();
    $expense = app(CreateExpense::class)->handle($owner, ['idempotency_key' => $key(), 'date' => '2026-01-05', 'category_id' => $category->id, 'amount' => '100', 'payment_mode' => 'paid', 'money_account_id' => $cash, 'note' => 'Tea']);
    $partner = app(CreatePartnerTransaction::class)->handle($owner, ['idempotency_key' => $key(), 'date' => '2026-01-05', 'party_id' => $world['parties']['ali']->id, 'type' => 'investment', 'amount' => '1000', 'money_account_id' => $cash]);
    $transfer = app(CreateTransfer::class)->handle($owner, ['idempotency_key' => $key(), 'date' => '2026-01-05', 'from_account_id' => $world['accounts']['hbl']->id, 'to_account_id' => $cash, 'amount' => '1000']);
    $adjustment = app(CreateAdjustment::class)->handle($owner, $sale, ['idempotency_key' => $key(), 'date' => '2026-01-05', 'amount' => '100', 'reason' => 'quality', 'note' => 'Agreed']);

    return [
        'purchase' => $purchase->id, 'sale' => $sale->id, 'payment' => $payment->id, 'expense' => $expense->id,
        'partnerTransaction' => $partner->id, 'transfer' => $transfer->id, 'adjustment' => $adjustment->id,
        'party' => $world['parties']['aslam']->id, 'moneyAccount' => $cash, 'category' => $category->id,
        'item' => (int) $item('WOOD'), 'user' => User::factory()->create()->assignRole(RolesAndPermissionsSeeder::ACCOUNTANT)->id,
        'attachment' => 999999, // none exists: the attachment check is the record's own (404 for both users)
    ];
}

it('T-09 every application route is classified', function () {
    $names = array_map(fn (RoutingRoute $route) => $route->getName(), applicationRoutes());

    expect(array_values(array_diff($names, array_keys(ROUTE_PERMISSIONS))))->toBe([], 'routes missing from ROUTE_PERMISSIONS')
        ->and(array_values(array_diff(array_keys(ROUTE_PERMISSIONS), $names)))->toBe([], 'ROUTE_PERMISSIONS names a route that does not exist');
});

it('T-09 BR-SEC-05 the accountant gets 403 where the matrix says No; the owner never gets 403', function () {
    freezeToday('2026-01-31');
    $owner = owner();
    $accountant = accountant();
    $parameters = routeParameters($owner);
    $ownerOnly = RolesAndPermissionsSeeder::OWNER_ONLY;
    $checked = 0;

    foreach (applicationRoutes() as $route) {
        $name = (string) $route->getName();
        $permission = ROUTE_PERMISSIONS[$name];
        $method = $route->methods()[0];
        $uri = route($name, array_intersect_key($parameters, array_flip($route->parameterNames())), false);

        if ($permission !== null && in_array($permission, $ownerOnly, true)) {
            $status = $this->actingAs($accountant)->call($method, $uri)->getStatusCode();
            expect($status)->toBe(403, "accountant on {$method} {$name}");
            $checked++;
        }

        $status = $this->actingAs($owner)->call($method, $uri)->getStatusCode();
        expect($status)->not->toBe(403, "owner on {$method} {$name}");
    }

    expect($checked)->toBeGreaterThan(20);
});

it('T-09 the accountant may use every route whose permission the matrix gives them', function () {
    $accountant = accountant();

    foreach (array_filter(ROUTE_PERMISSIONS) as $name => $permission) {
        expect($accountant->can($permission))->toBe(! in_array($permission, RolesAndPermissionsSeeder::OWNER_ONLY, true), $name);
    }
});
