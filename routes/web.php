<?php

use App\Http\Controllers\ItemController;
use App\Http\Controllers\MoneyAccountController;
use App\Http\Controllers\PartyController;
use Illuminate\Routing\RedirectController;
use Illuminate\Support\Facades\Route;

// GET only (see routes/settings.php): Route::redirect() would also register QUERY.
Route::get('/', RedirectController::class)
    ->defaults('destination', '/dashboard')
    ->defaults('status', 302)
    ->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    // Pages 17 and 18: parties (T2.5). No delete route exists (BR-PTY-05).
    Route::get('parties', [PartyController::class, 'index'])->name('parties.index');
    Route::get('parties/create', [PartyController::class, 'create'])->name('parties.create');
    Route::get('parties/search', [PartyController::class, 'lookup'])->name('parties.search');
    Route::post('parties', [PartyController::class, 'store'])->name('parties.store');
    Route::get('parties/{party}/edit', [PartyController::class, 'edit'])->name('parties.edit');
    Route::put('parties/{party}', [PartyController::class, 'update'])->name('parties.update');
    Route::post('parties/{party}/deactivate', [PartyController::class, 'deactivate'])->name('parties.deactivate');
    Route::post('parties/{party}/activate', [PartyController::class, 'activate'])->name('parties.activate');

    // Page 46: Accounts setup (T2.2).
    Route::get('settings/accounts', [MoneyAccountController::class, 'index'])->name('money-accounts.index');
    Route::post('settings/accounts', [MoneyAccountController::class, 'store'])->name('money-accounts.store');
    Route::put('settings/accounts/{moneyAccount}', [MoneyAccountController::class, 'update'])->name('money-accounts.update');
    Route::post('settings/accounts/{moneyAccount}/deactivate', [MoneyAccountController::class, 'deactivate'])->name('money-accounts.deactivate');

    // Page 45: Items (T2.3).
    Route::get('settings/items', [ItemController::class, 'index'])->name('items.index');
    Route::post('settings/items', [ItemController::class, 'store'])->name('items.store');
    Route::put('settings/items/{item}', [ItemController::class, 'update'])->name('items.update');
    Route::post('settings/items/{item}/deactivate', [ItemController::class, 'deactivate'])->name('items.deactivate');
});

require __DIR__.'/settings.php';
