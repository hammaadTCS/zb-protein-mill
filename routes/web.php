<?php

use App\Http\Controllers\MoneyAccountController;
use Illuminate\Routing\RedirectController;
use Illuminate\Support\Facades\Route;

// GET only (see routes/settings.php): Route::redirect() would also register QUERY.
Route::get('/', RedirectController::class)
    ->defaults('destination', '/dashboard')
    ->defaults('status', 302)
    ->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // Page 46: Accounts setup (T2.2).
    Route::get('settings/accounts', [MoneyAccountController::class, 'index'])->name('money-accounts.index');
    Route::post('settings/accounts', [MoneyAccountController::class, 'store'])->name('money-accounts.store');
    Route::put('settings/accounts/{moneyAccount}', [MoneyAccountController::class, 'update'])->name('money-accounts.update');
    Route::post('settings/accounts/{moneyAccount}/deactivate', [MoneyAccountController::class, 'deactivate'])->name('money-accounts.deactivate');
});

require __DIR__.'/settings.php';
