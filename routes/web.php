<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\MoneyAccountController;
use App\Http\Controllers\OpeningBalanceController;
use App\Http\Controllers\PartnerTransactionController;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\RatePendingController;
use App\Http\Controllers\Reports\TrialBalanceController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\TransferController;
use Illuminate\Routing\RedirectController;
use Illuminate\Support\Facades\Route;

// GET only (see routes/settings.php): Route::redirect() would also register QUERY.
Route::get('/', RedirectController::class)
    ->defaults('destination', '/dashboard')
    ->defaults('status', 302)
    ->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // Pages 3, 4 and 5: purchases (T2.7, T2.8).
    Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
    Route::get('purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
    Route::post('purchases', [PurchaseController::class, 'store'])->name('purchases.store');
    Route::get('purchases/last-rate', [PurchaseController::class, 'lastRate'])->name('purchases.last-rate');
    Route::get('purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');
    Route::get('purchases/{purchase}/edit', [PurchaseController::class, 'edit'])->name('purchases.edit');
    Route::put('purchases/{purchase}', [PurchaseController::class, 'update'])->name('purchases.update');
    Route::post('purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])->name('purchases.cancel');
    Route::post('purchases/{purchase}/rate', [PurchaseController::class, 'addRate'])->name('purchases.rate');

    // Pages 12, 13 and 14: expenses (T4.2, T4.3).
    Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::get('expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
    Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::get('expenses/link-lookup', [ExpenseController::class, 'linkLookup'])->name('expenses.link-lookup');
    Route::get('expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');
    Route::get('expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
    Route::put('expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
    Route::post('expenses/{expense}/cancel', [ExpenseController::class, 'cancel'])->name('expenses.cancel');

    // Pages 15 and 16: transfers (T4.5).
    Route::get('transfers', [TransferController::class, 'index'])->name('transfers.index');
    Route::get('transfers/create', [TransferController::class, 'create'])->name('transfers.create');
    Route::post('transfers', [TransferController::class, 'store'])->name('transfers.store');
    Route::get('transfers/{transfer}/edit', [TransferController::class, 'edit'])->name('transfers.edit');
    Route::put('transfers/{transfer}', [TransferController::class, 'update'])->name('transfers.update');
    Route::post('transfers/{transfer}/cancel', [TransferController::class, 'cancel'])->name('transfers.cancel');

    // Pages 21, 22 and 23: partner money (T4.4).
    Route::get('partners', [PartnerTransactionController::class, 'index'])->name('partners.index');
    Route::get('partners/create', [PartnerTransactionController::class, 'create'])->name('partners.create');
    Route::post('partners', [PartnerTransactionController::class, 'store'])->name('partners.store');
    Route::get('partners/statement', [PartnerTransactionController::class, 'statement'])->name('partners.statement');
    Route::get('partners/{partnerTransaction}/edit', [PartnerTransactionController::class, 'edit'])->name('partners.edit');
    Route::put('partners/{partnerTransaction}', [PartnerTransactionController::class, 'update'])->name('partners.update');
    Route::post('partners/{partnerTransaction}/cancel', [PartnerTransactionController::class, 'cancel'])->name('partners.cancel');

    // Pages 9, 10 and 11: payments and receipts (T3.5, T3.6).
    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('payments/create', [PaymentController::class, 'create'])->name('payments.create');
    Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('payments/balance-preview', [PaymentController::class, 'balancePreview'])->name('payments.balance-preview');
    Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    Route::get('payments/{payment}/edit', [PaymentController::class, 'edit'])->name('payments.edit');
    Route::put('payments/{payment}', [PaymentController::class, 'update'])->name('payments.update');
    Route::post('payments/{payment}/cancel', [PaymentController::class, 'cancel'])->name('payments.cancel');

    // Pages 6, 7 and 8: sales (T3.4).
    Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
    Route::get('sales/create', [SaleController::class, 'create'])->name('sales.create');
    Route::post('sales', [SaleController::class, 'store'])->name('sales.store');
    Route::get('sales/last-rate', [SaleController::class, 'lastRate'])->name('sales.last-rate');
    Route::get('sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
    Route::get('sales/{sale}/edit', [SaleController::class, 'edit'])->name('sales.edit');
    Route::put('sales/{sale}', [SaleController::class, 'update'])->name('sales.update');
    Route::post('sales/{sale}/cancel', [SaleController::class, 'cancel'])->name('sales.cancel');
    Route::post('sales/{sale}/rate', [SaleController::class, 'addRate'])->name('sales.rate');

    // Page 27: approvals (T3.7).
    Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::post('approvals/{payment}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
    Route::post('approvals/{payment}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');

    // Page 26: rate pending (T3.1).
    Route::get('rate-pending', [RatePendingController::class, 'index'])->name('rate-pending.index');

    // Slip and receipt photos, only through a permission check (BR-SEC-06, T2.8).
    Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');

    // Pages 17 and 18: parties (T2.5). No delete route exists (BR-PTY-05).
    Route::get('parties', [PartyController::class, 'index'])->name('parties.index');
    Route::get('parties/create', [PartyController::class, 'create'])->name('parties.create');
    Route::get('parties/search', [PartyController::class, 'lookup'])->name('parties.search');
    Route::post('parties', [PartyController::class, 'store'])->name('parties.store');
    Route::get('parties/{party}', [PartyController::class, 'show'])->name('parties.show');
    Route::get('parties/{party}/edit', [PartyController::class, 'edit'])->name('parties.edit');
    Route::put('parties/{party}', [PartyController::class, 'update'])->name('parties.update');
    Route::post('parties/{party}/deactivate', [PartyController::class, 'deactivate'])->name('parties.deactivate');
    Route::post('parties/{party}/activate', [PartyController::class, 'activate'])->name('parties.activate');

    // Page 36: Trial balance (T2.9).
    Route::get('reports/trial-balance', [TrialBalanceController::class, 'show'])->name('reports.trial-balance');

    // Page 47: Opening balances (T2.6).
    Route::get('settings/opening-balances', [OpeningBalanceController::class, 'index'])->name('opening-balances.index');
    Route::post('settings/opening-balances', [OpeningBalanceController::class, 'store'])->name('opening-balances.store');
    Route::post('settings/opening-balances/complete', [OpeningBalanceController::class, 'complete'])->name('opening-balances.complete');

    // Page 46: Accounts setup (T2.2).
    Route::get('settings/accounts', [MoneyAccountController::class, 'index'])->name('money-accounts.index');
    Route::post('settings/accounts', [MoneyAccountController::class, 'store'])->name('money-accounts.store');
    Route::put('settings/accounts/{moneyAccount}', [MoneyAccountController::class, 'update'])->name('money-accounts.update');
    Route::post('settings/accounts/{moneyAccount}/deactivate', [MoneyAccountController::class, 'deactivate'])->name('money-accounts.deactivate');

    // Page 44: Expense categories (T4.1).
    Route::get('settings/expense-categories', [ExpenseCategoryController::class, 'index'])->name('expense-categories.index');
    Route::post('settings/expense-categories', [ExpenseCategoryController::class, 'store'])->name('expense-categories.store');
    Route::put('settings/expense-categories/{category}', [ExpenseCategoryController::class, 'update'])->name('expense-categories.update');
    Route::post('settings/expense-categories/{category}/deactivate', [ExpenseCategoryController::class, 'deactivate'])->name('expense-categories.deactivate');

    // Page 45: Items (T2.3).
    Route::get('settings/items', [ItemController::class, 'index'])->name('items.index');
    Route::post('settings/items', [ItemController::class, 'store'])->name('items.store');
    Route::put('settings/items/{item}', [ItemController::class, 'update'])->name('items.update');
    Route::post('settings/items/{item}/deactivate', [ItemController::class, 'deactivate'])->name('items.deactivate');
});

require __DIR__.'/settings.php';
