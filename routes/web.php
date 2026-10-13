<?php

use Illuminate\Routing\RedirectController;
use Illuminate\Support\Facades\Route;

// GET only (see routes/settings.php): Route::redirect() would also register QUERY.
Route::get('/', RedirectController::class)
    ->defaults('destination', '/dashboard')
    ->defaults('status', 302)
    ->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
