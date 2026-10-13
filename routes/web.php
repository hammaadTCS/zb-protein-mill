<?php

use App\Http\Controllers\AdjustmentController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CashCountController;
use App\Http\Controllers\CompanySettingsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\MoneyAccountController;
use App\Http\Controllers\OpeningBalanceController;
use App\Http\Controllers\PartnerTransactionController;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\RateCorrectionController;
use App\Http\Controllers\RatePendingController;
use App\Http\Controllers\Reports\TrialBalanceController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\UserController;
use Illuminate\Routing\RedirectController;
use Illuminate\Support\Facades\Route;

// GET only (see routes/settings.php): Route::redirect() would also register QUERY.
Route::get('/', RedirectController::class)
    ->defaults('destination', '/dashboard')
    ->defaults('status', 302)
    ->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Pages 3, 4 and 5: purchases (T2.7, T2.8).
    Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index')->can('purchase.view');
    Route::get('purchases/create', [PurchaseController::class, 'create'])->name('purchases.create')->can('purchase.create');
    Route::post('purchases', [PurchaseController::class, 'store'])->name('purchases.store')->can('purchase.create');
    Route::get('purchases/last-rate', [PurchaseController::class, 'lastRate'])->name('purchases.last-rate')->can('purchase.view');
    Route::get('purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show')->can('purchase.view');
    Route::get('purchases/{purchase}/edit', [PurchaseController::class, 'edit'])->name('purchases.edit')->can('purchase.update');
    Route::put('purchases/{purchase}', [PurchaseController::class, 'update'])->name('purchases.update')->can('purchase.update');
    Route::post('purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])->name('purchases.cancel')->can('purchase.cancel');
    Route::post('purchases/{purchase}/rate', [PurchaseController::class, 'addRate'])->name('purchases.rate')->can('purchase.update');
    Route::post('purchases/{purchase}/adjustments', [AdjustmentController::class, 'storeForPurchase'])->name('purchases.adjustments.store')->can('adjustment.create');
    Route::post('purchases/{purchase}/rate-correction', [RateCorrectionController::class, 'storeForPurchase'])->name('purchases.rate-correction')->can('purchase.update');

    // Pages 12, 13 and 14: expenses (T4.2, T4.3).
    Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index')->can('expense.view');
    Route::get('expenses/create', [ExpenseController::class, 'create'])->name('expenses.create')->can('expense.create');
    Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store')->can('expense.create');
    Route::get('expenses/link-lookup', [ExpenseController::class, 'linkLookup'])->name('expenses.link-lookup')->can('expense.create');
    Route::get('expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show')->can('expense.view');
    Route::get('expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit')->can('expense.update');
    Route::put('expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update')->can('expense.update');
    Route::post('expenses/{expense}/cancel', [ExpenseController::class, 'cancel'])->name('expenses.cancel')->can('expense.cancel');

    // Pages 15 and 16: transfers (T4.5).
    Route::get('transfers', [TransferController::class, 'index'])->name('transfers.index')->can('transfer.view');
    Route::get('transfers/create', [TransferController::class, 'create'])->name('transfers.create')->can('transfer.create');
    Route::post('transfers', [TransferController::class, 'store'])->name('transfers.store')->can('transfer.create');
    Route::get('transfers/{transfer}/edit', [TransferController::class, 'edit'])->name('transfers.edit')->can('transfer.update');
    Route::put('transfers/{transfer}', [TransferController::class, 'update'])->name('transfers.update')->can('transfer.update');
    Route::post('transfers/{transfer}/cancel', [TransferController::class, 'cancel'])->name('transfers.cancel')->can('transfer.cancel');

    // Pages 21, 22 and 23: partner money (T4.4).
    Route::get('partners', [PartnerTransactionController::class, 'index'])->name('partners.index')->can('partner.view');
    Route::get('partners/create', [PartnerTransactionController::class, 'create'])->name('partners.create')->can('partner.manage');
    Route::post('partners', [PartnerTransactionController::class, 'store'])->name('partners.store')->can('partner.manage');
    Route::get('partners/statement', [PartnerTransactionController::class, 'statement'])->name('partners.statement')->can('partner.view');
    Route::get('partners/{partnerTransaction}/edit', [PartnerTransactionController::class, 'edit'])->name('partners.edit')->can('partner.manage');
    Route::put('partners/{partnerTransaction}', [PartnerTransactionController::class, 'update'])->name('partners.update')->can('partner.manage');
    Route::post('partners/{partnerTransaction}/cancel', [PartnerTransactionController::class, 'cancel'])->name('partners.cancel')->can('partner.manage');

    // Pages 9, 10 and 11: payments and receipts (T3.5, T3.6).
    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index')->can('payment.view');
    Route::get('payments/create', [PaymentController::class, 'create'])->name('payments.create')->can('payment.create');
    Route::post('payments', [PaymentController::class, 'store'])->name('payments.store')->can('payment.create');
    Route::get('payments/balance-preview', [PaymentController::class, 'balancePreview'])->name('payments.balance-preview')->can('payment.view');
    Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show')->can('payment.view');
    Route::get('payments/{payment}/edit', [PaymentController::class, 'edit'])->name('payments.edit')->can('payment.update');
    Route::put('payments/{payment}', [PaymentController::class, 'update'])->name('payments.update')->can('payment.update');
    Route::post('payments/{payment}/cancel', [PaymentController::class, 'cancel'])->name('payments.cancel')->can('payment.cancel');

    // Pages 6, 7 and 8: sales (T3.4).
    Route::get('sales', [SaleController::class, 'index'])->name('sales.index')->can('sale.view');
    Route::get('sales/create', [SaleController::class, 'create'])->name('sales.create')->can('sale.create');
    Route::post('sales', [SaleController::class, 'store'])->name('sales.store')->can('sale.create');
    Route::get('sales/last-rate', [SaleController::class, 'lastRate'])->name('sales.last-rate')->can('sale.view');
    Route::get('sales/{sale}', [SaleController::class, 'show'])->name('sales.show')->can('sale.view');
    Route::get('sales/{sale}/edit', [SaleController::class, 'edit'])->name('sales.edit')->can('sale.update');
    Route::put('sales/{sale}', [SaleController::class, 'update'])->name('sales.update')->can('sale.update');
    Route::post('sales/{sale}/cancel', [SaleController::class, 'cancel'])->name('sales.cancel')->can('sale.cancel');
    Route::post('sales/{sale}/rate', [SaleController::class, 'addRate'])->name('sales.rate')->can('sale.update');
    Route::post('sales/{sale}/adjustments', [AdjustmentController::class, 'storeForSale'])->name('sales.adjustments.store')->can('adjustment.create');
    Route::post('sales/{sale}/rate-correction', [RateCorrectionController::class, 'storeForSale'])->name('sales.rate-correction')->can('sale.update');
    Route::post('adjustments/{adjustment}/cancel', [AdjustmentController::class, 'cancel'])->name('adjustments.cancel')->can('adjustment.cancel');

    // Page 27: approvals (T3.7).
    Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index')->can('payment.approve');
    Route::post('approvals/{payment}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve')->can('payment.approve');
    Route::post('approvals/{payment}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject')->can('payment.approve');

    // Page 26: rate pending (T3.1).
    Route::get('rate-pending', [RatePendingController::class, 'index'])->name('rate-pending.index');

    // Slip and receipt photos, only through a permission check (BR-SEC-06, T2.8).
    Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');

    // Pages 17 and 18: parties (T2.5). No delete route exists (BR-PTY-05).
    Route::get('parties', [PartyController::class, 'index'])->name('parties.index')->can('party.view');
    Route::get('parties/create', [PartyController::class, 'create'])->name('parties.create')->can('party.create');
    Route::get('parties/search', [PartyController::class, 'lookup'])->name('parties.search')->can('party.view');
    Route::post('parties', [PartyController::class, 'store'])->name('parties.store')->can('party.create');
    Route::get('parties/{party}', [PartyController::class, 'show'])->name('parties.show')->can('party.view');
    Route::get('parties/{party}/edit', [PartyController::class, 'edit'])->name('parties.edit')->can('party.update');
    Route::put('parties/{party}', [PartyController::class, 'update'])->name('parties.update')->can('party.update');
    Route::post('parties/{party}/deactivate', [PartyController::class, 'deactivate'])->name('parties.deactivate')->can('party.update');
    Route::post('parties/{party}/review', [PartyController::class, 'review'])->name('parties.review')->can('party.review');
    Route::post('parties/{party}/activate', [PartyController::class, 'activate'])->name('parties.activate')->can('party.update');

    // Page 36: Trial balance (T2.9).
    Route::get('reports/trial-balance', [TrialBalanceController::class, 'show'])->name('reports.trial-balance')->can('report.view');

    // Page 47: Opening balances (T2.6).
    // Page 28: daily cash check (T6.1).
    Route::get('cash-counts', [CashCountController::class, 'index'])->name('cash-counts.index')->can('cashcount.create');
    Route::get('cash-counts/system-amount', [CashCountController::class, 'systemAmount'])->name('cash-counts.system-amount')->can('cashcount.create');
    Route::post('cash-counts', [CashCountController::class, 'store'])->name('cash-counts.store')->can('cashcount.create');
    Route::post('cash-counts/dismiss', [CashCountController::class, 'dismiss'])->name('cash-counts.dismiss')->can('period.close');

    // Page 41: audit log (T5.5).
    Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-log.index')->can('audit.view');

    // Page 43: users (T5.2). No delete route: users are only disabled (BR-USR-03).
    Route::get('settings/users', [UserController::class, 'index'])->name('users.index')->can('users.manage');
    Route::post('settings/users', [UserController::class, 'store'])->name('users.store')->can('users.manage');
    Route::put('settings/users/{user}/role', [UserController::class, 'updateRole'])->name('users.role')->can('users.manage');
    Route::put('settings/users/{user}/password', [UserController::class, 'resetPassword'])->name('users.password')->can('users.manage');
    Route::post('settings/users/{user}/disable', [UserController::class, 'disable'])->name('users.disable')->can('users.manage');
    Route::post('settings/users/{user}/enable', [UserController::class, 'enable'])->name('users.enable')->can('users.manage');

    // Page 42: company settings (T5.1). `/settings` was the starter kit's redirect to the profile page.
    Route::get('settings', [CompanySettingsController::class, 'edit'])->name('company-settings.edit')->can('settings.manage');
    Route::put('settings', [CompanySettingsController::class, 'update'])->name('company-settings.update')->can('settings.manage');

    Route::get('settings/opening-balances', [OpeningBalanceController::class, 'index'])->name('opening-balances.index')->can('opening.manage');
    Route::post('settings/opening-balances', [OpeningBalanceController::class, 'store'])->name('opening-balances.store')->can('opening.manage');
    Route::post('settings/opening-balances/complete', [OpeningBalanceController::class, 'complete'])->name('opening-balances.complete')->can('opening.manage');

    // Page 46: Accounts setup (T2.2).
    Route::get('settings/accounts', [MoneyAccountController::class, 'index'])->name('money-accounts.index')->can('settings.manage');
    Route::post('settings/accounts', [MoneyAccountController::class, 'store'])->name('money-accounts.store')->can('settings.manage');
    Route::put('settings/accounts/{moneyAccount}', [MoneyAccountController::class, 'update'])->name('money-accounts.update')->can('settings.manage');
    Route::post('settings/accounts/{moneyAccount}/deactivate', [MoneyAccountController::class, 'deactivate'])->name('money-accounts.deactivate')->can('settings.manage');

    // Page 44: Expense categories (T4.1).
    Route::get('settings/expense-categories', [ExpenseCategoryController::class, 'index'])->name('expense-categories.index')->can('settings.manage');
    Route::post('settings/expense-categories', [ExpenseCategoryController::class, 'store'])->name('expense-categories.store')->can('settings.manage');
    Route::put('settings/expense-categories/{category}', [ExpenseCategoryController::class, 'update'])->name('expense-categories.update')->can('settings.manage');
    Route::post('settings/expense-categories/{category}/deactivate', [ExpenseCategoryController::class, 'deactivate'])->name('expense-categories.deactivate')->can('settings.manage');

    // Page 45: Items (T2.3).
    Route::get('settings/items', [ItemController::class, 'index'])->name('items.index')->can('settings.manage');
    Route::post('settings/items', [ItemController::class, 'store'])->name('items.store')->can('settings.manage');
    Route::put('settings/items/{item}', [ItemController::class, 'update'])->name('items.update')->can('settings.manage');
    Route::post('settings/items/{item}/deactivate', [ItemController::class, 'deactivate'])->name('items.deactivate')->can('settings.manage');
});

require __DIR__.'/settings.php';
